<?php

use App\Filament\Resources\PricingRules\Pages\CreatePricingRule;
use App\Filament\Resources\PricingRules\Pages\EditPricingRule;
use App\Filament\Resources\PricingRules\Pages\ListPricingRules;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::query()->create(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $this->zones = Zone::factory()->count(4)->create();
    $this->vehicleType = VehicleType::factory()->create();
});

test('creating a rule for several zones creates one rule per zone', function () {
    $form = [
        'zone_ids' => $this->zones->take(3)->modelKeys(),
        'vehicle_type_id' => $this->vehicleType->id,
        'base_fare' => 1500,
        'per_km_rate' => 800,
        'per_minute_rate' => 0,
        'minimum_fare' => 2000,
        'currency_code' => 'UGX',
    ];

    Livewire::test(CreatePricingRule::class)->fillForm($form)->call('create')->assertHasNoFormErrors();

    expect(PricingRule::query()->where('base_fare', 1500)->orderBy('zone_id')->pluck('zone_id')->all())
        ->toBe($this->zones->take(3)->modelKeys());

    // A zone that already has a rule blocks the whole batch.
    Livewire::test(CreatePricingRule::class)
        ->fillForm([...$form, 'zone_ids' => $this->zones->slice(2)->modelKeys()])
        ->call('create')
        ->assertHasFormErrors(['zone_ids']);

    expect(PricingRule::query()->count())->toBe(3);
});

test('applying a rule to zones updates, restores and creates rules only for the same vehicle type', function () {
    [$sourceZone, $existingZone, $deletedZone, $newZone] = $this->zones->all();
    $rule = fn (Zone $zone, array $extra = []): PricingRule => PricingRule::factory()
        ->create(['zone_id' => $zone->id, 'vehicle_type_id' => $this->vehicleType->id, ...$extra]);

    $source = $rule($sourceZone, ['base_fare' => 2500]);
    $existing = $rule($existingZone, ['is_active' => false]);
    $deleted = $rule($deletedZone);
    $deleted->delete();
    $otherVehicle = PricingRule::factory()->create(['zone_id' => $existingZone->id]);

    Livewire::test(ListPricingRules::class)
        ->callAction(TestAction::make('applyToZones')->table($source), [
            'zone_ids' => [$existingZone->id, $deletedZone->id, $newZone->id],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Pricing copied');

    expect(PricingRule::query()->where('vehicle_type_id', $this->vehicleType->id)->where('base_fare', 2500)->count())->toBe(4)
        ->and($existing->refresh()->is_active)->toBeFalse()
        ->and($deleted->refresh()->trashed())->toBeFalse()
        ->and($deleted->deleted_by)->toBeNull()
        ->and($otherVehicle->refresh()->base_fare)->toBe('1000.00');
});

test('the edit page still saves and offers apply to zones', function () {
    $rule = PricingRule::factory()->create(['zone_id' => $this->zones[0]->id, 'vehicle_type_id' => $this->vehicleType->id]);

    Livewire::test(EditPricingRule::class, ['record' => $rule->id])
        ->fillForm(['base_fare' => 3000])
        ->call('save')
        ->assertHasNoFormErrors()
        ->callAction('applyToZones', ['zone_ids' => [$this->zones[1]->id]])
        ->assertHasNoFormErrors();

    expect(PricingRule::query()->where('zone_id', $this->zones[1]->id)->value('base_fare'))->toBe('3000.00');
});

test('apply to zones is hidden without the update permission', function () {
    $rule = PricingRule::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('ViewAny:PricingRule', 'Create:PricingRule');
    $this->actingAs($viewer);

    Livewire::test(ListPricingRules::class)
        ->assertActionHidden(TestAction::make('applyToZones')->table($rule));
});

test('bulk update changes only the filled-in prices on the selected rules', function () {
    $rules = PricingRule::factory()->count(2)->create(['cancellation_fee' => 300, 'vehicle_type_id' => $this->vehicleType->id]);
    $untouched = PricingRule::factory()->create();

    Livewire::test(ListPricingRules::class)
        ->assertCanSeeTableRecords([...$rules, $untouched])
        ->set('activeTab', $this->vehicleType->code)
        ->assertCanSeeTableRecords($rules)
        ->assertCanNotSeeTableRecords([$untouched])
        ->filterTable('zone', $rules->pluck('zone_id')->all())
        ->assertCanSeeTableRecords($rules)
        ->selectTableRecords($rules->modelKeys())
        ->callAction(TestAction::make('bulkUpdatePricing')->table()->bulk(), [
            'per_km_rate' => 900,
            'cancellation_fee' => 0,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Pricing updated');

    foreach ($rules as $rule) {
        $rule->refresh();

        expect($rule->per_km_rate)->toBe('900.00')
            ->and($rule->cancellation_fee)->toBe('0.00')
            ->and($rule->base_fare)->toBe('1000.00')
            ->and($rule->updated_by)->toBe(auth()->id());
    }

    expect($untouched->refresh()->per_km_rate)->toBe('750.00');
});

test('bulk update rejects an empty form and needs the update permission', function () {
    $rule = PricingRule::factory()->create();
    $bulk = TestAction::make('bulkUpdatePricing')->table()->bulk();

    Livewire::test(ListPricingRules::class)
        ->selectTableRecords([$rule->id])
        ->callAction($bulk, [])
        ->assertNotified('Fill in at least one value to change');

    expect($rule->refresh()->updated_by)->toBeNull();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('ViewAny:PricingRule');
    $this->actingAs($viewer);

    Livewire::test(ListPricingRules::class)->assertActionHidden($bulk);
});

test('the vehicle type tabs show rule counts without deleted rules', function () {
    $rules = PricingRule::factory()->count(3)->create(['vehicle_type_id' => $this->vehicleType->id]);
    $rules[0]->delete();
    PricingRule::factory()->create();

    $tabs = Livewire::test(ListPricingRules::class)->assertOk()->instance()->getCachedTabs();

    expect($tabs['all']->getBadge())->toEqual(3)
        ->and($tabs[$this->vehicleType->code]->getBadge())->toEqual(2);
});
