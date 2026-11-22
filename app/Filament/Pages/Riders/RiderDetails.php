<?php

namespace App\Filament\Pages\Riders;

use App\Filament\Pages\Riders\Concerns\ReviewsRiderKyc;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\UserAccountService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class RiderDetails extends Page
{
    use HasPageShield;
    use InteractsWithActions;
    use ReviewsRiderKyc;
    use WithPagination;

    private const array TRIP_SORT_COLUMNS = ['fare', 'requested_at'];

    private const array TRANSACTION_SORT_COLUMNS = ['created_at'];

    protected string $view = 'filament.pages.riders.rider-details';

    protected static ?string $slug = 'riders/{record}';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $recordId;

    #[Url(history: true)]
    public string $tab = 'kyc';

    public string $tripsSearch = '';

    public string $tripsType = '';

    public string $tripsStatus = '';

    public string $tripsSort = 'requested_at';

    public string $tripsSortDirection = 'desc';

    public string $transactionsSearch = '';

    public string $transactionsType = '';

    public string $transactionsMethod = '';

    public string $transactionsStatus = '';

    public string $transactionsSort = 'created_at';

    public string $transactionsSortDirection = 'desc';

    public function mount(int $record): void
    {
        $this->recordId = $record;

        // Resolves the rider now so an invalid id 404s on load, not on first tab interaction.
        $this->rider();
    }

    public function getTitle(): string
    {
        if (! isset($this->recordId)) {
            return 'Rider details';
        }

        $name = $this->rider()->name;

        return $name !== '' ? $name : 'Rider details';
    }

    #[Computed]
    public function rider(): User
    {
        return User::query()
            ->whereHas('riderProfile')
            ->with(['riderProfile.vehicle.vehicleType', 'riderProfile.vehicle.vehicleModel', 'riderProfile.homeZone', 'riderProfile.approvedBy', 'referredBy'])
            ->findOrFail($this->recordId);
    }

    /**
     * @return LengthAwarePaginator<int, Trip>
     */
    #[Computed]
    public function trips(): LengthAwarePaginator
    {
        $search = trim($this->tripsSearch);
        $direction = $this->tripsSortDirection === 'asc' ? 'asc' : 'desc';

        $nameMatch = function ($query) use ($search): void {
            $like = "%{$search}%";

            $query->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)
                ->orWhere('other_name', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like);
        };

        $query = Trip::query()
            ->where('rider_id', $this->recordId)
            ->with(['passengers.customer', 'passengers.fareBreakdown', 'deliveries.sender', 'vehicleType'])
            ->when($search !== '', function ($query) use ($search, $nameMatch): void {
                $like = "%{$search}%";

                $query->where(function ($query) use ($like, $nameMatch): void {
                    $query->where('trip_number', 'ilike', $like)
                        ->orWhereHas('passengers.customer', $nameMatch)
                        ->orWhereHas('deliveries.sender', $nameMatch);
                });
            })
            ->when($this->tripsType !== '', fn ($query) => $query->where('type', $this->tripsType))
            ->when($this->tripsStatus !== '', fn ($query) => $query->where('status', $this->tripsStatus));

        if ($this->tripsSort === 'fare') {
            // Trips carry no fare of their own -- order by the total across everyone on the
            // trip (one passenger/delivery for a solo trip, several for a shared one).
            // Ride/ride_share's fare lives on trip_fare_breakdowns now (one row per
            // passenger, joined via passenger_id), not on trip_passengers directly.
            $query->orderByRaw(<<<SQL
                (CASE
                    WHEN trips.type IN ('delivery', 'delivery_share')
                        THEN (SELECT COALESCE(SUM(COALESCE(final_fare, estimated_fare)), 0) FROM delivery_details WHERE delivery_details.trip_id = trips.id)
                    ELSE (
                        SELECT COALESCE(SUM(COALESCE(tfb.final_fare, tfb.estimated_fare)), 0)
                        FROM trip_passengers tp
                        JOIN trip_fare_breakdowns tfb ON tfb.passenger_id = tp.id
                        WHERE tp.trip_id = trips.id
                    )
                END) {$direction}
                SQL);
        } else {
            $query->orderBy('requested_at', $direction);
        }

        return $query->paginate(10, pageName: 'tripsPage');
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        $search = trim($this->transactionsSearch);
        $direction = $this->transactionsSortDirection === 'asc' ? 'asc' : 'desc';

        return Transaction::query()
            ->where('user_id', $this->recordId)
            ->with('user')
            ->when($search !== '', fn ($query) => $query->where('gateway_reference', 'ilike', "%{$search}%"))
            ->when($this->transactionsType !== '', fn ($query) => $query->where('transaction_type', $this->transactionsType))
            ->when($this->transactionsMethod !== '', fn ($query) => $query->where('method', $this->transactionsMethod))
            ->when($this->transactionsStatus !== '', fn ($query) => $query->where('status', $this->transactionsStatus))
            ->orderBy('created_at', $direction)
            ->paginate(10, pageName: 'transactionsPage');
    }

    /**
     * @return LengthAwarePaginator<int, Rating>
     */
    #[Computed]
    public function ratings(): LengthAwarePaginator
    {
        return Rating::query()
            ->where('ratee_id', $this->recordId)
            ->with(['rater', 'trip'])
            ->orderByDesc('created_at')
            ->paginate(10, pageName: 'ratingsPage');
    }

    /**
     * @return Collection<int, UserDevice>
     */
    #[Computed]
    public function devices(): Collection
    {
        return $this->rider()->devices()->orderByDesc('last_seen_at')->get();
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    #[Computed]
    public function tokens(): Collection
    {
        return $this->rider()->tokens()->orderByDesc('last_used_at')->get();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function updatingTripsSearch(): void
    {
        $this->resetPage('tripsPage');
    }

    public function updatingTripsType(): void
    {
        $this->resetPage('tripsPage');
    }

    public function updatingTripsStatus(): void
    {
        $this->resetPage('tripsPage');
    }

    public function sortTrips(string $column): void
    {
        if (! in_array($column, self::TRIP_SORT_COLUMNS, true)) {
            return;
        }

        if ($this->tripsSort === $column) {
            $this->tripsSortDirection = $this->tripsSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tripsSort = $column;
            $this->tripsSortDirection = 'asc';
        }

        $this->resetPage('tripsPage');
    }

    public function updatingTransactionsSearch(): void
    {
        $this->resetPage('transactionsPage');
    }

    public function updatingTransactionsType(): void
    {
        $this->resetPage('transactionsPage');
    }

    public function updatingTransactionsMethod(): void
    {
        $this->resetPage('transactionsPage');
    }

    public function updatingTransactionsStatus(): void
    {
        $this->resetPage('transactionsPage');
    }

    public function sortTransactions(string $column): void
    {
        if (! in_array($column, self::TRANSACTION_SORT_COLUMNS, true)) {
            return;
        }

        if ($this->transactionsSort === $column) {
            $this->transactionsSortDirection = $this->transactionsSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->transactionsSort = $column;
            $this->transactionsSortDirection = 'asc';
        }

        $this->resetPage('transactionsPage');
    }

    public function revokeDevice(int $deviceId): void
    {
        $this->rider()->devices()->where('id', $deviceId)->delete();

        Notification::make()->title('Device revoked')->success()->send();
    }

    public function revokeToken(int $tokenId): void
    {
        $this->rider()->tokens()->where('id', $tokenId)->delete();

        Notification::make()->title('Token revoked')->success()->send();
    }

    public function forceLogout(): void
    {
        $this->rider()->tokens()->delete();

        Notification::make()->title('Rider logged out of all devices')->success()->send();
    }

    public function suspendAccount(UserAccountService $userAccountService): void
    {
        $userAccountService->suspend($this->rider());

        Notification::make()->title('Account suspended')->success()->send();
    }

    public function reactivateAccount(UserAccountService $userAccountService): void
    {
        $userAccountService->unsuspend($this->rider());

        Notification::make()->title('Account reactivated')->success()->send();
    }

    public function banAccount(): void
    {
        $this->rider()->update(['status' => 'banned', 'allow_login' => false]);

        Notification::make()->title('Account banned')->danger()->send();
    }

    public function toggleAllowLogin(): void
    {
        $rider = $this->rider();

        $rider->update(['allow_login' => ! $rider->allow_login]);

        Notification::make()->title('Login access updated')->success()->send();
    }
}
