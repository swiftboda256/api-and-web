<?php

namespace App\Services\Rider;

use App\Models\Document;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class RiderKycService
{
    private const array REQUIRED_DOCUMENT_TYPES = ['national_id', 'driving_license'];

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

            $this->recomputeKycStatus($riderProfile, $reviewer);

            return $document->refresh();
        });
    }

    /**
     * A rider's overall KYC status is derived from their required documents:
     * any rejection fails the whole review, otherwise it's only approved once
     * every required document has been individually approved.
     */
    private function recomputeKycStatus(RiderProfile $riderProfile, User $reviewer): void
    {
        $documents = $riderProfile->documents()
            ->whereIn('document_type', self::REQUIRED_DOCUMENT_TYPES)
            ->get();

        $rejected = $documents->first(fn (Document $document): bool => $document->status === 'rejected');

        if ($rejected) {
            $riderProfile->update([
                'kyc_status' => 'rejected',
                'kyc_rejection_reason' => $rejected->rejection_reason,
            ]);

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

            return;
        }

        $riderProfile->update([
            'kyc_status' => 'pending',
            'kyc_rejection_reason' => null,
        ]);
    }
}
