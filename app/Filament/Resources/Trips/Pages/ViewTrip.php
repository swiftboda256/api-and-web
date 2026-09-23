<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use App\Models\Trip;
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
