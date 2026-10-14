<?php

namespace App\Services\Device;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Collection;

readonly class DeviceService
{
    /**
     * @return Collection<int, UserDevice>
     */
    public function list(User $user): Collection
    {
        return $user->devices()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, int $deviceId, array $data, ?string $ipAddress = null): UserDevice
    {
        $device = $this->findOrFail($user, $deviceId);

        $device->fill($data);
        $device->ip_address = $ipAddress ?? $device->ip_address;
        $device->last_seen_at = now();
        $device->save();

        return $device;
    }

    public function delete(User $user, int $deviceId): void
    {
        $this->findOrFail($user, $deviceId)->delete();
    }

    private function findOrFail(User $user, int $deviceId): UserDevice
    {
        return $user->devices()->where('id', $deviceId)->firstOrFail();
    }
}
