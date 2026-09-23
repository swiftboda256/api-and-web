<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * trip_fare_breakdowns becomes the source of truth for a passenger's fare/payment data --
     * trip_passengers keeps only lifecycle/identity fields. A ride_share trip can have
     * several passengers, so this table now holds one row per passenger (via passenger_id),
     * not one per trip -- the original unique constraint on trip_id alone would have blocked
     * a second passenger's row on the same trip, so it moves to passenger_id instead.
     *
     * commission_rate/commission_amount/rider_earning/total/final_fare(_before_rounding)
     * are only known once settled at drop-off, so they're nullable and left unset at
     * booking time -- their existing ->default(0) would otherwise misrepresent "not yet
     * settled" as "genuinely zero".
     */
    public function up(): void
    {
        Schema::table('trip_fare_breakdowns', function (Blueprint $table) {
            $table->dropUnique(['trip_id']);

            $table->foreignId('passenger_id')->nullable()->unique()->after('trip_id')->constrained('trip_passengers')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->after('passenger_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('discount_percentage', 5, 2)->nullable()->after('surge_amount');
            $table->decimal('estimated_fare', 10, 2)->nullable()->after('total');
            $table->decimal('estimated_fare_before_rounding', 10, 2)->nullable()->after('estimated_fare');
            $table->decimal('final_fare', 10, 2)->nullable()->after('estimated_fare_before_rounding');
            $table->decimal('final_fare_before_rounding', 10, 2)->nullable()->after('final_fare');

            $table->enum('payment_method', ['wallet', 'cash', 'mobile_money', 'card'])->nullable()->after('currency_code');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->after('payment_method');
        });

        // Historical trips backfilled from the pre-breakdown trips-level columns have no
        // base_fare of their own -- that was never tracked separately at trip level.
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN base_fare DROP NOT NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN total DROP NOT NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN commission_rate DROP NOT NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN commission_rate DROP DEFAULT');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN commission_amount DROP NOT NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN commission_amount DROP DEFAULT');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN rider_earning DROP NOT NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN rider_earning DROP DEFAULT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN rider_earning SET DEFAULT 0');
        DB::statement('UPDATE trip_fare_breakdowns SET rider_earning = 0 WHERE rider_earning IS NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN rider_earning SET NOT NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN commission_amount SET DEFAULT 0');
        DB::statement('UPDATE trip_fare_breakdowns SET commission_amount = 0 WHERE commission_amount IS NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN commission_amount SET NOT NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN commission_rate SET DEFAULT 0');
        DB::statement('UPDATE trip_fare_breakdowns SET commission_rate = 0 WHERE commission_rate IS NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN commission_rate SET NOT NULL');
        DB::statement('UPDATE trip_fare_breakdowns SET total = COALESCE(final_fare, estimated_fare, 0) WHERE total IS NULL');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN total SET NOT NULL');
        DB::statement('UPDATE trip_fare_breakdowns SET base_fare = COALESCE(base_fare, 0)');
        DB::statement('ALTER TABLE trip_fare_breakdowns ALTER COLUMN base_fare SET NOT NULL');

        Schema::table('trip_fare_breakdowns', function (Blueprint $table) {
            $table->dropColumn([
                'discount_percentage',
                'estimated_fare',
                'estimated_fare_before_rounding',
                'final_fare',
                'final_fare_before_rounding',
                'payment_method',
                'payment_status',
            ]);

            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('passenger_id');

            $table->unique('trip_id');
        });
    }
};
