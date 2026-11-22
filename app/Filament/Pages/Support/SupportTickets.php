<?php

namespace App\Filament\Pages\Support;

use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Services\SupportTicket\SupportQueue;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
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
 * Support ticket queue. Tabs come from SupportQueue; a row opens SupportTicketDetails.
 */
class SupportTickets extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.support.support-tickets';

    protected static ?string $slug = 'support-tickets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Support Tickets';

    protected static ?string $title = 'Support Tickets';

    public const array TAB_LABELS = [
        'needs_reply' => 'Needs reply',
        'mine' => 'Assigned to me',
        'unassigned' => 'Unassigned',
        'open' => 'All open',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'all' => 'All',
    ];

    #[Url(as: 'tab')]
    public string $activeTab = 'needs_reply';

    /** @var array<string, mixed>|null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public static function getNavigationBadge(): ?string
    {
        $count = SupportQueue::needsReplyCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Tickets waiting on a reply';
    }

    public function mount(): void
    {
        if (! in_array($this->activeTab, SupportQueue::TABS, true)) {
            $this->activeTab = 'needs_reply';
        }
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, SupportQueue::TABS, true)) {
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
        $agentId = Filament::auth()->id();

        return collect(SupportQueue::TABS)
            ->mapWithKeys(fn (string $tab): array => [$tab => SupportQueue::query($tab, is_int($agentId) ? $agentId : null)->count()])
            ->all();
    }

    public function table(Table $table): Table
    {
        $agentId = Filament::auth()->id();

        return $table
            ->query(fn (): Builder => SupportQueue::withActivity(
                SupportQueue::query($this->activeTab, is_int($agentId) ? $agentId : null)->select('support_tickets.*')
            )->with(['user.riderProfile', 'category', 'assignedTo', 'trip']))
            ->columns([
                IconColumn::make('awaiting_support')
                    ->label('')
                    ->boolean()
                    ->trueIcon(Heroicon::ChatBubbleLeftEllipsis)
                    ->falseIcon(Heroicon::Minus)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (SupportTicket $record): string => $record->getAttribute('awaiting_support') ? 'Waiting on support' : 'Waiting on the requester'),
                TextColumn::make('ticket_number')
                    ->label('Ticket')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('subject')
                    ->weight('medium')
                    ->limit(50)
                    ->description(fn (SupportTicket $record): ?string => $record->category?->name)
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Requester')
                    ->description(fn (SupportTicket $record): string => ($record->user?->riderProfile ? 'Rider' : 'Customer').' · '.($record->user->phone ?? ''))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('user', fn (Builder $user) => $user
                        ->whereAny(['first_name', 'last_name', 'other_name', 'phone', 'email'], 'ilike', "%{$search}%"))),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->color(fn (string $state): string => self::priorityColor($state))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END ".($direction === 'desc' ? 'DESC' : 'ASC'))),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('assignedTo.name')
                    ->label('Assignee')
                    ->placeholder('Unassigned'),
                TextColumn::make('trip.trip_number')
                    ->label('Trip')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('last_public_message_at')
                    ->label('Last message')
                    ->since()
                    ->dateTimeTooltip('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('last_public_message_at', $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('created_at')
                    ->label('Opened')
                    ->since()
                    ->dateTimeTooltip('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('priority')
                    ->options(array_combine(['low', 'medium', 'high', 'urgent'], ['Low', 'Medium', 'High', 'Urgent']))
                    ->multiple(),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => SupportCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('assigned_to')
                    ->label('Assignee')
                    ->options(fn (): array => SupportTicketDetails::agentOptions()),
                SelectFilter::make('requester_type')
                    ->label('Requester')
                    ->options(['rider' => 'Riders', 'customer' => 'Customers'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'rider' => $query->whereHas('user.riderProfile'),
                        'customer' => $query->whereDoesntHave('user.riderProfile'),
                        default => $query,
                    }),
            ])
            // Longest-waiting first while working the queue; newest first in the archive tabs.
            ->defaultSort(fn (Builder $query): Builder => in_array($this->activeTab, ['resolved', 'closed', 'all'], true)
                ? $query->orderByDesc('support_tickets.created_at')
                : $query->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")->orderBy('last_public_message_at'))
            ->recordUrl(fn (SupportTicket $record): string => SupportTicketDetails::getUrl(['record' => $record->id]))
            ->emptyStateHeading(fn (): string => $this->activeTab === 'needs_reply' ? 'Nobody is waiting on a reply' : 'No tickets here')
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->poll('30s');
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'open' => 'warning',
            'in_progress' => 'info',
            'resolved' => 'success',
            default => 'gray',
        };
    }

    public static function priorityColor(string $priority): string
    {
        return match ($priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            default => 'gray',
        };
    }
}
