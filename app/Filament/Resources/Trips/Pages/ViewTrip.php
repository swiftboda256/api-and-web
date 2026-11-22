<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use App\Models\DeliveryStop;
use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\TripStop;
use Carbon\CarbonInterface;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    protected string $view = 'filament.resources.trips.pages.view-trip';

    #[Url(history: true)]
    public string $tab = 'map';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->hasRecord() ? $this->trip()->trip_number : parent::getTitle();
    }

    #[Computed]
    public function trip(): Trip
    {
        return Trip::query()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with([
                'rider.riderProfile.vehicle.vehicleType',
                'vehicleType',
                'zone',
                'cancellationReason',
                'passengers.customer',
                'passengers.stops',
                'passengers.cancellationReason',
                'passengers.fareBreakdown',
                'deliveries.sender',
                'deliveries.stops',
                'deliveries.cancellationReason',
            ])
            ->where('id', $this->getRecord()->getKey())
            ->firstOrFail();
    }

    /**
     * Trips carry no fare of their own -- the real total is the sum of what everyone on it
     * (one passenger/delivery for a solo trip, several for a shared one) is actually paying.
     */
    #[Computed]
    public function manifestTotalFare(): float
    {
        $trip = $this->trip();

        if (in_array($trip->type, ['delivery', 'delivery_share'], true)) {
            return (float) $trip->deliveries->sum(fn ($delivery) => (float) ($delivery->final_fare ?? $delivery->estimated_fare));
        }

        return (float) $trip->passengers->sum(function ($passenger): float {
            $fareBreakdown = $passenger->fareBreakdown;

            if ($fareBreakdown === null) {
                return 0.0;
            }

            return (float) ($fareBreakdown->final_fare ?? $fareBreakdown->estimated_fare ?? 0);
        });
    }

    /**
     * Data for the map tab: the journey from the first pickup to the last dropoff.
     *
     * - stops: every pickup/dropoff on the trip (all passengers/deliveries on a shared trip), in the
     *   order the rider visited them, so it reads Pickup -> stops in between -> Destination.
     * - path: the rider's GPS breadcrumbs recorded between the first pickup and the last dropoff
     *   (up to the latest ping while the trip is still under way). The drive to the pickup is left out.
     *
     * @return array{path: list<array{0: float, 1: float}>, stops: list<array{lat: float, lng: float, type: string, role: string, label: string, name: string, address: ?string, time: ?string}>, pickedUp: bool, active: bool}
     */
    #[Computed]
    public function mapData(): array
    {
        $trip = $this->trip();

        $stops = [];

        if (in_array($trip->type, ['delivery', 'delivery_share'], true)) {
            foreach ($trip->deliveries->values() as $index => $delivery) {
                $name = filled($delivery->sender->name) ? $delivery->sender->name : 'Delivery '.($index + 1);
                array_push($stops, ...self::journeyStops($delivery->stops, $name, $delivery->picked_up_at, $delivery->dropped_off_at));
            }

            $pickedUpAt = $trip->deliveries->pluck('picked_up_at')->filter()->min();
            $allDroppedOff = $trip->deliveries->isNotEmpty() && $trip->deliveries->every(fn ($delivery): bool => $delivery->dropped_off_at !== null);
            $droppedOffAt = $allDroppedOff ? $trip->deliveries->pluck('dropped_off_at')->max() : null;
        } else {
            foreach ($trip->passengers->values() as $index => $passenger) {
                $name = filled($passenger->customer->name) ? $passenger->customer->name : 'Passenger '.($index + 1);
                array_push($stops, ...self::journeyStops($passenger->stops, $name, $passenger->picked_up_at, $passenger->dropped_off_at));
            }

            $pickedUpAt = $trip->passengers->pluck('picked_up_at')->filter()->min();
            $allDroppedOff = $trip->passengers->isNotEmpty() && $trip->passengers->every(fn ($passenger): bool => $passenger->dropped_off_at !== null);
            $droppedOffAt = $allDroppedOff ? $trip->passengers->pluck('dropped_off_at')->max() : null;
        }

        $pickedUpAt ??= $trip->started_at;
        $droppedOffAt ??= $trip->completed_at;

        // Visited stops in the order they happened; stops not reached yet follow in planned order.
        usort($stops, fn (array $a, array $b): int => [$a['time'] === null, $a['time'], $a['sequence']] <=> [$b['time'] === null, $b['time'], $b['sequence']]);

        $lastIndex = count($stops) - 1;
        $stops = array_map(function (array $stop, int $index) use ($lastIndex): array {
            $role = match (true) {
                $index === 0 => 'start',
                $index === $lastIndex => 'destination',
                default => 'stop',
            };

            return [
                'lat' => $stop['lat'],
                'lng' => $stop['lng'],
                'type' => $stop['type'],
                'role' => $role,
                'label' => match ($role) {
                    'start' => 'Pickup',
                    'destination' => 'Destination',
                    default => 'Stop '.$index.' · '.str($stop['type'])->headline(),
                },
                'name' => $stop['name'],
                'address' => $stop['address'],
                'time' => $stop['time']?->format('d M Y, H:i'),
            ];
        }, $stops, array_keys($stops));

        $path = [];

        if ($pickedUpAt !== null) {
            $path = TripLocation::query()
                ->where('trip_id', $trip->id)
                ->where('recorded_at', '>=', $pickedUpAt)
                ->when($droppedOffAt, fn ($query, $droppedOffAt) => $query->where('recorded_at', '<=', $droppedOffAt))
                ->orderBy('recorded_at')
                ->orderBy('id')
                ->get(['location'])
                ->map(fn (TripLocation $location): array => [$location->location->getLatitude(), $location->location->getLongitude()])
                ->values()
                ->all();
        }

        return [
            'path' => array_values($path),
            'stops' => $stops,
            'pickedUp' => $pickedUpAt !== null,
            'active' => $droppedOffAt === null && in_array($trip->status, ['accepted', 'arrived', 'in_progress'], true),
        ];
    }

    /**
     * The time a stop was actually reached: the passenger/delivery's pickup or dropoff time,
     * falling back to when the rider marked arrival at the stop.
     *
     * @param  iterable<TripStop|DeliveryStop>  $stops
     * @return list<array{lat: float, lng: float, type: string, name: string, address: ?string, time: ?CarbonInterface, sequence: int}>
     */
    private static function journeyStops(iterable $stops, string $name, ?CarbonInterface $pickedUpAt, ?CarbonInterface $droppedOffAt): array
    {
        $mapped = [];

        foreach ($stops as $stop) {
            if ($stop->location === null) {
                continue;
            }

            $mapped[] = [
                'lat' => $stop->location->getLatitude(),
                'lng' => $stop->location->getLongitude(),
                'type' => $stop->stop_type,
                'name' => $name,
                'address' => $stop->address,
                'time' => ($stop->stop_type === 'pickup' ? $pickedUpAt : $droppedOffAt) ?? $stop->arrived_at,
                'sequence' => (int) $stop->sequence,
            ];
        }

        return $mapped;
    }

    #[Computed]
    public function manifestCount(): int
    {
        $trip = $this->trip();

        return in_array($trip->type, ['delivery', 'delivery_share'], true)
            ? $trip->deliveries->count()
            : $trip->passengers->count();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    protected function getHeaderActions(): array
    {
        return [
            //            EditAction::make(),
        ];
    }
}
