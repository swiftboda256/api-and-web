<?php

namespace App\Services\Rider;

use App\Models\Document;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

readonly class RiderProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->fill(Arr::only($data, ['first_name', 'last_name', 'other_name', 'email', 'avatar_url']));
            $user->profile_completed = filled($user->first_name) && filled($user->last_name);
            $user->save();

            $riderProfile = $user->riderProfile ?? RiderProfile::query()->create(['user_id' => $user->id]);

            $riderProfile->fill(Arr::only($data, [
                'national_id_number',
                'license_number',
                'license_expiry_at',
                'date_of_birth',
                'gender',
            ]));

            if (! empty($data['documents'])) {
                foreach ($data['documents'] as $document) {
                    $this->storeDocument($riderProfile, $document['type'], $document['file']);
                }

                $riderProfile->kyc_status = 'pending';
            }

            $riderProfile->save();

            return $user->setRelation('riderProfile', $riderProfile->fresh());
        });
    }

    private function storeDocument(RiderProfile $riderProfile, string $type, UploadedFile $file): void
    {
        $existing = Document::query()
            ->where('documentable_type', RiderProfile::class)
            ->where('documentable_id', $riderProfile->id)
            ->where('document_type', $type)
            ->first();

        if ($existing) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $path = $file->store("documents/riders/{$riderProfile->id}", 'public');

        Document::query()->updateOrCreate(
            [
                'documentable_type' => RiderProfile::class,
                'documentable_id' => $riderProfile->id,
                'document_type' => $type,
            ],
            [
                'file_path' => $path,
                'status' => 'pending',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );
    }
}
