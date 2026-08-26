<?php

namespace Database\Seeders;

use App\Models\TripCancellationReason;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class TripCancellationReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['label' => 'Driver is taking too long', 'applies_to' => 'customer'],
            ['label' => 'Changed my mind', 'applies_to' => 'customer'],
            ['label' => 'Wrong pickup location', 'applies_to' => 'customer'],
            ['label' => 'Found another ride', 'applies_to' => 'customer'],
            ['label' => 'Price too high', 'applies_to' => 'customer'],
            ['label' => 'Customer is not reachable', 'applies_to' => 'rider'],
            ['label' => 'Customer requested cancellation', 'applies_to' => 'rider'],
            ['label' => 'Unsafe or inaccessible pickup location', 'applies_to' => 'rider'],
            ['label' => 'Vehicle breakdown', 'applies_to' => 'rider'],
            ['label' => 'Trip is taking too long to start', 'applies_to' => 'both'],
            ['label' => 'Other', 'applies_to' => 'both'],
        ];

        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            foreach ($reasons as $reason) {
                TripCancellationReason::query()->firstOrCreate(
                    ['label' => $reason['label']],
                    [
                        'applies_to' => $reason['applies_to'],
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
}
