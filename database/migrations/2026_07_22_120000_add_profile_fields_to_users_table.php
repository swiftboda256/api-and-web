<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('name');
            $table->string('last_name')->after('first_name');
            $table->string('other_name')->nullable()->after('last_name');
            $table->dropColumn('name');
            $table->index('first_name');
            $table->index('last_name');
            $table->index('other_name');
            $table->string('phone')->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->enum('login_type', ['sms', 'email', 'password'])->default('phone')->after('phone_verified_at');
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active')->after('login_type');
            $table->string('avatar_url')->nullable()->after('status');
            $table->decimal('rating_avg', 3, 2)->default(0)->after('avatar_url');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            $table->string('referral_code')->unique()->nullable()->after('rating_count');
            $table->foreignId('referred_by')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('referred_by');
            $table->text('two_factor_secret')->nullable()->after('last_login_at');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->string('password')->nullable()->change();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn([
                'phone',
                'phone_verified_at',
                'login_type',
                'status',
                'avatar_url',
                'rating_avg',
                'rating_count',
                'referral_code',
                'last_login_at',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
            $table->string('password')->nullable(false)->change();
            $table->string('name')->after('id');
            $table->dropColumn(['first_name', 'last_name', 'other_name']);
        });
    }
};
