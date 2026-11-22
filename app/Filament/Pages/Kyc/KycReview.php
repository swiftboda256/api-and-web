<?php

namespace App\Filament\Pages\Kyc;

use App\Filament\Pages\Riders\Concerns\ReviewsRiderKyc;
use App\Filament\Pages\Riders\RiderDetails;
use App\Models\Document;
use App\Models\User;
use App\Services\Rider\KycQueue;
use App\Services\Rider\RiderKycService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Review one rider's KYC: their details next to each required document (zoomable), plus the vehicle.
 * Access follows the queue page (View:KycReviews); actions need Review:Kyc / Delete:KycDocument.
 */
class KycReview extends Page
{
    use InteractsWithActions;
    use ReviewsRiderKyc;

    protected string $view = 'filament.pages.kyc.kyc-review';

    protected static ?string $slug = 'kyc-reviews/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public const array DOCUMENT_LABELS = [
        'national_id' => 'National ID',
        'driving_license' => 'Driving License',
    ];

    #[Locked]
    public int $recordId;

    public static function canAccess(): bool
    {
        return KycReviews::canAccess();
    }

    public function mount(int $record): void
    {
        $this->recordId = $record;

        // 404 now for an unknown rider rather than on the first action.
        $this->rider();
    }

    public function getTitle(): string
    {
        if (! isset($this->recordId)) {
            return 'KYC review';
        }

        $name = $this->rider()->name;

        return $name !== '' ? "KYC review · {$name}" : 'KYC review';
    }

    #[Computed]
    public function rider(): User
    {
        return User::query()
            ->whereHas('riderProfile')
            ->with(['riderProfile.vehicle.vehicleType', 'riderProfile.vehicle.vehicleModel', 'riderProfile.homeZone', 'riderProfile.approvedBy'])
            ->findOrFail($this->recordId);
    }

    /**
     * One entry per required document type, in a fixed order; null when not uploaded yet.
     *
     * @return array<string, Document|null>
     */
    public function requiredDocuments(): array
    {
        $documents = $this->documents();

        return collect(RiderKycService::REQUIRED_DOCUMENT_TYPES)
            ->mapWithKeys(fn (string $type): array => [$type => $documents->firstWhere('document_type', $type)])
            ->all();
    }

    /**
     * Previous/next by position in the awaiting queue (oldest first, same order as the queue page).
     * Once this rider has been decided they've left the queue: "next" is then whoever has waited
     * longest, and there's no "previous".
     *
     * @return array{position: int|null, total: int, previous: string|null, next: string|null}
     */
    #[Computed]
    public function queueNavigation(): array
    {
        /** @var list<int> $ids */
        $ids = KycQueue::withWaitingSince(KycQueue::query('awaiting')->select('users.id'))
            ->orderBy('kyc_waiting_since')
            ->orderBy('users.id')
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $index = array_search($this->recordId, $ids, true);
        $url = fn (?int $id): ?string => $id !== null ? self::getUrl(['record' => $id]) : null;

        if ($index === false) {
            return ['position' => null, 'total' => count($ids), 'previous' => null, 'next' => $url($ids[0] ?? null)];
        }

        return [
            'position' => $index + 1,
            'total' => count($ids),
            'previous' => $url($ids[$index - 1] ?? null),
            'next' => $url($ids[$index + 1] ?? null),
        ];
    }

    public function riderProfileUrl(): ?string
    {
        return RiderDetails::canAccess() ? RiderDetails::getUrl(['record' => $this->recordId]) : null;
    }

    public function isPdf(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    protected function refreshKycState(): void
    {
        unset($this->rider, $this->documents, $this->queueNavigation);
    }
}
