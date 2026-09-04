<?php

namespace Database\Seeders;

use App\Models\Configuration;
use App\Models\Document;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Wallet;
use App\Models\Zone;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    private const array REQUIRED_DOCUMENT_TYPES = ['national_id', 'driving_license'];

    private const string TEST_WALLET_PIN = '12345';

    /**
     * A 1x1 transparent PNG, used as placeholder content for seeded rider documents.
     */
    private const string PLACEHOLDER_DOCUMENT_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function run(): void
    {
        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $zone = Zone::query()->firstOrFail();

        $motorcycleType = VehicleType::query()->where('code', 'motorcycle')->firstOrFail();
        $carType = VehicleType::query()->where('code', 'car')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            $this->createCustomer($zone, (string) Configuration::get('user_test_number'));
            $this->createRider($zone, $motorcycleType, (string) Configuration::get('rider_test_number'), 'Motorcycle', $systemUser);
            $this->createRider($zone, $carType, (string) Configuration::get('rider_test_number_car'), 'Car', $systemUser);
        } finally {
            if ($actingUser instanceof User) {
                Auth::setUser($actingUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    private function createCustomer(Zone $zone, string $phone): void
    {
        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'email' => 'test.customer@swiftboda.app',
                'login_type' => 'sms',
                'allow_login' => true,
                'status' => 'active',
                'profile_completed' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole('customer')) {
            $user->assignRole('customer');
        }

        $this->attachDevice($user, 'test-customer-device');
        $this->attachWallet($user, $zone);
    }

    private function createRider(Zone $zone, VehicleType $vehicleType, string $phone, string $label, User $systemUser): void
    {
        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'first_name' => 'Test',
                'last_name' => "Rider ({$label})",
                'email' => 'test.rider.'.strtolower($label).'@swiftboda.app',
                'login_type' => 'sms',
                'allow_login' => true,
                'status' => 'active',
                'profile_completed' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole('rider')) {
            $user->assignRole('rider');
        }

        $riderProfile = RiderProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'rider_ref' => 'RDR-TEST-'.strtoupper($vehicleType->code),
                'national_id_number' => 'TEST-NIN-'.strtoupper($vehicleType->code),
                'license_number' => 'TEST-DL-'.strtoupper($vehicleType->code),
                'license_expiry_at' => now()->addYears(2),
                'date_of_birth' => now()->subYears(30),
                'gender' => 'other',
                'kyc_status' => 'approved',
                'kyc_rejection_reason' => null,
                'approved_at' => now(),
                'approved_by' => $systemUser->id,
                'availability_status' => 'online',
                'current_location' => $this->zoneCenter($zone),
                'last_location_at' => now(),
                'home_zone_id' => $zone->id,
            ],
        );

        Vehicle::query()->updateOrCreate(
            ['rider_profile_id' => $riderProfile->id],
            [
                'vehicle_type_id' => $vehicleType->id,
                'make' => $vehicleType->code === 'motorcycle' ? 'Honda' : 'Toyota',
                'year' => 2022,
                'color' => 'White',
                'plate_number' => 'UTEST'.strtoupper(substr($vehicleType->code, 0, 3)),
                'registration_number' => 'REG-TEST-'.strtoupper($vehicleType->code),
                'insurance_expiry_at' => now()->addYear(),
                'status' => 'approved',
            ],
        );

        foreach (self::REQUIRED_DOCUMENT_TYPES as $documentType) {
            $this->storeApprovedDocument($riderProfile, $documentType, $systemUser);
        }

        $this->attachDevice($user, "test-rider-{$vehicleType->code}-device");
        $this->attachWallet($user, $zone);
    }

    private function storeApprovedDocument(RiderProfile $riderProfile, string $documentType, User $reviewer): void
    {
        $path = "documents/riders/{$riderProfile->id}/{$documentType}.png";

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, base64_decode(self::PLACEHOLDER_DOCUMENT_BASE64));
        }

        Document::query()->updateOrCreate(
            [
                'documentable_type' => RiderProfile::class,
                'documentable_id' => $riderProfile->id,
                'document_type' => $documentType,
            ],
            [
                'file_path' => $path,
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'expires_at' => now()->addYears(2),
            ],
        );
    }

    private function attachDevice(User $user, string $deviceId): void
    {
        UserDevice::query()->firstOrCreate(
            ['user_id' => $user->id, 'device_id' => $deviceId],
            [
                'device_type' => 'android',
                'fcm_token' => Str::random(163),
                'app_version' => '1.0.0',
                'ip_address' => '127.0.0.1',
                'last_seen_at' => now(),
                'active' => true,
            ],
        );
    }

    private function attachWallet(User $user, Zone $zone): void
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'currency_code' => $zone->currency_code,
                'status' => 'active',
                'pin' => self::TEST_WALLET_PIN,
            ],
        );

        if (! $wallet->pin) {
            $wallet->update(['pin' => self::TEST_WALLET_PIN]);
        }
    }

    private function zoneCenter(Zone $zone): Point
    {
        $points = $zone->boundary->getLineStrings()[0]->getPoints();

        $latitudes = array_map(fn (Point $point) => $point->getLatitude(), $points);
        $longitudes = array_map(fn (Point $point) => $point->getLongitude(), $points);

        return Point::makeGeodetic(
            (min($latitudes) + max($latitudes)) / 2,
            (min($longitudes) + max($longitudes)) / 2,
        );
    }
}
