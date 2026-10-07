<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The canonical users table.
 *
 * Greenfield baseline, so the full shape lives here rather than accumulating
 * a chain of ALTER migrations before anything has shipped.
 *
 * Design notes:
 *  - One table for every actor (admin/vendor/client). `type` is the
 *    discriminator, so adding an actor is an enum case, not a new table.
 *  - `type` and `status` are NOT NULL with no default on purpose: every insert
 *    must state them explicitly, so a mis-typed account cannot be created by
 *    omission.
 *  - `phone` stores canonical E.164 only. UNIQUE(phone) is the real guard
 *    against duplicate accounts; an application-level exists() check is only a
 *    nicety and is racy on its own.
 *  - UNIQUE(phone) plus soft deletes means a deleted user's number stays
 *    reserved, so re-registration must restore that row (see Issue 4).
 *    UNIQUE(phone, deleted_at) is deliberately NOT used: MySQL treats NULLs as
 *    distinct, so it would not enforce uniqueness at all for live rows.
 *  - `phone_country_id` is a plain indexed column for now; the foreign key to
 *    `countries` is added in Phase 2, when that table exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Canonical E.164, e.g. "+201012345678" (max +15 digits, 20 is roomy).
            $table->string('phone', 20)->nullable()->unique();
            $table->unsignedBigInteger('phone_country_id')->nullable()->index();
            $table->timestamp('phone_verified_at')->nullable();

            // Nullable because phone/OTP actors never set a password.
            $table->string('password')->nullable();

            $table->string('type', 20);
            $table->string('status', 20);

            $table->string('locale', 5)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('avatar_path')->nullable();

            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // The dominant admin filter: "active vendors", "blocked clients".
            $table->index(['type', 'status']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
