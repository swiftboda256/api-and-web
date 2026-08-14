<?php

namespace App\Services\Rider;

use App\Models\Document;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\Vehicle;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

readonly class RiderProfileService
{
    private const array DOCUMENT_TYPES = ['national_id', 'driving_license'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->fill(Arr::only($data, ['first_name', 'last_name', 'other_name', 'email']));

            if (! empty($data['avatar'])) {
                $user->avatar_url = $this->storeAvatar($user, $data['avatar']);
            }

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

            if (! empty($data['latitude']) && ! empty($data['longitude'])) {
                $riderProfile->current_location = Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude']);
                $riderProfile->last_location_at = now();
            }

            if (! empty($data['availability_status'])) {
                if ($riderProfile->availability_status === 'on_trip') {
                    throw ValidationException::withMessages([
                        'availability_status' => 'You cannot change your availability while on an active trip.',
                    ]);
                }

                $riderProfile->availability_status = $data['availability_status'];
            }

            $documentUploaded = false;

            foreach (self::DOCUMENT_TYPES as $type) {
                if (! empty($data[$type])) {
                    $this->storeDocument($riderProfile, $type, $data[$type]);
                    $documentUploaded = true;
                }
            }

            if ($documentUploaded) {
                $riderProfile->kyc_status = 'pending';
            }

            $riderProfile->save();

            $vehicleFields = array_filter(
                Arr::only($data, ['vehicle_type_id', 'make', 'year', 'color', 'plate_number', 'registration_number', 'insurance_expiry_at']),
                fn ($value): bool => $value !== null,
            );

            if ($vehicleFields !== []) {
                $this->updateVehicle($riderProfile, $vehicleFields);
            }

            return $user->setRelation('riderProfile', $riderProfile->fresh(['vehicle']));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateVehicle(RiderProfile $riderProfile, array $data): void
    {
        $vehicle = $riderProfile->vehicle;

        if (! $vehicle) {
            if (empty($data['vehicle_type_id']) || empty($data['plate_number'])) {
                throw ValidationException::withMessages([
                    'vehicle_type_id' => 'Vehicle type and plate number are required to add a vehicle.',
                ]);
            }

            Vehicle::query()->create([
                ...$data,
                'rider_profile_id' => $riderProfile->id,
                'status' => 'pending',
            ]);

            return;
        }

        $vehicle->fill($data);
        $vehicle->status = 'pending';
        $vehicle->save();
    }

    private function storeAvatar(User $user, UploadedFile $file): string
    {
        Storage::disk('public')->deleteDirectory("avatars/{$user->id}");

        $path = $file->store("avatars/{$user->id}", 'public');

        if ($path === false) {
            throw ValidationException::withMessages(['avatar' => 'Failed to upload avatar image.']);
        }

        return Storage::disk('public')->url($path);
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
