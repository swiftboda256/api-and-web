<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatRoom;
use App\Models\ChatRoomParticipant;
use App\Models\DeliveryDetails;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trip-scoped chat between a driver and a customer. Each driver-customer pair on a trip gets
 * its own private room -- a ride_share/delivery_share trip with several passengers never gets
 * a group chat, it gets one room per passenger. A room is only created lazily, on the first
 * message sent into it (see findOrCreateRoom()); it never locks, even once the booking is
 * completed or cancelled.
 */
readonly class ChatService
{
    public function __construct(
        private FcmGateway $push,
    ) {}

    // --- Customer side -------------------------------------------------------------------
    // $bookingId follows the same convention as TripService::show(): it's the customer's own
    // booking id (TripPassenger or DeliveryDetails), not the vehicle trip's id -- the same
    // thing GET user-app/trips/{id} takes, disambiguated by the optional $type.

    public function customerRoom(User $customer, int $bookingId, ?string $type = null): ChatRoomSummary
    {
        $booking = $this->resolveBooking($customer, $bookingId, $type);

        return $this->toSummary($booking, $this->existingRoom($booking->trip, $customer), $customer);
    }

    /**
     * @return LengthAwarePaginator<int, ChatMessage>
     */
    public function customerMessages(User $customer, int $bookingId, ?string $type, int $perPage): LengthAwarePaginator
    {
        $booking = $this->resolveBooking($customer, $bookingId, $type);
        $room = $this->existingRoom($booking->trip, $customer);

        return $this->paginateMessages($room, $perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendAsCustomer(User $customer, int $bookingId, ?string $type, array $data): ChatMessage
    {
        $booking = $this->resolveBooking($customer, $bookingId, $type);
        $this->assertChatOpen($booking);

        return $this->send($customer, $booking->trip->rider, $booking->trip, $customer, $data);
    }

    public function markReadAsCustomer(User $customer, int $bookingId, ?string $type): int
    {
        $booking = $this->resolveBooking($customer, $bookingId, $type);

        return $this->markRead($customer, $this->existingRoom($booking->trip, $customer));
    }

    // --- Driver side -----------------------------------------------------------------------

    /**
     * @return Collection<int, ChatRoomSummary> one entry per passenger/delivery on the trip
     */
    public function driverRooms(User $driver, int $tripId): Collection
    {
        $trip = $this->ownRide($driver, $tripId);

        $bookings = in_array($trip->type, ['ride', 'ride_share', 'posted_ride'], true)
            ? $trip->passengers()->with('customer')->get()
            : $trip->deliveries()->with('sender')->get();

        return $bookings->map(function (TripPassenger|DeliveryDetails $booking) use ($trip, $driver) {
            $booking->setRelation('trip', $trip);

            return $this->toSummary($booking, $this->existingRoom($trip, $this->customerOf($booking)), $driver);
        });
    }

    /**
     * @return LengthAwarePaginator<int, ChatMessage>
     */
    public function driverPassengerMessages(User $driver, int $tripId, int $passengerId, int $perPage): LengthAwarePaginator
    {
        $trip = $this->ownRide($driver, $tripId);
        $passenger = $this->passengerOnTrip($trip, $passengerId);

        return $this->paginateMessages($this->existingRoom($trip, $passenger->customer), $perPage);
    }

    /**
     * @return LengthAwarePaginator<int, ChatMessage>
     */
    public function driverDeliveryMessages(User $driver, int $tripId, int $deliveryId, int $perPage): LengthAwarePaginator
    {
        $trip = $this->ownRide($driver, $tripId);
        $delivery = $this->deliveryOnTrip($trip, $deliveryId);

        return $this->paginateMessages($this->existingRoom($trip, $delivery->sender), $perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendAsDriverToPassenger(User $driver, int $tripId, int $passengerId, array $data): ChatMessage
    {
        $trip = $this->ownRide($driver, $tripId);
        $passenger = $this->passengerOnTrip($trip, $passengerId);
        $this->assertChatOpen($passenger);

        return $this->send($driver, $passenger->customer, $trip, $passenger->customer, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendAsDriverToDelivery(User $driver, int $tripId, int $deliveryId, array $data): ChatMessage
    {
        $trip = $this->ownRide($driver, $tripId);
        $delivery = $this->deliveryOnTrip($trip, $deliveryId);
        $this->assertChatOpen($delivery);

        return $this->send($driver, $delivery->sender, $trip, $delivery->sender, $data);
    }

    public function markReadAsDriverForPassenger(User $driver, int $tripId, int $passengerId): int
    {
        $trip = $this->ownRide($driver, $tripId);
        $passenger = $this->passengerOnTrip($trip, $passengerId);

        return $this->markRead($driver, $this->existingRoom($trip, $passenger->customer));
    }

    public function markReadAsDriverForDelivery(User $driver, int $tripId, int $deliveryId): int
    {
        $trip = $this->ownRide($driver, $tripId);
        $delivery = $this->deliveryOnTrip($trip, $deliveryId);

        return $this->markRead($driver, $this->existingRoom($trip, $delivery->sender));
    }

    // --- Shared internals ------------------------------------------------------------------

    private function resolveBooking(User $customer, int $bookingId, ?string $type): TripPassenger|DeliveryDetails
    {
        $isDelivery = in_array($type, ['delivery', 'delivery_share'], true);

        $passenger = $isDelivery ? null : TripPassenger::query()
            ->where('customer_id', $customer->id)
            ->where('id', $bookingId)
            ->with('trip.rider')
            ->first();

        if ($passenger !== null) {
            return $passenger;
        }

        if ($type !== null && ! $isDelivery) {
            throw (new ModelNotFoundException)->setModel(TripPassenger::class, [$bookingId]);
        }

        return DeliveryDetails::query()
            ->where('sender_id', $customer->id)
            ->where('id', $bookingId)
            ->with('trip.rider')
            ->firstOrFail();
    }

    private function ownRide(User $driver, int $tripId): Trip
    {
        return Trip::query()
            ->where('id', $tripId)
            ->where('rider_id', $driver->id)
            ->firstOrFail();
    }

    private function passengerOnTrip(Trip $trip, int $passengerId): TripPassenger
    {
        return TripPassenger::query()
            ->where('trip_id', $trip->id)
            ->where('id', $passengerId)
            ->with('customer')
            ->firstOrFail();
    }

    private function deliveryOnTrip(Trip $trip, int $deliveryId): DeliveryDetails
    {
        return DeliveryDetails::query()
            ->where('trip_id', $trip->id)
            ->where('id', $deliveryId)
            ->with('sender')
            ->firstOrFail();
    }

    /**
     * Chat is write-only between being matched to a driver and the booking ending -- history
     * stays readable after it ends (see customerMessages()/driverPassengerMessages()/etc.,
     * none of which call this), only new messages are blocked.
     */
    private function assertChatOpen(TripPassenger|DeliveryDetails $booking): void
    {
        if (in_array($booking->status, ['requested', 'pending_approval'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chat opens once a driver has been matched to this booking.',
            ]);
        }

        if (in_array($booking->status, ['dropped_off', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This chat is closed because the trip has ended.',
            ]);
        }
    }

    private function customerOf(TripPassenger|DeliveryDetails $booking): User
    {
        return $booking instanceof DeliveryDetails ? $booking->sender : $booking->customer;
    }

    /**
     * The room for this driver-customer pair on this trip, if the two of them have already
     * exchanged a first message -- found through chat_room_participants, not a column on
     * chat_rooms, since a room's parties are exactly its two participants.
     */
    private function existingRoom(Trip $trip, User $customer): ?ChatRoom
    {
        return ChatRoom::query()
            ->where('trip_id', $trip->id)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $customer->id))
            ->first();
    }

    /**
     * Lazily creates the room for this driver-customer pair on its first message. The
     * existence check above is the only thing standing between this and a duplicate room if
     * both sides' first messages land at the same instant -- an acceptable risk for a human
     * typing speed race on a 1:1 chat, not worth a DB-level lock for.
     */
    private function findOrCreateRoom(Trip $trip, User $customer): ChatRoom
    {
        $room = $this->existingRoom($trip, $customer);

        if ($room !== null) {
            return $room;
        }

        $room = ChatRoom::query()->create(['type' => 'trip', 'trip_id' => $trip->id]);

        ChatRoomParticipant::query()->create(['chat_room_id' => $room->id, 'user_id' => $trip->rider_id]);
        ChatRoomParticipant::query()->create(['chat_room_id' => $room->id, 'user_id' => $customer->id]);

        return $room;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(User $sender, User $recipient, Trip $trip, User $customer, array $data): ChatMessage
    {
        $message = DB::transaction(function () use ($sender, $trip, $customer, $data): ChatMessage {
            $room = $this->findOrCreateRoom($trip, $customer);

            $message = ChatMessage::query()->create([
                'chat_room_id' => $room->id,
                'sender_id' => $sender->id,
                'message' => $data['message'] ?? null,
                'read_by' => [$sender->id],
            ]);

            foreach ($data['attachments'] ?? [] as $url) {
                ChatMessageAttachment::query()->create([
                    'chat_message_id' => $message->id,
                    'type' => $this->guessAttachmentType($url),
                    'url' => $url,
                ]);
            }

            $room->update([
                'recent_message' => $message->message,
                'last_message_at' => now(),
            ]);

            return $message;
        });

        $message->load(['sender', 'attachments', 'chatRoom']);

        event(new ChatMessageSent($message));

        $this->notifyMessageSent($message->chatRoom, $message, $recipient);

        return $message;
    }

    private function notifyMessageSent(ChatRoom $room, ChatMessage $message, User $recipient): void
    {
        $this->push->sendToUser($recipient, new PushMessage(
            type: 'chat_message_received',
            id: $room->id,
            title: $message->sender->name,
            body: $message->message ?? 'Sent an attachment',
            data: [
                'chat_room_id' => $room->id,
                'trip_id' => (string) $room->trip_id,
            ],
            interruptionLevel: 'active',
        ));
    }

    private function markRead(User $user, ?ChatRoom $room): int
    {
        if ($room === null) {
            return 0;
        }

        $touched = 0;

        $room->messages()
            ->where('sender_id', '!=', $user->id)
            ->get()
            ->each(function (ChatMessage $message) use ($user, &$touched): void {
                if (in_array($user->id, $message->read_by, true)) {
                    return;
                }

                $message->update(['read_by' => [...$message->read_by, $user->id]]);
                $touched++;
            });

        return $touched;
    }

    /**
     * @return LengthAwarePaginator<int, ChatMessage>
     */
    private function paginateMessages(?ChatRoom $room, int $perPage): LengthAwarePaginator
    {
        if ($room === null) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        return $room->messages()
            ->with(['sender', 'attachments'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function toSummary(TripPassenger|DeliveryDetails $booking, ?ChatRoom $room, User $viewer): ChatRoomSummary
    {
        $isDelivery = $booking instanceof DeliveryDetails;
        $customer = $this->customerOf($booking);
        $otherParty = $viewer->id === $booking->trip->rider_id ? $customer : $booking->trip->rider;

        return new ChatRoomSummary(
            id: $room?->id,
            tripId: $booking->trip_id,
            bookingType: $isDelivery ? 'delivery_details' : 'trip_passenger',
            bookingId: $booking->id,
            otherParty: $otherParty,
            recentMessage: $room?->recent_message,
            lastMessageAt: $room?->last_message_at,
            unreadCount: $room === null ? 0 : $room->messages()
                ->where('sender_id', '!=', $viewer->id)
                ->get()
                ->filter(fn (ChatMessage $message): bool => ! in_array($viewer->id, $message->read_by, true))
                ->count(),
        );
    }

    private function guessAttachmentType(string $url): string
    {
        return match (strtolower((string) pathinfo($url, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
            'mp4', 'mov', 'avi', 'webm' => 'video',
            'mp3', 'wav', 'm4a', 'ogg' => 'audio',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt' => 'document',
            default => 'other',
        };
    }
}
