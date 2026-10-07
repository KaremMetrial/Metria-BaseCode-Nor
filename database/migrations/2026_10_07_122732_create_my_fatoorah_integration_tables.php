<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_fatoorah_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('operation', 80);
            $table->string('status', 30);
            $table->string('idempotency_key', 128)->unique();
            $table->char('request_hash', 64);
            $table->longText('result')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('my_fatoorah_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 30);
            $table->string('reference', 128);
            $table->foreignId('customer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 50)->default('created');
            $table->boolean('needs_refresh')->default(false);
            $table->longText('snapshot');
            $table->text('last_event')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'reference']);
            $table->index(['customer_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_fatoorah_entities');
        Schema::dropIfExists('my_fatoorah_operations');
    }
};
