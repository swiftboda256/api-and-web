<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * The waitlist feature was removed: drop its table (sign-ups are lost) and its Shield permissions
 * (role assignments go with them through role_has_permissions' cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('waitlist_entries');

        DB::table('permissions')->where('name', 'like', '%:WaitlistEntry')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Recreates the empty table only; the sign-ups and permissions aren't restored.
     */
    public function down(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->auditColumns();
        });
    }
};
