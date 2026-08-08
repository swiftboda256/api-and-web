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
                'customer',
                'rider.riderProfile.vehicle.vehicleType',
                'vehicleType',
                'zone',
                'fareBreakdown',
                'promoCode',
                'cancellationReason',
            ])
            ->where('id', $this->getRecord()->getKey())
            ->firstOrFail();
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
