<?php

namespace Tests\Feature\Content;

use App\Models\TermsAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LegacyTermsMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_legacy_consent_is_preserved_without_inventing_a_document_snapshot(): void
    {
        $mediaMigration = require database_path('migrations/2026_10_11_090934_create_media_table.php');
        $mediaMigration->down();
        $migration = require database_path('migrations/2026_10_11_082203_create_content_tables.php');
        $migration->down();
        $user = User::factory()->client()->create(['terms_version' => 'legacy-1', 'terms_accepted_at' => now()->subDay()]);
        $migration->up();
        $mediaMigration->up();
        $acceptance = TermsAcceptance::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('legacy-1', $acceptance->terms_version);
        $this->assertNull($acceptance->page_revision_id);
        $this->assertTrue($user->terms_accepted_at->equalTo($acceptance->accepted_at));
    }
}
