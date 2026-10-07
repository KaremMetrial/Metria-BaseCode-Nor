<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the constraint that Phase 1 had to defer.
 *
 * `users.phone_country_id` existed from the start so the column could be part of
 * the canonical users table; the foreign key could not be created until
 * `countries` existed.
 *
 * restrictOnDelete, not cascade or set null:
 *  - cascade would silently delete users when a country row is removed;
 *  - set null would silently discard which numbering plan a phone belongs to,
 *    corrupting the meaning of the stored E.164 value.
 * Reference data like this is deactivated via `is_active`, never hard-deleted,
 * so the restriction is a guard rail rather than a workflow obstacle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('phone_country_id')
                ->references('id')
                ->on('countries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['phone_country_id']);
        });
    }
};
