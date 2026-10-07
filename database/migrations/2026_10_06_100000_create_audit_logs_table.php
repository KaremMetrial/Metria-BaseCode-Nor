<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for sensitive actions.
 *
 * There is intentionally no `updated_at`: an audit row that can be edited is
 * not an audit row. Nothing in the application updates or deletes these rows.
 *
 * `actor_user_id` uses nullOnDelete rather than cascade: removing an operator
 * must not erase the record of what that operator did. The row survives with a
 * null actor and the historical `action`/`subject` intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 100)->index();

            // subject_type + subject_id (+ composite index).
            $table->nullableMorphs('subject');

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('context')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 64)->nullable()->index();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
