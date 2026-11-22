<?php

namespace App\Filament\Pages\Riders\Concerns;

use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use App\Services\Rider\RiderKycService;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * KYC review actions (documents + vehicle) shared by RiderDetails and KycReview. The host page must
 * define a `rider(): User` computed property for the rider being reviewed, and use InteractsWithActions.
 *
 * Approve/reject need the `Review:Kyc` custom permission and deleting a document needs
 * `Delete:KycDocument`; the views hide what the admin can't do and the actions re-check on the server.
 */
trait ReviewsRiderKyc
{
    public ?int $previewingDocumentId = null;

    /** What the reject modal is for: 'vehicle' or 'document:{id}'. */
    public ?string $rejecting = null;

    public string $rejectionReason = '';

    public bool $editingVehicle = false;

    public ?int $vehicleTypeId = null;

    public ?int $vehicleModelId = null;

    public string $plateNumber = '';

    public string $registrationNumber = '';

    public ?int $year = null;

    public string $color = '';

    public string $insuranceExpiryAt = '';

    /** Quick-pick rejection reasons offered in the reject modal. */
    public const array DOCUMENT_REJECTION_REASONS = [
        'The photo is blurry or unreadable.',
        'The document has expired.',
        "The number doesn't match the one submitted.",
        'This is the wrong document.',
        'Part of the document is cut off.',
    ];

    public const array VEHICLE_REJECTION_REASONS = [
        "The plate number doesn't match the photos.",
        'The vehicle photos are blurry or missing.',
        'The insurance has expired.',
        "The vehicle doesn't match the selected type or model.",
    ];

    public function canReviewKyc(): bool
    {
        return Filament::auth()->user()?->can('Review:Kyc') ?? false;
    }

    public function canDeleteKycDocuments(): bool
    {
        return Filament::auth()->user()?->can('Delete:KycDocument') ?? false;
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
     * @return Collection<int, VehicleType>
     */
    #[Computed]
    public function vehicleTypeOptions(): Collection
    {
        return VehicleType::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, VehicleModel>
     */
    #[Computed]
    public function vehicleModelOptions(): Collection
    {
        return VehicleModel::query()
            ->when($this->vehicleTypeId, fn ($query, $vehicleTypeId) => $query->where('vehicle_type_id', $vehicleTypeId))
            ->orderBy('make')
            ->orderBy('name')
            ->get();
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
        abort_unless($this->canReviewKyc(), 403);

        $rider = $this->rider();
        $riderKycService->approveDocument($this->findDocument($documentId), $rider->riderProfile, $this->reviewer());
        $this->refreshKycState();

        Notification::make()->title('Document approved')->success()->send();
    }

    public function rejectDocument(int $documentId): void
    {
        abort_unless($this->canReviewKyc(), 403);

        $this->rejecting = "document:{$documentId}";
        $this->rejectionReason = '';
    }

    public function deleteDocumentAction(): DeleteAction
    {
        return DeleteAction::make('deleteDocument')
            ->record(fn (array $arguments): Document => $this->findDocument((int) $arguments['documentId']))
            ->authorize(fn (): bool => $this->canDeleteKycDocuments())
            ->modalHeading('Delete document')
            ->modalDescription('The rider will have to upload this document again. This updates their KYC status.')
            ->successNotificationTitle('Document deleted')
            ->action(function (Document $record, RiderKycService $riderKycService): void {
                $riderKycService->deleteDocument($record, $this->rider()->riderProfile, $this->reviewer());

                if ($this->previewingDocumentId === $record->id) {
                    $this->previewingDocumentId = null;
                }

                $this->refreshKycState();
            });
    }

    public function approveVehicle(RiderKycService $riderKycService): void
    {
        abort_unless($this->canReviewKyc(), 403);

        $vehicle = $this->rider()->riderProfile->vehicle;

        if (! $vehicle) {
            return;
        }

        $riderKycService->approveVehicle($vehicle);
        $this->refreshKycState();

        Notification::make()->title('Vehicle approved')->success()->send();
    }

    public function rejectVehicle(): void
    {
        abort_unless($this->canReviewKyc(), 403);

        $this->rejecting = 'vehicle';
        $this->rejectionReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejecting = null;
        $this->rejectionReason = '';
        $this->resetErrorBag('rejectionReason');
    }

    public function confirmReject(RiderKycService $riderKycService): void
    {
        abort_unless($this->canReviewKyc(), 403);

        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:500'],
        ]);

        $rider = $this->rider();
        $reason = trim($this->rejectionReason);

        if ($this->rejecting === 'vehicle') {
            if ($vehicle = $rider->riderProfile->vehicle) {
                $riderKycService->rejectVehicle($vehicle, $reason);

                Notification::make()->title('Vehicle rejected')->danger()->send();
            }
        } elseif (is_string($this->rejecting) && str_starts_with($this->rejecting, 'document:')) {
            $document = $this->findDocument((int) str_replace('document:', '', $this->rejecting));

            $riderKycService->rejectDocument($document, $rider->riderProfile, $this->reviewer(), $reason);

            Notification::make()->title('Document rejected')->danger()->send();
        }

        $this->rejecting = null;
        $this->rejectionReason = '';
        $this->refreshKycState();
    }

    public function editVehicle(): void
    {
        abort_unless($this->canReviewKyc(), 403);

        $vehicle = $this->rider()->riderProfile->vehicle;

        if (! $vehicle) {
            return;
        }

        $this->vehicleTypeId = $vehicle->vehicle_type_id;
        $this->vehicleModelId = $vehicle->vehicle_model_id;
        $this->plateNumber = $vehicle->plate_number;
        $this->registrationNumber = (string) $vehicle->registration_number;
        $this->year = $vehicle->year;
        $this->color = (string) $vehicle->color;
        $this->insuranceExpiryAt = $vehicle->insurance_expiry_at?->format('Y-m-d') ?? '';
        $this->editingVehicle = true;
    }

    public function cancelEditVehicle(): void
    {
        $this->editingVehicle = false;
        $this->resetErrorBag();
    }

    public function saveVehicle(): void
    {
        abort_unless($this->canReviewKyc(), 403);

        $vehicle = $this->rider()->riderProfile->vehicle;

        if (! $vehicle) {
            return;
        }

        $data = $this->validate([
            'vehicleTypeId' => ['required', 'integer', 'exists:vehicle_types,id'],
            'vehicleModelId' => ['nullable', 'integer', 'exists:vehicle_models,id'],
            'plateNumber' => ['required', 'string', 'max:255', Rule::unique('vehicles', 'plate_number')->ignore($vehicle->id)],
            'registrationNumber' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:255'],
            'insuranceExpiryAt' => ['nullable', 'date'],
        ]);

        $vehicle->update([
            'vehicle_type_id' => $data['vehicleTypeId'],
            'vehicle_model_id' => $data['vehicleModelId'],
            'plate_number' => $data['plateNumber'],
            'registration_number' => $data['registrationNumber'] !== '' ? $data['registrationNumber'] : null,
            'year' => $data['year'],
            'color' => $data['color'] !== '' ? $data['color'] : null,
            'insurance_expiry_at' => $data['insuranceExpiryAt'] !== '' ? $data['insuranceExpiryAt'] : null,
        ]);

        Notification::make()->title('Vehicle updated')->success()->send();

        $this->editingVehicle = false;
        $this->refreshKycState();
    }

    /**
     * Reasons offered in the reject modal for what's currently being rejected.
     *
     * @return list<string>
     */
    public function rejectionReasonSuggestions(): array
    {
        return $this->rejecting === 'vehicle' ? self::VEHICLE_REJECTION_REASONS : self::DOCUMENT_REJECTION_REASONS;
    }

    private function findDocument(int $documentId): Document
    {
        return $this->rider()->riderProfile->documents()->findOrFail($documentId);
    }

    private function reviewer(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }

    /**
     * Drop memoised computed properties so the page re-renders with the new statuses.
     */
    protected function refreshKycState(): void
    {
        unset($this->rider, $this->documents);
    }

    /**
     * The vehicle being reviewed, with its photos.
     */
    public function reviewedVehicle(): ?Vehicle
    {
        return $this->rider()->riderProfile->vehicle?->loadMissing('images');
    }
}
