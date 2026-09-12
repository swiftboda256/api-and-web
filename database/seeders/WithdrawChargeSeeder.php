<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WithdrawCharge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class WithdrawChargeSeeder extends Seeder
{
    /**
     * base_charge is Yo! Payments' published per-transaction fee, applied to
     * every mobile-money withdrawal. mtn_charge/airtel_charge are the
     * MTN/Airtel bulk-payment operator fees added on top, based on which
     * network the withdrawal's phone number belongs to. The operators don't
     * publish bulk-payment API tariffs, only the range Yo quotes (MTN: UGX
     * 300-1,200, Airtel: UGX 300-1,000), so these are estimated within that
     * range — confirm the exact figures with the Yo account manager before
     * relying on them in production.
     */
    private const array CHARGES = [
        ['channel' => 'mobile_money', 'min_amount' => 500, 'max_amount' => 60_000, 'base_charge' => 300, 'mtn_charge' => 300, 'airtel_charge' => 300],
        ['channel' => 'mobile_money', 'min_amount' => 60_001, 'max_amount' => 500_000, 'base_charge' => 500, 'mtn_charge' => 600, 'airtel_charge' => 600],
        ['channel' => 'mobile_money', 'min_amount' => 500_001, 'max_amount' => 1_000_000, 'base_charge' => 800, 'mtn_charge' => 900, 'airtel_charge' => 800],
        ['channel' => 'mobile_money', 'min_amount' => 1_000_001, 'max_amount' => null, 'base_charge' => 1_000, 'mtn_charge' => 1_200, 'airtel_charge' => 1_000],
    ];

    public function run(): void
    {
        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            foreach (self::CHARGES as $charge) {
                WithdrawCharge::query()->firstOrCreate(
                    [
                        'channel' => $charge['channel'],
                        'min_amount' => $charge['min_amount'],
                    ],
                    [
                        'max_amount' => $charge['max_amount'],
                        'base_charge' => $charge['base_charge'],
                        'mtn_charge' => $charge['mtn_charge'],
                        'airtel_charge' => $charge['airtel_charge'],
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
