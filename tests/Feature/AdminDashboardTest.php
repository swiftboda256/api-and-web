<?php

use App\Filament\Widgets\LatestTripsWidget;
use App\Filament\Widgets\LiveOperationsOverview;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\PaymentMethodsChart;
use App\Filament\Widgets\RevenueTrendChart;
use App\Filament\Widgets\TodayOverview;
use App\Filament\Widgets\TopRidersWidget;
use App\Filament\Widgets\TripsTrendChart;
use App\Filament\Widgets\TripTypesChart;
use App\Filament\Widgets\WelcomeWidget;
use App\Models\DeliveryDetails;
use App\Models\RiderProfile;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Midday, so "today" and "yesterday at this time" never straddle midnight.
    $this->travelTo(now()->setTime(12, 0));

    foreach (['customer', 'rider', 'admin', 'super_admin'] as $role) {
        Role::query()->create(['name' => $role, 'guard_name' => 'web']);
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function completedRideWithFare(float $fare, string $method = 'cash'): Trip
{
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'completed', 'completed_at' => now()->subHour()]);

    $passenger = TripPassenger::query()->create([
        'trip_id' => $trip->id,
        'customer_id' => User::factory()->create()->id,
        'seats_requested' => 1,
        'status' => 'dropped_off',
        'requested_at' => now()->subHours(2),
        'dropped_off_at' => now()->subHour(),
    ]);

    TripFareBreakdown::query()->create([
        'trip_id' => $trip->id,
        'passenger_id' => $passenger->id,
        'customer_id' => $passenger->customer_id,
        'base_fare' => 1000,
        'estimated_fare' => $fare,
        'final_fare' => $fare,
        'commission_amount' => 0,
        'rider_earning' => $fare,
        'total' => $fare,
        'currency_code' => 'UGX',
        'payment_method' => $method,
        'payment_status' => 'paid',
    ]);

    return $trip;
}

test('the dashboard and every widget render for a super admin', function () {
    $this->seed(PermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    completedRideWithFare(3000);

    $this->actingAs($admin)->get('/admin')->assertOk();

    foreach ([
        WelcomeWidget::class, LiveOperationsOverview::class, TodayOverview::class, NeedsAttentionWidget::class,
        TopRidersWidget::class, TripsTrendChart::class, RevenueTrendChart::class, TripTypesChart::class,
        PaymentMethodsChart::class, LatestTripsWidget::class,
    ] as $widget) {
        expect($widget::canView())->toBeTrue();
        Livewire::test($widget)->assertOk();
    }
});

test('widgets are hidden from an admin without their permission', function () {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole('admin'));

    expect(LiveOperationsOverview::canView())->toBeFalse()
        ->and(RevenueTrendChart::canView())->toBeFalse();
});

test('today counts dropped-off passenger and delivery fares, and completed commissions', function () {
    completedRideWithFare(3000);
    completedRideWithFare(2000, 'mobile_money');

    DeliveryDetails::factory()->create([
        'trip_id' => Trip::factory()->create(['type' => 'delivery', 'status' => 'completed', 'completed_at' => now()->subHour()])->id,
        'sender_id' => User::factory()->create()->id,
        'status' => 'dropped_off',
        'dropped_off_at' => now()->subHour(),
        'final_fare' => 5000,
        'currency_code' => 'UGX',
        'payment_method' => 'cash',
    ]);

    // Yesterday morning: counted in yesterday's comparison, not today.
    $this->travelTo(now()->subDay()->setTime(9, 0));
    completedRideWithFare(1000);
    $this->travelBack();
    $this->travelTo(now()->setTime(12, 0));

    $commission = fn (string $status, float $amount) => Transaction::query()->create([
        'user_id' => User::factory()->create()->id,
        'method' => 'cash',
        'direction' => 'credit',
        'transaction_type' => 'commission',
        'amount' => $amount,
        'currency_code' => 'UGX',
        'status' => $status,
    ]);
    $commission('completed', 450);
    $commission('pending', 999);

    $today = app(DashboardMetricsService::class)->today();

    expect($today['gross']['today'])->toBe(10000.0)
        ->and($today['gross']['yesterday'])->toBe(1000.0)
        ->and($today['commission']['today'])->toBe(450.0)
        ->and($today['completed']['today'])->toBe(3.0)
        ->and($today['gross']['trend'])->toHaveCount(7)
        ->and(app(DashboardMetricsService::class)->paymentMethods())->toBe(['cash' => 3, 'mobile_money' => 1]);
});

test('live counts riders and flags trips waiting over 5 minutes', function () {
    RiderProfile::factory()->create(['availability_status' => 'online']);
    RiderProfile::factory()->create(['availability_status' => 'on_trip']);
    RiderProfile::factory()->create(['availability_status' => 'offline']);

    Trip::factory()->create(['status' => 'searching', 'search_started_at' => now()->subMinutes(8), 'completed_at' => null]);
    Trip::factory()->create(['status' => 'searching', 'search_started_at' => now()->subMinute(), 'completed_at' => null]);
    Trip::factory()->create(['status' => 'in_progress', 'completed_at' => null]);

    expect(app(DashboardMetricsService::class)->live())->toMatchArray([
        'online' => 1,
        'on_trip' => 1,
        'active_trips' => 1,
        'searching' => 2,
        'searching_overdue' => 1,
    ]);
});
