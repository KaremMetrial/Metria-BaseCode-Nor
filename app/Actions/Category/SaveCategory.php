<?php

namespace App\Actions\Category;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

final class SaveCategory
{
    public function execute(?Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data): Category {
            DB::table('application_locks')->where('name', 'category_tree')->lockForUpdate()->first();
            $category = $category ? Category::query()->findOrFail($category->id) : new Category;
            $parent = $data['parent_id'] ?? ($category->parent_id ?? null);
            if (array_key_exists('parent_id', $data) && $data['parent_id'] === null) {
                $parent = null;
            }
            $parents = Category::query()->pluck('parent_id', 'id')->all();
            $id = $category->id ?? -1;
            $parents[$id] = $parent;
            foreach (array_keys($parents) as $node) {
                $seen = [];
                $cursor = $node;
                while ($cursor !== null) {
                    if (isset($seen[$cursor])) {
                        throw new DomainException(ErrorCode::CATEGORY_CYCLE);
                    }
                    if (count($seen) >= Category::MAX_DEPTH) {
                        throw new DomainException(ErrorCode::CATEGORY_DEPTH_EXCEEDED);
                    }
                    $seen[$cursor] = true;
                    $cursor = $parents[$cursor] ?? null;
                }
            }
            $category->fill($data)->save();

            return $category->load('translations');
        }, 5);
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            DB::table('application_locks')->where('name', 'category_tree')->lockForUpdate()->first();
            if ($category->children()->exists()) {
                throw new DomainException(ErrorCode::RESOURCE_IN_USE);
            }
            $category->delete();
        }, 5);
    }
}
