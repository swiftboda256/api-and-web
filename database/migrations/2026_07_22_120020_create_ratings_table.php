<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('rater_role', ['customer', 'rider']);
            $table->unsignedTinyInteger('score');
            $table->string('comment')->nullable();
            $table->jsonb('tags')->nullable();
            $table->auditColumns();

            $table->unique(['trip_id', 'rater_id']);
            $table->index('ratee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
