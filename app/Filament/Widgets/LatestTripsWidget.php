<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Trips\TripResource;
use App\Models\Trip;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestTripsWidget extends TableWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $canViewTrips = TripResource::canViewAny();

        return $table
            ->heading('Latest trips')
            ->query(fn () => Trip::query()
                ->with(['passengers.customer', 'deliveries.sender', 'rider'])
                ->withSum('fareBreakdowns as passenger_fare', 'final_fare')
                ->withSum('fareBreakdowns as passenger_estimate', 'estimated_fare')
                ->withSum('deliveries as delivery_fare', 'final_fare')
                ->withSum('deliveries as delivery_estimate', 'estimated_fare')
                ->latest())
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->limit(8))
            ->columns([
                TextColumn::make('trip_number')
                    ->label('Trip')
                    ->fontFamily('mono')
                    ->weight('medium'),
                TextColumn::make('type')
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'accepted', 'arrived', 'in_progress' => 'info',
                        'requested', 'searching' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('customer')
                    ->label('Customer')
                    ->state(fn (Trip $record): ?string => $record->primaryCustomer()?->name)
                    ->placeholder('—'),
                TextColumn::make('rider.name')
                    ->label('Rider')
                    ->placeholder('Not assigned'),
                TextColumn::make('fare')
                    ->label('Fare')
                    ->state(fn (Trip $record): ?float => $this->fare($record))
                    ->formatStateUsing(fn (?float $state): string => 'UGX '.number_format((float) $state))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->since()
                    ->dateTimeTooltip('d M Y, H:i'),
            ])
            ->recordUrl($canViewTrips ? fn (Trip $record): string => TripResource::getUrl('view', ['record' => $record]) : null)
            ->headerActions([
                Action::make('all')
                    ->label('View all trips')
                    ->link()
                    ->icon('heroicon-m-arrow-right')
                    ->iconPosition('after')
                    ->url(fn (): string => TripResource::getUrl())
                    ->visible($canViewTrips),
            ]);
    }

    /**
     * Final fare once set, else the estimate (sum over the trip's passengers or deliveries).
     */
    private function fare(Trip $record): ?float
    {
        $values = $record->getAttributes();

        foreach (['passenger_fare', 'delivery_fare', 'passenger_estimate', 'delivery_estimate'] as $key) {
            if (($values[$key] ?? null) !== null && (float) $values[$key] > 0) {
                return (float) $values[$key];
            }
        }

        return null;
    }
}
