<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('country');
            $table->geometry('boundary', subtype: 'polygon', srid: 4326);
            $table->string('currency_code', 3);
            $table->string('timezone');
            $table->boolean('is_active')->default(true);
            $table->auditColumns();

            $table->spatialIndex('boundary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
