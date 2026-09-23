<?php

namespace Database\Factories;

use App\Models\Zone;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $latitude = fake()->randomFloat(4, -1, 3);
        $longitude = fake()->randomFloat(4, 30, 34);

        return [
            'name' => fake()->unique()->city(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'city' => fake()->city(),
            'country' => 'Uganda',
            'boundary' => $this->boundingBox($latitude, $longitude),
            'currency_code' => 'UGX',
            'timezone' => 'Africa/Kampala',
            'is_active' => true,
        ];
    }

    private function boundingBox(float $latitude, float $longitude): Polygon
    {
        $halfWidthDegrees = 50.0 / 111.0;

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
