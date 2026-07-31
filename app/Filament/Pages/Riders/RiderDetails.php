<?php

namespace App\Filament\Pages\Riders;

use App\Models\Document;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Rider\RiderKycService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class RiderDetails extends Page
{
    use HasPageShield;
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

    public ?int $previewingDocumentId = null;

    public ?string $rejecting = null;

    public string $rejectionReason = '';

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
            ->with(['riderProfile.vehicle.vehicleType', 'riderProfile.homeZone', 'riderProfile.approvedBy', 'referredBy'])
            ->findOrFail($this->recordId);
    }

    /**
     * @return Collection<int, Document>
     */
    #[Computed]
    public function documents(): Collection
    {
        return $this->rider()->riderProfile->documents()
            ->with('reviewedBy')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, Trip>
     */
    #[Computed]
    public function trips(): LengthAwarePaginator
    {
        $search = trim($this->tripsSearch);
        $direction = $this->tripsSortDirection === 'asc' ? 'asc' : 'desc';

        $query = Trip::query()
            ->where('rider_id', $this->recordId)
            ->with(['customer', 'vehicleType'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = "%{$search}%";

                $query->where(function ($query) use ($like): void {
                    $query->where('trip_number', 'ilike', $like)
                        ->orWhereHas('customer', function ($query) use ($like): void {
                            $query->where('first_name', 'ilike', $like)
                                ->orWhere('last_name', 'ilike', $like)
                                ->orWhere('other_name', 'ilike', $like)
                                ->orWhere('phone', 'ilike', $like);
                        });
                });
            })
            ->when($this->tripsType !== '', fn ($query) => $query->where('type', $this->tripsType))
            ->when($this->tripsStatus !== '', fn ($query) => $query->where('status', $this->tripsStatus));

        if ($this->tripsSort === 'fare') {
            $query->orderByRaw("COALESCE(final_fare, estimated_fare) {$direction}");
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
            ->when($search !== '', fn ($query) => $query->where('gateway_reference', 'ilike', "%{$search}%"))
            ->when($this->transactionsType !== '', fn ($query) => $query->where('transaction_type', $this->transactionsType))
            ->when($this->transactionsMethod !== '', fn ($query) => $query->where('method', $this->transactionsMethod))
            ->when($this->transactionsStatus !== '', fn ($query) => $query->where('status', $this->transactionsStatus))
            ->orderBy('created_at', $direction)
            ->paginate(10, pageName: 'transactionsPage');
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

    public function previewDocument(int $documentId): void
    {
        $this->previewingDocumentId = $documentId;
    }

    public function closePreview(): void
    {
        $this->previewingDocumentId = null;
    }

    public function previewingDocument(): ?Document
    {
        if ($this->previewingDocumentId === null) {
            return null;
        }

        return $this->documents()->firstWhere('id', $this->previewingDocumentId);
    }

    public function documentUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public function approveDocument(int $documentId, RiderKycService $riderKycService): void
    {
        /** @var User $admin */
        $admin = Auth::user();

        $rider = $this->rider();
        $document = $this->findDocument($rider, $documentId);

        $riderKycService->approveDocument($document, $rider->riderProfile, $admin);

        Notification::make()->title('Document approved')->success()->send();
    }

    public function rejectDocument(int $documentId): void
    {
        $this->rejecting = "document:{$documentId}";
        $this->rejectionReason = '';
    }

    public function rejectVehicle(): void
    {
        $this->rejecting = 'vehicle';
        $this->rejectionReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejecting = null;
        $this->rejectionReason = '';
    }

    public function confirmReject(RiderKycService $riderKycService): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:500'],
        ]);

        $rider = $this->rider();

        if ($this->rejecting === 'vehicle') {
            $rider->riderProfile->vehicle?->update([
                'status' => 'rejected',
            ]);

            Notification::make()->title('Vehicle rejected')->danger()->send();
        } elseif (is_string($this->rejecting) && str_starts_with($this->rejecting, 'document:')) {
            /** @var User $admin */
            $admin = Auth::user();

            $document = $this->findDocument($rider, (int) str_replace('document:', '', $this->rejecting));

            $riderKycService->rejectDocument($document, $rider->riderProfile, $admin, $this->rejectionReason);

            Notification::make()->title('Document rejected')->danger()->send();
        }

        $this->rejecting = null;
        $this->rejectionReason = '';
    }

    public function approveVehicle(): void
    {
        $this->rider()->riderProfile->vehicle?->update(['status' => 'approved']);

        Notification::make()->title('Vehicle approved')->success()->send();
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

    public function suspendAccount(): void
    {
        $this->rider()->update(['status' => 'suspended']);

        Notification::make()->title('Account suspended')->success()->send();
    }

    public function reactivateAccount(): void
    {
        $this->rider()->update(['status' => 'active']);

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

    private function findDocument(User $rider, int $documentId): Document
    {
        return $rider->riderProfile->documents()->findOrFail($documentId);
    }
}
