<?php

namespace App\Services\Address;

use App\Models\Address;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

readonly class AddressService
{
    /**
     * @return Collection<int, Address>
     */
    public function list(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            if ($data['is_default'] ?? false) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                'label' => $data['label'],
                'location' => Point::makeGeodetic($data['latitude'], $data['longitude']),
                'formatted_address' => $data['formatted_address'] ?? null,
                'place_id' => $data['place_id'] ?? null,
                'is_default' => $data['is_default'] ?? false,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, int $addressId, array $data): Address
    {
        $address = $this->findOrFail($user, $addressId);

        DB::transaction(function () use ($user, $address, $data): void {
            if ($data['is_default'] ?? false) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            if (isset($data['latitude'], $data['longitude'])) {
                $address->location = Point::makeGeodetic($data['latitude'], $data['longitude']);
            }

            $address->fill(collect($data)->except(['latitude', 'longitude'])->all());
            $address->save();
        });

        return $address->refresh();
    }

    public function delete(User $user, int $addressId): void
    {
        $this->findOrFail($user, $addressId)->delete();
    }

    private function findOrFail(User $user, int $addressId): Address
    {
        return $user->addresses()->where('id', $addressId)->firstOrFail();
    }
}
