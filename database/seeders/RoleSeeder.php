<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['customer', 'rider', 'admin', 'system'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
