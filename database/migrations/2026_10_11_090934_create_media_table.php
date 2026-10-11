<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 20);
            $table->string('status', 20);
            $table->string('disk', 50);
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('inspection', 30)->nullable();
            $table->foreignId('page_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('support_message_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('avatar_media_id')->nullable()->constrained('media')->restrictOnDelete();
        });
        Schema::table('pages', fn (Blueprint $table) => $table->json('media_ids')->nullable());
        Schema::table('page_revisions', fn (Blueprint $table) => $table->json('media_ids')->nullable());
    }

    public function down(): void
    {
        Schema::table('page_revisions', fn (Blueprint $table) => $table->dropColumn('media_ids'));
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('media_ids'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('avatar_media_id'));
        Schema::dropIfExists('media');
    }
};
