<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->admin()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user);
    }

    private function data(string $name = 'Books'): array
    {
        return ['translations' => ['en' => ['name' => $name], 'ar' => ['name' => 'كتب']]];
    }

    public function test_categories_are_translated_searchable_and_permission_protected(): void
    {
        $this->admin();
        $id = $this->postJson('/api/v1/admin/categories', $this->data())->assertCreated()->json('data.id');
        $this->getJson('/api/v1/categories?keyword=كتب', ['X-Locale' => 'ar'])->assertOk()->assertJsonPath('data.items.0.name', 'كتب');
        $this->getJson('/api/v1/categories?keyword=Books', ['X-Locale' => 'en'])->assertOk()->assertJsonPath('data.items.0.name', 'Books');
        $this->getJson('/api/v1/categories?sort=id%20desc')->assertUnprocessable();
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson('/api/v1/admin/categories/'.$id, ['is_active' => false])->assertForbidden();
        $this->assertTrue(Category::find($id)->is_active);
    }

    public function test_category_cycles_and_parent_deletion_are_rejected(): void
    {
        $this->admin();
        $a = $this->postJson('/api/v1/admin/categories', $this->data())->assertCreated()->json('data.id');
        $b = $this->postJson('/api/v1/admin/categories', $this->data('Child') + ['parent_id' => $a])->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/categories/'.$a, ['parent_id' => $b])->assertUnprocessable()->assertJsonPath('code', 'CATEGORY_CYCLE');
        $this->deleteJson('/api/v1/admin/categories/'.$a)->assertConflict();
        $this->patchJson('/api/v1/admin/categories/'.$a, ['is_active' => false])->assertOk();
        $this->getJson('/api/v1/categories/'.$b)->assertNotFound();
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.meta.total', 0);
    }

    public function test_nested_translation_input_cannot_change_parent_identity(): void
    {
        $this->admin();
        $data = $this->data();
        $data['translations']['ar']['category_id'] = 999;
        $this->postJson('/api/v1/admin/categories', $data)->assertUnprocessable();
        $this->assertDatabaseCount('categories', 0);
    }
}
