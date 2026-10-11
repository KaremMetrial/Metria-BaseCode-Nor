<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('referral_claim_expires_at')->nullable();
        });
        Schema::create('referral_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code', 16)->unique();
            $table->timestamps();
        });
        Schema::create('share_links', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('referral_code_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('target_type', 20);
            $table->string('target_value', 100)->default('');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'target_type', 'created_at']);
        });
        Schema::create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('referrer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['referrer_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('share_links');
        Schema::dropIfExists('referral_codes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('referral_claim_expires_at'));
    }
};
