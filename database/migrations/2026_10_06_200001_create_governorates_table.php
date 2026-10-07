<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-level administrative divisions.
 *
 * Named `governorates` for consistency even though other countries call the same
 * level a state, province, emirate or region. The actual word is recorded in
 * `type` (App\Enums\GovernorateType) so the data stays truthful while the code
 * stays readable.
 *
 * A note on the unique index: UNIQUE(country_id, code) does not restrict
 * rows whose `code` is NULL, because MySQL treats NULLs as distinct. That is
 * exactly the desired behaviour -- `code` is optional, and only real codes must
 * be unique within a country.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governorates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            $table->string('code', 16)->nullable();

            // governorate | state | province | emirate | region
            $table->string('type', 20)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['country_id', 'code']);

            // Listing governorates for a country, active first, in order.
            $table->index(['country_id', 'is_active', 'sort_order']);
        });

        Schema::create('governorate_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('governorate_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5)->index();

            $table->string('name');

            $table->timestamps();

            $table->unique(['governorate_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governorate_translations');
        Schema::dropIfExists('governorates');
    }
};
