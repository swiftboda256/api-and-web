<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Zone;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class ZoneSeeder extends Seeder
{
    private const float HALF_WIDTH_KM = 50.0;

    private const float KM_PER_DEGREE = 111.0;

    public function run(): void
    {
        $districts = [
            // Central
            ['name' => 'Kampala', 'city' => 'Kampala', 'latitude' => 0.3476, 'longitude' => 32.5825],
            ['name' => 'Wakiso', 'city' => 'Wakiso', 'latitude' => 0.4044, 'longitude' => 32.4593],
            ['name' => 'Mukono', 'city' => 'Mukono', 'latitude' => 0.3533, 'longitude' => 32.7553],
            ['name' => 'Mpigi', 'city' => 'Mpigi', 'latitude' => 0.2281, 'longitude' => 32.3306],
            ['name' => 'Luwero', 'city' => 'Luwero', 'latitude' => 0.8500, 'longitude' => 32.4667],
            ['name' => 'Masaka', 'city' => 'Masaka', 'latitude' => -0.3333, 'longitude' => 31.7333],
            // Eastern
            ['name' => 'Jinja', 'city' => 'Jinja', 'latitude' => 0.4244, 'longitude' => 33.2042],
            ['name' => 'Mbale', 'city' => 'Mbale', 'latitude' => 1.0827, 'longitude' => 34.1755],
            ['name' => 'Soroti', 'city' => 'Soroti', 'latitude' => 1.7147, 'longitude' => 33.6111],
            ['name' => 'Tororo', 'city' => 'Tororo', 'latitude' => 0.6928, 'longitude' => 34.1808],
            ['name' => 'Iganga', 'city' => 'Iganga', 'latitude' => 0.6081, 'longitude' => 33.4686],
            ['name' => 'Busia', 'city' => 'Busia', 'latitude' => 0.4608, 'longitude' => 34.0917],
            // Northern
            ['name' => 'Gulu', 'city' => 'Gulu', 'latitude' => 2.7746, 'longitude' => 32.2990],
            ['name' => 'Lira', 'city' => 'Lira', 'latitude' => 2.2350, 'longitude' => 32.9100],
            ['name' => 'Arua', 'city' => 'Arua', 'latitude' => 3.0333, 'longitude' => 30.9500],
            ['name' => 'Kitgum', 'city' => 'Kitgum', 'latitude' => 3.2783, 'longitude' => 32.8867],
            ['name' => 'Moyo', 'city' => 'Moyo', 'latitude' => 3.6547, 'longitude' => 31.7292],
            // Western
            ['name' => 'Mbarara', 'city' => 'Mbarara', 'latitude' => -0.6072, 'longitude' => 30.6545],
            ['name' => 'Fort Portal', 'city' => 'Fort Portal', 'latitude' => 0.6710, 'longitude' => 30.2748],
            ['name' => 'Kasese', 'city' => 'Kasese', 'latitude' => 0.1833, 'longitude' => 30.0833],
            ['name' => 'Hoima', 'city' => 'Hoima', 'latitude' => 1.4356, 'longitude' => 31.3556],
            ['name' => 'Bushenyi', 'city' => 'Bushenyi', 'latitude' => -0.5833, 'longitude' => 30.2167],
            ['name' => 'Kabale', 'city' => 'Kabale', 'latitude' => -1.2486, 'longitude' => 29.9897],
        ];

        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            foreach ($districts as $district) {
                Zone::query()->updateOrCreate(
                    ['name' => $district['name'], 'city' => $district['city']],
                    [
                        'country' => 'Uganda',
                        'boundary' => $this->boundingBox($district['latitude'], $district['longitude']),
                        'currency_code' => 'UGX',
                        'timezone' => 'Africa/Kampala',
                        'is_active' => true,
                    ],
                );
            }
        } finally {
            if ($actingUser instanceof User) {
                Auth::setUser($actingUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    private function boundingBox(float $latitude, float $longitude): Polygon
    {
        $halfWidthDegrees = self::HALF_WIDTH_KM / self::KM_PER_DEGREE;

        $south = $latitude - $halfWidthDegrees;
        $north = $latitude + $halfWidthDegrees;
        $west = $longitude - $halfWidthDegrees;
        $east = $longitude + $halfWidthDegrees;

        return Polygon::make([
            LineString::make([
                Point::makeGeodetic($south, $west),
                Point::makeGeodetic($south, $east),
                Point::makeGeodetic($north, $east),
                Point::makeGeodetic($north, $west),
                Point::makeGeodetic($south, $west),
            ]),
        ]);
    }
}
