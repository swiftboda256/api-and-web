<?php

namespace App\Services\Wallet;

use App\Models\WithdrawCharge;
use Illuminate\Database\Eloquent\Collection;

readonly class WithdrawChargeService
{
    /**
     * @return Collection<int, WithdrawCharge>
     */
    public function list(): Collection
    {
        return WithdrawCharge::query()
            ->where('is_active', true)
            ->orderBy('min_amount')
            ->get();
    }

    public function calculateCharge(string $channel, ?string $accountIdentifier, float $amount): float
    {
        $charge = WithdrawCharge::query()
            ->where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $amount);
            })
            ->where(function ($query) use ($channel) {
                $query->whereNull('channel')
                    ->orWhere('channel', $channel);
            })
            ->orderBy('min_amount')
            ->first();

        if (! $charge) {
            return 0.0;
        }

        $provider = $channel === 'mobile_money' ? $this->detectProvider($accountIdentifier) : null;

        $operatorCharge = match ($provider) {
            'mtn' => (float) $charge->mtn_charge,
            'airtel' => (float) $charge->airtel_charge,
            default => 0.0,
        };

        return round((float) $charge->base_charge + $operatorCharge, 2);
    }

    public function detectProvider(?string $phoneNumber): ?string
    {
        if (! $phoneNumber) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phoneNumber) ?? '';

        if (str_starts_with($digits, '256')) {
            $digits = '0'.substr($digits, 3);
        } elseif (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        $prefix = substr($digits, 0, 3);

        return match (true) {
            in_array($prefix, ['077', '078', '076', '079', '031', '039'], true) => 'mtn',
            in_array($prefix, ['070', '075', '074', '020'], true) => 'airtel',
            default => null,
        };
    }
}
