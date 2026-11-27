<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('channels');
            $table->json('target_roles')->nullable();
            $table->json('target_user_ids')->nullable();
            $table->string('status')->default('queued'); // queued | sending | completed
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedSmallInteger('sms_segments')->default(0);
            $table->unsignedInteger('sms_unit_cost')->default(0);
            $table->unsignedBigInteger('sms_estimated_cost')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('communication_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel'); // push | email | sms
            $table->string('destination')->nullable();
            $table->string('status')->default('pending'); // pending | sent | failed
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['communication_id', 'channel', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_deliveries');
        Schema::dropIfExists('communications');
    }
};
