<?php

namespace App\Services\EmergencyContact;

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

readonly class EmergencyContactService
{
    /**
     * @return Collection<int, EmergencyContact>
     */
    public function list(User $user): Collection
    {
        return $user->emergencyContacts()
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): EmergencyContact
    {
        return DB::transaction(function () use ($user, $data): EmergencyContact {
            if ($data['is_primary'] ?? false) {
                $user->emergencyContacts()->update(['is_primary' => false]);
            }

            return $user->emergencyContacts()->create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'relationship' => $data['relationship'],
                'is_primary' => $data['is_primary'] ?? false,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, int $contactId, array $data): EmergencyContact
    {
        $contact = $this->findOrFail($user, $contactId);

        DB::transaction(function () use ($user, $contact, $data): void {
            if ($data['is_primary'] ?? false) {
                $user->emergencyContacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
            }

            $contact->fill($data);
            $contact->save();
        });

        return $contact->refresh();
    }

    public function delete(User $user, int $contactId): void
    {
        $this->findOrFail($user, $contactId)->delete();
    }

    private function findOrFail(User $user, int $contactId): EmergencyContact
    {
        return $user->emergencyContacts()->where('id', $contactId)->firstOrFail();
    }
}
