<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('otp_challenges', function (Blueprint $table) {
   $table->id(); $table->char('scope',64)->unique(); $table->uuid('challenge_id')->unique();
   $table->string('phone',20); $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
   $table->string('actor_type',20); $table->string('purpose',30); $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
   $table->string('code_hash'); $table->unsignedSmallInteger('attempts')->default(0);
   $table->timestamp('expires_at')->index(); $table->timestamp('sent_at'); $table->timestamp('consumed_at')->nullable(); $table->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('otp_challenges'); }
};
