<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Countries: business metadata only.
 *
 * Important boundary: this table decides *whether the product operates in* a
 * country and how it is displayed. It is NOT the authority on phone numbering
 * plans -- libphonenumber is (see App\Contracts\Phone\PhoneNumberServiceInterface).
 * The phone-related columns here are display hints at most.
 *
 *  - `iso2` is the canonical identity and the canonical phone region.
 *  - `calling_code` is deliberately NOT unique: many countries and territories
 *    share a dialing prefix (+1 covers US and Canada; +44 covers several
 *    territories), so it can never identify a country.
 *  - `numeric_code` is a 3-character string, not an integer, because ISO 3166-1
 *    numeric codes have significant leading zeros (Afghanistan is 004).
 *  - `phone_example` is a UI hint; the authoritative example comes from
 *    libphonenumber metadata at seed time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();

            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->nullable()->unique();
            $table->char('numeric_code', 3)->nullable();

            // Display only, e.g. "+20". Never used as an identifier.
            $table->string('calling_code', 8);

            $table->string('currency_code', 3)->nullable();
            $table->string('timezone_default', 64)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // UI hint for phone inputs. libphonenumber remains authoritative.
            $table->string('phone_example', 32)->nullable();

            $table->timestamps();

            // The public listing filter: active countries in display order.
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('country_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5)->index();

            $table->string('name');
            $table->string('nationality')->nullable();

            $table->timestamps();

            $table->unique(['country_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_translations');
        Schema::dropIfExists('countries');
    }
};
