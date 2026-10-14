<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * value_type: the data type of the value (bool, text or number), which picks the admin form input.
     * guard: where the configuration applies: global (all apps), user_app or rider_app.
     */
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('value_type')->default('text')->after('value');
            $table->string('guard')->default('global')->after('value_type');
        });

        // Values saved through the old text-only form are JSON strings ("true", "3"). Convert
        // them to real booleans/numbers and type every row from what it holds. The test phone
        // numbers are digits but are text.
        DB::statement(<<<'SQL'
            UPDATE configurations SET value = to_jsonb(value #>> '{}' = 'true')
            WHERE jsonb_typeof(value) = 'string' AND value #>> '{}' IN ('true', 'false')
        SQL);

        DB::statement(<<<'SQL'
            UPDATE configurations SET value = to_jsonb((value #>> '{}')::numeric)
            WHERE jsonb_typeof(value) = 'string' AND value #>> '{}' ~ '^-?[0-9]+(\.[0-9]+)?$' AND key NOT LIKE '%test_number%'
        SQL);

        DB::statement(<<<'SQL'
            UPDATE configurations SET value_type = CASE jsonb_typeof(value)
                WHEN 'boolean' THEN 'bool'
                WHEN 'number' THEN 'number'
                ELSE 'text'
            END
        SQL);

        DB::table('configurations')->where('key', 'user_test_number')->update(['guard' => 'user_app']);
        DB::table('configurations')->whereIn('key', ['rider_test_number', 'rider_test_number_car'])->update(['guard' => 'rider_app']);
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['value_type', 'guard']);
        });
    }
};
