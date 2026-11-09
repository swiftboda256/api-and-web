<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Chat\IndexChatMessageRequest;
use App\Http\Requests\Api\V1\UserApp\Chat\SendChatMessageRequest;
use App\Http\Resources\Api\V1\UserApp\ChatMessageCollection;
use App\Http\Resources\Api\V1\UserApp\ChatMessageResource;
use App\Http\Resources\Api\V1\UserApp\ChatRoomResource;
use App\Models\User;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request, int $trip, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(ChatRoomResource::collection($chatService->driverRooms($user, $trip)));
    }

    public function passengerMessages(IndexChatMessageRequest $request, int $trip, int $passenger, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $messages = $chatService->driverPassengerMessages($user, $trip, $passenger, (int) ($request->validated('per_page') ?? 15));

        return self::success(new ChatMessageCollection($messages));
    }

    public function sendToPassenger(SendChatMessageRequest $request, int $trip, int $passenger, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $message = $chatService->sendAsDriverToPassenger($user, $trip, $passenger, $request->validated());

        return self::success(new ChatMessageResource($message), status: 201);
    }

    public function markPassengerRead(Request $request, int $trip, int $passenger, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $chatService->markReadAsDriverForPassenger($user, $trip, $passenger);

        return self::success(['marked_read' => $count]);
    }

    public function deliveryMessages(IndexChatMessageRequest $request, int $trip, int $delivery, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $messages = $chatService->driverDeliveryMessages($user, $trip, $delivery, (int) ($request->validated('per_page') ?? 15));

        return self::success(new ChatMessageCollection($messages));
    }

    public function sendToDelivery(SendChatMessageRequest $request, int $trip, int $delivery, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $message = $chatService->sendAsDriverToDelivery($user, $trip, $delivery, $request->validated());

        return self::success(new ChatMessageResource($message), status: 201);
    }

    public function markDeliveryRead(Request $request, int $trip, int $delivery, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $chatService->markReadAsDriverForDelivery($user, $trip, $delivery);

        return self::success(['marked_read' => $count]);
    }
}
