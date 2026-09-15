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
        return $this->calculateChargeBreakdown($channel, $accountIdentifier, $amount)['total'];
    }

    /**
     * @return array{base_charge: float, operator_charge: float, total: float}
     */
    public function calculateChargeBreakdown(string $channel, ?string $accountIdentifier, float $amount): array
    {
        $charge = $this->findApplicableCharge($channel, $amount);

        if (! $charge) {
            return ['base_charge' => 0.0, 'operator_charge' => 0.0, 'total' => 0.0];
        }

        $provider = $channel === 'mobile_money' ? $this->detectProvider($accountIdentifier) : null;

        $operatorCharge = match ($provider) {
            'mtn' => (float) $charge->mtn_charge,
            'airtel' => (float) $charge->airtel_charge,
            default => 0.0,
        };

        $baseCharge = (float) $charge->base_charge;

        return [
            'base_charge' => $baseCharge,
            'operator_charge' => $operatorCharge,
            'total' => round($baseCharge + $operatorCharge, 2),
        ];
    }

    private function findApplicableCharge(string $channel, float $amount): ?WithdrawCharge
    {
        return WithdrawCharge::query()
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
