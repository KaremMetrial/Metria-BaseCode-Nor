<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cities.
 *
 * Deliberately has NO `country_id`. Country is reached through
 * City -> Governorate -> Country, and duplicating it here would create a second
 * source of truth that can (and eventually will) contradict the first. If a
 * demonstrated query or performance requirement appears later, denormalise then
 * -- with a constraint that keeps the copy honest.
 *
 * Coordinates are nullable decimals: real places without a surveyed centroid
 * exist, and inventing 0,0 would place them in the Gulf of Guinea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('governorate_id')->constrained()->cascadeOnDelete();

            $table->string('code', 16)->nullable();
            $table->string('postal_code', 16)->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['governorate_id', 'code']);

            // Listing cities for a governorate, active first, in order.
            $table->index(['governorate_id', 'is_active', 'sort_order']);
        });

        Schema::create('city_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5)->index();

            $table->string('name');

            $table->timestamps();

            $table->unique(['city_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_translations');
        Schema::dropIfExists('cities');
    }
};
