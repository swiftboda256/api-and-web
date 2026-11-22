<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Database\Seeder;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every Shield permission (resources, pages, widgets, and the custom permissions in
 * config/filament-shield.php) and grants them all to super_admin. Safe to re-run; it only adds.
 *
 * super_admin isn't gate-bypassed in this app (filament-shield super_admin.define_via_gate = false),
 * so it must hold every permission explicitly. Deploys don't run seeders: after adding a resource,
 * page, widget or custom permission, run `php artisan db:seed --class=PermissionSeeder` on each server.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $permissions = collect(FilamentShield::getEntitiesPermissions() ?? [])
            ->map(fn (string $name): PermissionContract => Permission::findOrCreate($name, $guard));

        Role::findOrCreate('super_admin', $guard)->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
