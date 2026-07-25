<?php

namespace Database\Seeders;

use App\Models\Zone;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    private const float HALF_WIDTH_DEGREES = 0.05;

    public function run(): void
    {
        $cities = [
            ['name' => 'Kampala', 'city' => 'Kampala', 'latitude' => 0.3476, 'longitude' => 32.5825],
            ['name' => 'Arua', 'city' => 'Arua', 'latitude' => 3.0333, 'longitude' => 30.9500],
            ['name' => 'Mbarara', 'city' => 'Mbarara', 'latitude' => -0.6072, 'longitude' => 30.6545],
            ['name' => 'Gulu', 'city' => 'Gulu', 'latitude' => 2.7746, 'longitude' => 32.2990],
            ['name' => 'Jinja', 'city' => 'Jinja', 'latitude' => 0.4244, 'longitude' => 33.2042],
        ];

        foreach ($cities as $city) {
            Zone::query()->firstOrCreate(
                ['name' => $city['name'], 'city' => $city['city']],
                [
                    'country' => 'Uganda',
                    'boundary' => $this->boundingBox($city['latitude'], $city['longitude']),
                    'currency_code' => 'UGX',
                    'timezone' => 'Africa/Kampala',
                    'is_active' => true,
                ],
            );
        }
    }

    private function boundingBox(float $latitude, float $longitude): Polygon
    {
        $south = $latitude - self::HALF_WIDTH_DEGREES;
        $north = $latitude + self::HALF_WIDTH_DEGREES;
        $west = $longitude - self::HALF_WIDTH_DEGREES;
        $east = $longitude + self::HALF_WIDTH_DEGREES;

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
