<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['image', 'video', 'audio', 'document', 'other'])->default('other');
            $table->string('url');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_attachments');
    }
};
