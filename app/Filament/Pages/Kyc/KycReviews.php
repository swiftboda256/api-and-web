<?php

namespace App\Filament\Pages\Kyc;

use App\Filament\Pages\Riders\RidersIndex;
use App\Models\Document;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Rider\KycQueue;
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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The KYC review queue. Tabs come from KycQueue; a row opens KycReview.
 */
class KycReviews extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.kyc.kyc-reviews';

    protected static ?string $slug = 'kyc-reviews';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'KYC Reviews';

    protected static ?string $title = 'KYC Reviews';

    public const array TAB_LABELS = [
        'awaiting' => 'Awaiting review',
        'incomplete' => 'Incomplete',
        'rejected' => 'Rejected',
        'approved' => 'Approved',
        'all' => 'All',
    ];

    #[Url(as: 'tab')]
    public string $activeTab = 'awaiting';

    /** @var array<string, mixed>|null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public static function getNavigationBadge(): ?string
    {
        $count = KycQueue::awaitingCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Riders awaiting review';
    }

    public function mount(): void
    {
        if (! in_array($this->activeTab, KycQueue::TABS, true)) {
            $this->activeTab = 'awaiting';
        }
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, KycQueue::TABS, true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetPage($this->getTablePaginationPageName());
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        return collect(KycQueue::TABS)->mapWithKeys(fn (string $tab): array => [$tab => KycQueue::query($tab)->count()])->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => KycQueue::withWaitingSince(KycQueue::query($this->activeTab)->select('users.*'))
                ->with(['riderProfile.documents', 'riderProfile.vehicle.vehicleType', 'riderProfile.homeZone']))
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => RidersIndex::initialsAvatarUrl($record)),
                TextColumn::make('name')
                    ->label('Rider')
                    ->weight('medium')
                    ->description(fn (User $record): ?string => $record->riderProfile?->rider_ref)
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('riderProfile.national_id_number')
                    ->label('National ID no.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('national_id_status')
                    ->label('National ID')
                    ->state(fn (User $record): string => $this->documentStatus($record, 'national_id'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('license_status')
                    ->label('License')
                    ->state(fn (User $record): string => $this->documentStatus($record, 'driving_license'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('vehicle_status')
                    ->label('Vehicle')
                    ->state(fn (User $record): string => $record->riderProfile?->vehicle->status ?? 'missing')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->description(fn (User $record): ?string => $record->riderProfile?->vehicle?->plate_number),
                TextColumn::make('riderProfile.kyc_status')
                    ->label('KYC')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state ?? 'unknown')->headline()->toString())
                    ->color(fn (?string $state): string => self::statusColor($state ?? '')),
                TextColumn::make('kyc_waiting_since')
                    ->label('Waiting')
                    ->since()
                    ->dateTimeTooltip('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('kyc_waiting_since', $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('riderProfile.homeZone.name')
                    ->label('Zone')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('home_zone_id')
                    ->label('Zone')
                    ->options(fn (): array => Zone::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $value) => $query->whereHas('riderProfile', fn (Builder $query) => $query->where('home_zone_id', $value)),
                    )),
                SelectFilter::make('vehicle_type_id')
                    ->label('Vehicle type')
                    ->options(fn (): array => VehicleType::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $value) => $query->whereHas('riderProfile.vehicle', fn (Builder $query) => $query->where('vehicle_type_id', $value)),
                    )),
            ])
            // Oldest first while reviewing, so nobody gets skipped; newest riders first elsewhere.
            ->defaultSort(fn (Builder $query): Builder => $this->activeTab === 'awaiting'
                ? $query->orderBy('kyc_waiting_since')->orderBy('users.id')
                : $query->orderByDesc('users.created_at'))
            ->recordUrl(fn (User $record): string => KycReview::getUrl(['record' => $record->id]))
            ->emptyStateHeading(fn (): string => $this->activeTab === 'awaiting' ? 'Nothing waiting for review' : 'No riders here')
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'approved' => 'success',
            'pending', 'under_review' => 'warning',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    private function documentStatus(User $record, string $type): string
    {
        /** @var Document|null $document */
        $document = $record->riderProfile?->documents->firstWhere('document_type', $type);

        return $document->status ?? 'missing';
    }
}
