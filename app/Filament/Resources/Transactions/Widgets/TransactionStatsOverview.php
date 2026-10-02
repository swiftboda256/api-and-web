<?php

namespace App\Filament\Resources\Transactions\Widgets;

use App\Models\Transaction;
use Carbon\CarbonInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransactionStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $now = now();

        $allTime = $this->totals();
        // Month-to-date vs the same days of last month, so a half-finished month
        // isn't compared against a full one.
        $thisMonth = $this->totals($now->copy()->startOfMonth(), $now);
        $lastMonth = $this->totals(
            $now->copy()->subMonthNoOverflow()->startOfMonth(),
            $now->copy()->subMonthNoOverflow(),
        );

        // [key, label, icon, whether an increase is good]
        $cards = [
            ['commissions', 'Commissions', Heroicon::OutlinedReceiptPercent, true],
            ['payouts', 'Payouts', Heroicon::OutlinedBanknotes, true],
            ['deposits', 'Wallet deposits', Heroicon::OutlinedArrowDownTray, true],
            ['withdrawals', 'Withdrawals', Heroicon::OutlinedArrowUpTray, true],
            ['successful', 'Successful transactions', Heroicon::OutlinedCheckCircle, true],
            ['failed', 'Failed transactions', Heroicon::OutlinedXCircle, false],
            ['pending', 'Pending transactions', Heroicon::OutlinedClock, false],
        ];

        return array_map(
            fn (array $card): Stat => $this->makeStat($card[1], $card[2], (float) $allTime->{$card[0]}, (float) $thisMonth->{$card[0]}, (float) $lastMonth->{$card[0]}, $card[3]),
            $cards,
        );
    }

    private function makeStat(string $label, Heroicon $icon, float $total, float $current, float $previous, bool $increaseIsGood): Stat
    {
        $stat = Stat::make($label, $this->money($total))->icon($icon);

        if ($previous == 0.0) {
            return $current == 0.0
                ? $stat->description('No change from last month')->descriptionIcon(Heroicon::Minus)->color('gray')
                : $stat->description('New this month')->descriptionIcon(Heroicon::ArrowTrendingUp)->color($increaseIsGood ? 'success' : 'danger');
        }

        $change = round((($current - $previous) / $previous) * 100, 1);

        if ($change == 0.0) {
            return $stat->description('0% from last month')->descriptionIcon(Heroicon::Minus)->color('gray');
        }

        $isIncrease = $change > 0;

        return $stat
            ->description(($isIncrease ? '+' : '').number_format($change, 1).'% from last month')
            ->descriptionIcon($isIncrease ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->color($isIncrease === $increaseIsGood ? 'success' : 'danger');
    }

    /**
     * Sum of `amount` for each card's slice, in one pass over the table.
     */
    private function totals(?CarbonInterface $from = null, ?CarbonInterface $to = null): object
    {
        return Transaction::query()
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'commission' AND status = 'completed' THEN amount END), 0) AS commissions")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'trip_payout' AND status = 'completed' THEN amount END), 0) AS payouts")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'topup' AND status = 'completed' THEN amount END), 0) AS deposits")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'withdrawal' AND status = 'completed' THEN amount END), 0) AS withdrawals")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN amount END), 0) AS successful")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'failed' THEN amount END), 0) AS failed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) AS pending")
            ->toBase()
            ->first();
    }

    private function money(float $amount): string
    {
        return 'UGX '.number_format($amount);
    }
}
