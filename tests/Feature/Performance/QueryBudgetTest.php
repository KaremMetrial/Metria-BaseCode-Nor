<?php

namespace Tests\Feature\Performance;

use App\Models\Category;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_translated_category_list_query_count_does_not_grow_per_result(): void
    {
        Category::create(['en' => ['name' => 'First'], 'ar' => ['name' => 'الأول']]);
        $this->getJson('/api/v1/categories')->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/categories?per_page=50', ['X-Locale' => 'ar'])->assertOk()->assertJsonCount(1, 'data.items');
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 30; $i++) {
            Category::create(['en' => ['name' => 'Category '.$i], 'ar' => ['name' => 'تصنيف '.$i]]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->getJson('/api/v1/categories?per_page=50', ['X-Locale' => 'ar'])->assertOk()->assertJsonCount(31, 'data.items');

        $this->assertLessThanOrEqual($small, count(DB::getQueryLog()), 'Translated category list introduced per-row queries.');
        $this->assertLessThanOrEqual(5, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_public_country_translations_are_eager_loaded_with_a_bounded_query_count(): void
    {
        Country::factory()->count(25)->create();
        $this->getJson('/api/v1/locations/countries')->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->getJson('/api/v1/locations/countries', ['X-Locale' => 'ar'])->assertOk()->assertJsonCount(25, 'data');

        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()), 'Country translation serialization introduced N+1 queries.');
        DB::disableQueryLog();
    }

    public function test_thousand_category_search_keeps_pagination_and_query_cost_bounded(): void
    {
        config(['access.rate_limits.api' => 10000]);
        $categories = [];
        $translations = [];
        for ($id = 1; $id <= 1000; $id++) {
            $categories[] = ['id' => $id, 'is_active' => true, 'sort_order' => $id];
            $translations[] = ['category_id' => $id, 'locale' => 'en', 'name' => 'Catalog item '.$id];
            $translations[] = ['category_id' => $id, 'locale' => 'ar', 'name' => 'منتج '.$id];
        }
        foreach (array_chunk($categories, 100) as $chunk) {
            DB::table('categories')->insert($chunk);
        }
        foreach (array_chunk($translations, 100) as $chunk) {
            DB::table('category_translations')->insert($chunk);
        }
        $this->getJson('/api/v1/categories?per_page=25')->assertOk();
        $durations = [];
        $maximumQueries = 0;
        DB::enableQueryLog();
        for ($request = 0; $request < 40; $request++) {
            DB::flushQueryLog();
            $started = hrtime(true);
            $this->getJson('/api/v1/categories?per_page=25&keyword='.urlencode('منتج').'&page='.(1 + $request % 10), ['X-Locale' => 'ar'])->assertOk()->assertJsonPath('data.meta.total', 1000)->assertJsonCount(25, 'data.items');
            $durations[] = (hrtime(true) - $started) / 1000000;
            $maximumQueries = max($maximumQueries, count(DB::getQueryLog()));
        }
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(5, $maximumQueries);
        if ($path = getenv('METRIAL_BENCHMARK_OUTPUT')) {
            sort($durations);
            file_put_contents($path, json_encode(['database' => DB::getDriverName(), 'categories' => 1000, 'translations' => 2000, 'requests' => 40, 'maximum_queries' => $maximumQueries, 'p50_ms' => round($durations[19], 2), 'p95_ms' => round($durations[37], 2), 'max_ms' => round(max($durations), 2), 'boundary' => 'Sequential Laravel HTTP-kernel requests on this host; excludes TLS, internet latency and concurrent production load.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }
}
