<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('settings');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        DB::table('contact_settings')->insert(['id' => 1, 'settings' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->uuid('request_id')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 40);
            $table->string('subject', 200);
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->timestamps();
            $table->index(['status', 'id']);
        });
        Schema::create('support_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id');
            $table->char('request_hash', 64);
            $table->string('subject', 200);
            $table->string('status', 20)->default('new');
            $table->unsignedBigInteger('last_message_id')->default(0);
            $table->unsignedBigInteger('client_read_through')->default(0);
            $table->unsignedBigInteger('staff_read_through')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'id']);
            $table->index(['status', 'id']);
            $table->index(['assigned_to', 'id']);
        });
        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_conversation_id')->constrained()->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->string('sender_type', 20);
            $table->uuid('client_message_id');
            $table->text('body');
            $table->timestamp('created_at');
            $table->unique(['sender_id', 'client_message_id']);
            $table->index(['support_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('contact_settings');
    }
};
