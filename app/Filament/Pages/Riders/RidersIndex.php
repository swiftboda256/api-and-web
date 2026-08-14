<?php

namespace App\Filament\Pages\Riders;

use App\Models\User;
use App\Models\VehicleType;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RidersIndex extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.riders.riders-index';

    protected static ?string $slug = 'riders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Riders';

    protected static ?string $title = 'Riders';

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->whereHas('riderProfile')->with(['riderProfile.homeZone', 'riderProfile.vehicle.vehicleType']))
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => self::initialsAvatarUrl($record)),
                TextColumn::make('name')
                    ->label('Rider')
                    ->weight('medium')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('riderProfile.rider_ref')
                    ->label('Rider ref')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('riderProfile.kyc_status')
                    ->label('KYC status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state ?? 'unknown')->headline())
                    ->color(fn (?string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('riderProfile.homeZone.name')
                    ->label('Zone')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('riderProfile.current_location')
                    ->label('Current location')
                    ->placeholder('—')
                    ->formatStateUsing(fn (User $record): ?string => $record->riderProfile?->current_location
                        ? number_format($record->riderProfile->current_location->getLatitude(), 5).', '.number_format($record->riderProfile->current_location->getLongitude(), 5)
                        : null),
                TextColumn::make('rating_avg')
                    ->label('Rating')
                    ->formatStateUsing(fn (User $record): string => '⭐ '.number_format((float) $record->rating_avg, 2)." ({$record->rating_count})"),
                TextColumn::make('riderProfile.total_trips')
                    ->label('Total trips')
                    ->numeric(),
                TextColumn::make('riderProfile.vehicle.vehicleType.name')
                    ->label('Vehicle')
                    ->placeholder('—')
                    ->description(fn (User $record): ?string => $record->riderProfile?->vehicle?->plate_number),
            ])
            ->filters([
                SelectFilter::make('vehicle_type_id')
                    ->label('Vehicle type')
                    ->options(fn (): array => VehicleType::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $value) => $query->whereHas(
                            'riderProfile.vehicle', fn (Builder $query) => $query->where('vehicle_type_id', $value)
                        ),
                    )),
            ])
            ->recordUrl(fn (User $record): string => RiderDetails::getUrl(['record' => $record->id]))
            ->defaultSort('created_at', 'desc');
    }

    private static function initialsAvatarUrl(User $record): string
    {
        $initials = $record->initials() !== '' ? $record->initials() : '?';

        $palette = ['f59e0b', '10b981', '0ea5e9', '8b5cf6', 'f43f5e', '14b8a6'];
        $color = $palette[$record->id % count($palette)];

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40">
                <rect width="40" height="40" rx="20" fill="#{$color}" />
                <text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="16" fill="#ffffff">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
