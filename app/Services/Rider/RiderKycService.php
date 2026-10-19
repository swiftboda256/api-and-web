<?php

namespace App\Services\Rider;

use App\Models\Document;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class RiderKycService
{
    private const array REQUIRED_DOCUMENT_TYPES = ['national_id', 'driving_license'];

    public function __construct(
        private FcmGateway $push,
    ) {}

    public function approveDocument(Document $document, RiderProfile $riderProfile, User $reviewer): Document
    {
        return DB::transaction(function () use ($document, $riderProfile, $reviewer): Document {
            $document->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->recomputeKycStatus($riderProfile, $reviewer);

            return $document->refresh();
        });
    }

    public function rejectDocument(Document $document, RiderProfile $riderProfile, User $reviewer, string $reason): Document
    {
        return DB::transaction(function () use ($document, $riderProfile, $reviewer, $reason): Document {
            $document->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->push->sendToUser($riderProfile->user, new PushMessage(
                type: 'document_rejected',
                id: $document->id,
                title: 'Document rejected',
                body: Str::headline($document->document_type)." was rejected: {$reason}",
                data: ['document_id' => $document->id, 'document_type' => $document->document_type],
            ));

            $this->recomputeKycStatus($riderProfile, $reviewer);

            return $document->refresh();
        });
    }

    /**
     * Soft-deletes the document only. The underlying file is kept on disk until
     * a force-delete is implemented for documents.
     */
    public function deleteDocument(Document $document, RiderProfile $riderProfile, User $reviewer): void
    {
        DB::transaction(function () use ($document, $riderProfile, $reviewer): void {
            $document->delete();

            $this->recomputeKycStatus($riderProfile, $reviewer);
        });
    }

    /**
     * A rider's overall KYC status is derived from their required documents:
     * any rejection fails the whole review, otherwise it's only approved once
     * every required document has been individually approved.
     */
    private function recomputeKycStatus(RiderProfile $riderProfile, User $reviewer): void
    {
        $previousStatus = $riderProfile->kyc_status;

        $documents = $riderProfile->documents()
            ->whereIn('document_type', self::REQUIRED_DOCUMENT_TYPES)
            ->get();

        $rejected = $documents->first(fn (Document $document): bool => $document->status === 'rejected');

        if ($rejected) {
            $riderProfile->update([
                'kyc_status' => 'rejected',
                'kyc_rejection_reason' => $rejected->rejection_reason,
            ]);

            $this->notifyKycStatusChanged($riderProfile, $previousStatus, 'rejected', $rejected->rejection_reason);

            return;
        }

        $allApproved = collect(self::REQUIRED_DOCUMENT_TYPES)->every(
            fn (string $type): bool => $documents->firstWhere('document_type', $type)?->status === 'approved'
        );

        if ($allApproved) {
            $riderProfile->update([
                'kyc_status' => 'approved',
                'kyc_rejection_reason' => null,
                'approved_at' => now(),
                'approved_by' => $reviewer->id,
            ]);

            $this->notifyKycStatusChanged($riderProfile, $previousStatus, 'approved');

            return;
        }

        $riderProfile->update([
            'kyc_status' => 'pending',
            'kyc_rejection_reason' => null,
        ]);
    }

    /**
     * Only pushes on an actual transition into approved/rejected, so re-reviewing an
     * already-decided rider (e.g. deleting one more document) doesn't re-fire it.
     */
    private function notifyKycStatusChanged(RiderProfile $riderProfile, ?string $previousStatus, string $newStatus, ?string $reason = null): void
    {
        if ($previousStatus === $newStatus) {
            return;
        }

        $this->push->sendToUser($riderProfile->user, new PushMessage(
            type: $newStatus === 'approved' ? 'kyc_approved' : 'kyc_rejected',
            id: $riderProfile->id,
            title: $newStatus === 'approved' ? 'KYC approved' : 'KYC rejected',
            body: $newStatus === 'approved'
                ? 'Your documents have been verified. You can now accept rides.'
                : ($reason ?? 'Your KYC review was rejected.'),
            data: ['kyc_status' => $newStatus],
        ));
    }
}
