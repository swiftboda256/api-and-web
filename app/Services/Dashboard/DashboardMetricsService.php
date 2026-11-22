<?php

namespace App\Services\Dashboard;

use App\Models\DeliveryDetails;
use App\Models\Document;
use App\Models\RiderProfile;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\User;
use App\Models\UserDeleteRequest;
use App\Models\WithdrawalRequest;
use App\Services\Rider\KycQueue;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Numbers behind the admin dashboard widgets.
 *
 * Fares live on the bookings, not the trip: a passenger's fare is on its trip_fare_breakdowns row
 * (one per passenger), a delivery's on delivery_details. A booking counts as earned when it's
 * dropped off. Commission comes from the commission transactions (the ledger the Transactions page
 * uses), which covers both. Timestamps are Kampala wall time, the same as now().
 */
class DashboardMetricsService
{
    /** Trips with a rider assigned and not yet finished. */
    public const ACTIVE_TRIP_STATUSES = ['accepted', 'arrived', 'in_progress'];

    /** Trips still waiting for a rider. */
    public const UNASSIGNED_TRIP_STATUSES = ['requested', 'searching'];

    private const CACHE_SECONDS = 60;

    /**
     * @return array{online: int, on_trip: int, active_trips: int, searching: int, searching_overdue: int, departing_today: int}
     */
    public function live(): array
    {
        $now = now();

        $riders = RiderProfile::query()
            ->selectRaw("COUNT(*) FILTER (WHERE availability_status = 'online') AS online")
            ->selectRaw("COUNT(*) FILTER (WHERE availability_status = 'on_trip') AS on_trip")
            ->toBase()
            ->first();

        $trips = Trip::query()
            ->selectRaw('COUNT(*) FILTER (WHERE status IN (?, ?, ?)) AS active_trips', self::ACTIVE_TRIP_STATUSES)
            ->selectRaw('COUNT(*) FILTER (WHERE status IN (?, ?)) AS searching', self::UNASSIGNED_TRIP_STATUSES)
            ->selectRaw(
                'COUNT(*) FILTER (WHERE status IN (?, ?) AND COALESCE(search_started_at, requested_at, created_at) < ?) AS searching_overdue',
                [...self::UNASSIGNED_TRIP_STATUSES, $now->copy()->subMinutes(5)],
            )
            ->selectRaw(
                "COUNT(*) FILTER (WHERE status NOT IN ('completed', 'cancelled') AND departs_at BETWEEN ? AND ?) AS departing_today",
                [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            )
            ->toBase()
            ->first();

        return [
            'online' => (int) $riders->online,
            'on_trip' => (int) $riders->on_trip,
            'active_trips' => (int) $trips->active_trips,
            'searching' => (int) $trips->searching,
            'searching_overdue' => (int) $trips->searching_overdue,
            'departing_today' => (int) $trips->departing_today,
        ];
    }

    /**
     * Today so far against yesterday up to the same time, plus a 7-day daily series per metric
     * (oldest first, today last) for the sparklines.
     *
     * @return array<string, array{today: float, yesterday: float, trend: list<int|float>}>
     */
    public function today(): array
    {
        return Cache::remember('dashboard:today:'.now()->format('Y-m-d-H-i'), self::CACHE_SECONDS, function (): array {
            $now = CarbonImmutable::now();
            $from = $now->subDays(6)->startOfDay();

            $series = [
                'completed' => $this->dailyCounts(Trip::query()->where('status', 'completed'), 'completed_at', $from),
                'cancelled' => $this->dailyCounts(Trip::query()->where('status', 'cancelled'), 'cancelled_at', $from),
                'gross' => $this->dailyGross($from),
                'commission' => $this->dailyCommission($from),
                'customers' => $this->dailyCounts(User::query()->role('customer'), 'created_at', $from),
                'riders' => $this->dailyCounts(RiderProfile::query(), 'created_at', $from),
            ];

            $yesterdayFrom = $now->subDay()->startOfDay();
            $yesterdayTo = $now->subDay();

            $yesterday = [
                'completed' => Trip::query()->where('status', 'completed')->whereBetween('completed_at', [$yesterdayFrom, $yesterdayTo])->count(),
                'cancelled' => Trip::query()->where('status', 'cancelled')->whereBetween('cancelled_at', [$yesterdayFrom, $yesterdayTo])->count(),
                'gross' => $this->gross($yesterdayFrom, $yesterdayTo),
                'commission' => $this->commission($yesterdayFrom, $yesterdayTo),
                'customers' => User::query()->role('customer')->whereBetween('created_at', [$yesterdayFrom, $yesterdayTo])->count(),
                'riders' => RiderProfile::query()->whereBetween('created_at', [$yesterdayFrom, $yesterdayTo])->count(),
            ];

            $result = [];

            foreach ($series as $key => $trend) {
                $result[$key] = [
                    'today' => (float) end($trend),
                    'yesterday' => (float) $yesterday[$key],
                    'trend' => $trend,
                ];
            }

            return $result;
        });
    }

    /**
     * Counts that need an admin's action. Zero means nothing to do.
     *
     * @return array<string, int>
     */
    public function attention(): array
    {
        return Cache::remember('dashboard:attention', self::CACHE_SECONDS, fn (): array => [
            'kyc_review' => KycQueue::awaitingCount(),
            'documents' => Document::query()->where('status', 'pending')->count(),
            // Withdrawals are paid out automatically via Yo!; one still processing after 10 minutes is stuck
            // (money already left the rider's wallet, outcome unknown).
            'stuck_withdrawals' => WithdrawalRequest::query()->where('status', 'processing')->where('created_at', '<', now()->subMinutes(10))->count(),
            'stuck_transactions' => Transaction::query()->where('status', 'pending')->where('created_at', '<', now()->subMinutes(10))->count(),
            'failed_transactions' => Transaction::query()->where('status', 'failed')->where('created_at', '>=', now()->startOfDay())->count(),
            'open_tickets' => SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'urgent_tickets' => SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->whereIn('priority', ['high', 'urgent'])->count(),
            'delete_requests' => UserDeleteRequest::query()->whereIn('status', ['pending', 'processing'])->count(),
        ]);
    }

    /**
     * Completed vs cancelled trips per day.
     *
     * @return array{labels: list<string>, completed: list<int>, cancelled: list<int>}
     */
    public function tripsTrend(int $days = 30): array
    {
        return Cache::remember("dashboard:trips-trend:{$days}", self::CACHE_SECONDS, function () use ($days): array {
            $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

            return [
                'labels' => $this->dayLabels($from),
                'completed' => $this->dailyCounts(Trip::query()->where('status', 'completed'), 'completed_at', $from),
                'cancelled' => $this->dailyCounts(Trip::query()->where('status', 'cancelled'), 'cancelled_at', $from),
            ];
        });
    }

    /**
     * Gross fares vs commission per day.
     *
     * @return array{labels: list<string>, gross: list<float>, commission: list<float>}
     */
    public function revenueTrend(int $days = 30): array
    {
        return Cache::remember("dashboard:revenue-trend:{$days}", self::CACHE_SECONDS, function () use ($days): array {
            $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

            return [
                'labels' => $this->dayLabels($from),
                'gross' => $this->dailyGross($from),
                'commission' => $this->dailyCommission($from),
            ];
        });
    }

    /**
     * Trips created in the period, by type.
     *
     * @return array<string, int>
     */
    public function tripTypes(int $days = 30): array
    {
        return Cache::remember("dashboard:trip-types:{$days}", self::CACHE_SECONDS, fn (): array => Trip::query()
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('type, COUNT(*) AS total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type')
            ->map(fn ($total): int => (int) $total)
            ->all());
    }

    /**
     * Dropped-off bookings in the period, by payment method.
     *
     * @return array<string, int>
     */
    public function paymentMethods(int $days = 30): array
    {
        return Cache::remember("dashboard:payment-methods:{$days}", self::CACHE_SECONDS, function () use ($days): array {
            $from = now()->subDays($days - 1)->startOfDay();
            $totals = [];

            $passengers = $this->droppedOffFares($from)
                ->selectRaw("COALESCE(trip_fare_breakdowns.payment_method, 'unknown') AS method, COUNT(*) AS total")
                ->groupBy('method')
                ->pluck('total', 'method');

            $deliveries = DeliveryDetails::query()
                ->whereNotNull('dropped_off_at')
                ->where('dropped_off_at', '>=', $from)
                ->selectRaw("COALESCE(payment_method, 'unknown') AS method, COUNT(*) AS total")
                ->groupBy('method')
                ->pluck('total', 'method');

            foreach ([$passengers, $deliveries] as $rows) {
                foreach ($rows as $method => $total) {
                    $totals[$method] = ($totals[$method] ?? 0) + (int) $total;
                }
            }

            arsort($totals);

            return $totals;
        });
    }

    public function gross(CarbonInterface $from, CarbonInterface $to): float
    {
        $passengers = (float) $this->droppedOffFares($from, $to)->sum('trip_fare_breakdowns.final_fare');

        $deliveries = (float) DeliveryDetails::query()
            ->whereBetween('dropped_off_at', [$from, $to])
            ->sum('final_fare');

        return $passengers + $deliveries;
    }

    public function commission(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) $this->completedCommissions()
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');
    }

    /**
     * @return Builder<TripFareBreakdown>
     */
    private function droppedOffFares(CarbonInterface $from, ?CarbonInterface $to = null): Builder
    {
        return TripFareBreakdown::query()
            ->join('trip_passengers', 'trip_passengers.id', '=', 'trip_fare_breakdowns.passenger_id')
            ->whereNull('trip_passengers.deleted_at')
            ->where('trip_passengers.status', 'dropped_off')
            ->where('trip_passengers.dropped_off_at', '>=', $from)
            ->when($to, fn (Builder $query) => $query->where('trip_passengers.dropped_off_at', '<=', $to));
    }

    /**
     * @return Builder<Transaction>
     */
    private function completedCommissions(): Builder
    {
        return Transaction::query()
            ->where('transaction_type', 'commission')
            ->where('status', 'completed');
    }

    /**
     * @return list<float>
     */
    private function dailyGross(CarbonImmutable $from): array
    {
        $passengers = $this->droppedOffFares($from)
            ->selectRaw('DATE(trip_passengers.dropped_off_at) AS day, SUM(trip_fare_breakdowns.final_fare) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $deliveries = DeliveryDetails::query()
            ->where('dropped_off_at', '>=', $from)
            ->selectRaw('DATE(dropped_off_at) AS day, SUM(final_fare) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return array_map(
            fn (string $day): float => (float) ($passengers[$day] ?? 0) + (float) ($deliveries[$day] ?? 0),
            $this->days($from),
        );
    }

    /**
     * @return list<float>
     */
    private function dailyCommission(CarbonImmutable $from): array
    {
        $totals = $this->completedCommissions()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) AS day, SUM(amount) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return array_map(fn (string $day): float => (float) ($totals[$day] ?? 0), $this->days($from));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column  a timestamp column of the query's table
     * @return list<int>
     */
    private function dailyCounts(Builder $query, string $column, CarbonImmutable $from): array
    {
        $totals = $query
            ->where($column, '>=', $from)
            ->selectRaw("DATE({$column}) AS day, COUNT(*) AS total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return array_map(fn (string $day): int => (int) ($totals[$day] ?? 0), $this->days($from));
    }

    /**
     * Y-m-d for each day from $from through today.
     *
     * @return list<string>
     */
    private function days(CarbonImmutable $from): array
    {
        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo(now()); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    /**
     * @return list<string>
     */
    private function dayLabels(CarbonImmutable $from): array
    {
        return array_map(fn (string $day): string => CarbonImmutable::parse($day)->format('d M'), $this->days($from));
    }
}
