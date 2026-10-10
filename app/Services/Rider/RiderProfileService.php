<?php

namespace App\Services\Rider;

use App\Models\Document;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleImage;
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
     * KYC detail fields and the document that backs each one. Changing a field
     * sends its document back for review.
     */
    private const array KYC_FIELD_DOCUMENTS = [
        'national_id_number' => 'national_id',
        'license_number' => 'driving_license',
        'license_expiry_at' => 'driving_license',
    ];

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
                'home_zone_id',
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

            $kycDetailsChanged = $this->resetDocumentsForChangedKycDetails($riderProfile);

            $documentUploaded = false;

            foreach (self::DOCUMENT_TYPES as $type) {
                if (! empty($data[$type])) {
                    $this->storeDocument($riderProfile, $type, $data[$type]);
                    $documentUploaded = true;
                }
            }

            if ($documentUploaded || $kycDetailsChanged) {
                $riderProfile->kyc_status = 'pending';
            }

            $riderProfile->save();

            $vehicleFields = array_filter(
                Arr::only($data, ['vehicle_type_id', 'vehicle_model_id', 'year', 'color', 'plate_number', 'registration_number', 'insurance_expiry_at']),
                fn ($value): bool => $value !== null,
            );

            $vehicleImages = $data['vehicle_images'] ?? [];

            if ($vehicleFields !== [] || $vehicleImages !== []) {
                $this->updateVehicle($riderProfile, $vehicleFields, $vehicleImages);
            }

            return $user->setRelation('riderProfile', $riderProfile->fresh(['vehicle.images']));
        });
    }

    public function generateRiderRef(int $riderProfileId, string $districtCode): string
    {
        return strtoupper($districtCode).str_pad((string) $riderProfileId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $images
     */
    private function updateVehicle(RiderProfile $riderProfile, array $data, array $images = []): void
    {
        $vehicle = $riderProfile->vehicle;

        if (! $vehicle) {
            if (empty($data['vehicle_type_id']) || empty($data['plate_number'])) {
                throw ValidationException::withMessages([
                    'vehicle_type_id' => 'Vehicle type and plate number are required to add a vehicle.',
                ]);
            }

            $vehicle = Vehicle::query()->create([
                ...$data,
                'rider_profile_id' => $riderProfile->id,
                'status' => 'pending',
            ]);
        } else {
            $vehicle->fill($data);
            $vehicle->status = 'pending';
            $vehicle->save();
        }

        foreach ($images as $image) {
            $this->storeVehicleImage($vehicle, $image);
        }
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

    /**
     * KYC status is derived from document statuses (RiderKycService), so the
     * documents behind changed details are reset to pending as well, otherwise
     * the rider would sit in pending with nothing left for an admin to review.
     */
    private function resetDocumentsForChangedKycDetails(RiderProfile $riderProfile): bool
    {
        $documentTypes = collect(self::KYC_FIELD_DOCUMENTS)
            ->filter(fn (string $documentType, string $field): bool => $riderProfile->isDirty($field))
            ->unique()
            ->values();

        if ($documentTypes->isEmpty()) {
            return false;
        }

        $riderProfile->documents()
            ->whereIn('document_type', $documentTypes->all())
            ->get()
            ->each(fn (Document $document) => $document->update([
                'status' => 'pending',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]));

        return true;
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

    private function storeVehicleImage(Vehicle $vehicle, UploadedFile $file): void
    {
        $path = $file->store("vehicles/{$vehicle->id}", 'public');

        if ($path === false) {
            throw ValidationException::withMessages(['vehicle_images' => 'Failed to upload one or more vehicle images.']);
        }

        VehicleImage::query()->create([
            'vehicle_id' => $vehicle->id,
            'file_path' => $path,
        ]);
    }
}
