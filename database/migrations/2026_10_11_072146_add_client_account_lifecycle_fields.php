<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('profile_completed_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 64)->nullable();
            $table->json('notification_preferences')->nullable();
            $table->boolean('notifications_dirty')->default(false);
            $table->index(['notifications_dirty', 'status']);
            $table->unsignedBigInteger('notifications_version')->default(0);
            $table->unsignedBigInteger('notifications_published_version')->default(0);
        });
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('device_id')->nullable()->index();
            $table->timestamp('authenticated_at')->nullable();
        });
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->cascadeOnDelete();
        });
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->unsignedBigInteger('sequence')->default(0);
            $table->unsignedBigInteger('revision')->default(0);
            $table->index(['user_id', 'revision']);
            $table->string('category', 24)->default('transactional');
            $table->json('action')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->index(['user_id', 'archived_at', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'archived_at', 'read_at']);
            $table->dropIndex(['user_id', 'revision']);
            $table->dropColumn(['category', 'action', 'archived_at', 'sequence', 'revision']);
        });
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('personal_access_token_id');
        });
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex(['device_id']);
            $table->dropColumn(['device_id', 'authenticated_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['notifications_dirty', 'status']);
            $table->dropColumn(['notifications_dirty', 'profile_completed_at', 'terms_accepted_at', 'terms_version', 'notification_preferences', 'notifications_version', 'notifications_published_version']);
        });
    }
};
