<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('draft_version')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('show_in_footer')->default(true);
            $table->boolean('show_in_settings')->default(true);
            $table->string('terms_version', 100)->nullable();
            $table->timestamps();
        });
        Schema::create('page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->longText('content');
            $table->unique(['page_id', 'locale']);
        });
        Schema::create('page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('translations');
            $table->json('navigation');
            $table->string('terms_version', 100)->nullable();
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->unique(['page_id', 'version']);
        });
        Schema::table('pages', function (Blueprint $table): void {
            $table->foreignId('published_revision_id')->nullable()->constrained('page_revisions')->restrictOnDelete();
        });
        Schema::create('terms_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('page_revision_id')->nullable()->constrained('page_revisions')->restrictOnDelete();
            $table->string('terms_version', 100);
            $table->timestamp('accepted_at');
            $table->unique(['user_id', 'terms_version']);
        });
        /** This permanent row serializes the first publication against registration too. */
        DB::table('pages')->insert(['slug' => 'terms', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->whereNotNull('terms_accepted_at')->whereNotNull('terms_version')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('terms_acceptances')->insert(['user_id' => $user->id, 'terms_version' => $user->terms_version, 'accepted_at' => $user->terms_accepted_at, 'page_revision_id' => null]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
        Schema::table('pages', fn (Blueprint $table) => $table->dropConstrainedForeignId('published_revision_id'));
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }
};
