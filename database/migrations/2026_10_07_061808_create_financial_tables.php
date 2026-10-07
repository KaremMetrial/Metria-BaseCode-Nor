<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('wallets',function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete(); $t->char('currency',3); $t->unsignedBigInteger('balance')->default(0); $t->boolean('is_locked')->default(false); $t->timestamps(); $t->unique(['user_id','currency']);
  });
  Schema::create('wallet_transactions',function(Blueprint $t){
   $t->id(); $t->foreignId('wallet_id')->constrained()->restrictOnDelete(); $t->string('direction',10); $t->unsignedBigInteger('amount'); $t->unsignedBigInteger('balance_after'); $t->string('idempotency_key',128); $t->char('request_hash',64); $t->string('reason',255); $t->timestamp('created_at'); $t->unique(['wallet_id','idempotency_key']); $t->index(['wallet_id','created_at']);
  });
  Schema::create('payments',function(Blueprint $t){
   $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('user_id')->constrained()->restrictOnDelete(); $t->foreignId('wallet_id')->constrained()->restrictOnDelete();
   $t->string('provider',40); $t->string('provider_reference')->nullable(); $t->text('client_secret')->nullable(); $t->unsignedBigInteger('amount'); $t->char('currency',3); $t->string('status',30); $t->unsignedBigInteger('refunded_amount')->default(0);
   $t->string('idempotency_key',128); $t->char('request_hash',64); $t->timestamps(); $t->unique(['user_id','idempotency_key']); $t->unique(['provider','provider_reference']); $t->index(['status','created_at']);
  });
  Schema::create('payment_refunds',function(Blueprint $t){
   $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('payment_id')->constrained()->restrictOnDelete(); $t->foreignId('actor_id')->constrained('users')->restrictOnDelete(); $t->unsignedBigInteger('amount'); $t->string('status',30); $t->string('provider_reference')->nullable(); $t->string('idempotency_key',128); $t->char('request_hash',64); $t->timestamps(); $t->unique(['payment_id','idempotency_key']); $t->index(['status','created_at']);
  });
  Schema::create('payment_webhook_events',function(Blueprint $t){
   $t->id(); $t->string('provider',40); $t->string('provider_event_id'); $t->char('payload_hash',64); $t->timestamp('created_at'); $t->unique(['provider','provider_event_id']);
  });
 }
 public function down(): void { foreach(['payment_webhook_events','payment_refunds','payments','wallet_transactions','wallets'] as $table) { Schema::dropIfExists($table); } }
};
