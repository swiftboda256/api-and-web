<?php

namespace Database\Seeders;

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
use Illuminate\Support\Str;

class RiderSeeder extends Seeder
{
    private const float MAX_DISTANCE_FROM_ZONE_KM = 50.0;

    private const float KM_PER_DEGREE = 111.0;

    private const int MOTORCYCLE_RIDERS_PER_ZONE = 3;

    private const int CAR_RIDERS_PER_ZONE = 2;

    public function run(): void
    {
        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $motorcycleType = VehicleType::query()->where('code', 'motorcycle')->firstOrFail();
        $carType = VehicleType::query()->where('code', 'car')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            $sequence = 0;

            Zone::query()->each(function (Zone $zone) use ($systemUser, $motorcycleType, $carType, &$sequence): void {
                $center = $this->zoneCenter($zone);

                $vehicleTypes = [
                    ...array_fill(0, self::MOTORCYCLE_RIDERS_PER_ZONE, $motorcycleType),
                    ...array_fill(0, self::CAR_RIDERS_PER_ZONE, $carType),
                ];

                foreach ($vehicleTypes as $vehicleType) {
                    $sequence++;
                    $this->createRider($zone, $center, $vehicleType, $sequence, $systemUser);
                }
            });
        } finally {
            if ($actingUser instanceof User) {
                Auth::setUser($actingUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    private function createRider(Zone $zone, Point $zoneCenter, VehicleType $vehicleType, int $sequence, User $systemUser): void
    {
        $user = User::factory()->create([
            'phone_verified_at' => now(),
        ]);

        if (! $user->hasRole('rider')) {
            $user->assignRole('rider');
        }

        $riderProfile = RiderProfile::query()->create([
            'user_id' => $user->id,
            'rider_ref' => 'RDR-'.strtoupper(Str::random(8)),
            'national_id_number' => fake()->unique()->bothify('CM########??'),
            'license_number' => fake()->unique()->bothify('DL#####??'),
            'license_expiry_at' => now()->addYears(2),
            'date_of_birth' => fake()->dateTimeBetween('-45 years', '-21 years'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'kyc_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $systemUser->id,
            'availability_status' => 'online',
            'current_location' => $this->randomPointWithin($zoneCenter, self::MAX_DISTANCE_FROM_ZONE_KM),
            'last_location_at' => now(),
            'home_zone_id' => $zone->id,
        ]);

        Vehicle::query()->create([
            'rider_profile_id' => $riderProfile->id,
            'vehicle_type_id' => $vehicleType->id,
            'make' => $vehicleType->code === 'motorcycle'
                ? fake()->randomElement(['Bajaj', 'TVS', 'Honda', 'Yamaha'])
                : fake()->randomElement(['Toyota', 'Nissan', 'Honda', 'Hyundai']),
            'year' => fake()->numberBetween(2015, 2024),
            'color' => fake()->safeColorName(),
            'plate_number' => sprintf('U%s%03d%s', strtoupper(Str::random(2)), $sequence, strtoupper(Str::random(1))),
            'registration_number' => 'REG-'.strtoupper(Str::random(10)),
            'insurance_expiry_at' => now()->addYear(),
            'status' => 'approved',
        ]);

        UserDevice::query()->create([
            'user_id' => $user->id,
            'device_type' => fake()->randomElement(['android', 'ios']),
            'device_id' => (string) Str::uuid(),
            'fcm_token' => Str::random(163),
            'app_version' => '1.0.0',
            'ip_address' => fake()->ipv4(),
            'last_seen_at' => now(),
            'active' => true,
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'balance' => 0,
            'currency_code' => $zone->currency_code,
            'status' => 'active',
        ]);
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

    private function randomPointWithin(Point $center, float $radiusKm): Point
    {
        $distanceKm = $radiusKm * sqrt(random_int(0, 10000) / 10000);
        $bearing = deg2rad(random_int(0, 359));

        $latitudeOffset = ($distanceKm * cos($bearing)) / self::KM_PER_DEGREE;
        $longitudeOffset = ($distanceKm * sin($bearing)) / (self::KM_PER_DEGREE * cos(deg2rad($center->getLatitude())));

        return Point::makeGeodetic(
            $center->getLatitude() + $latitudeOffset,
            $center->getLongitude() + $longitudeOffset,
        );
    }
}
