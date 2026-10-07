<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Category\SaveCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\Shared\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CategoryController extends Controller
{
    /**
     * List all categories.
     *
     * Includes inactive categories and translations; ordered by sort order and ID.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::paginated(CategoryResource::class, Category::query()->with('translations')->orderBy('sort_order')->orderBy('id')->paginate(25));
    }

    /**
     * Create a category.
     *
     * Requires a name in the default locale (en). Optional ar translations are supported. Maximum tree depth is five.
     */
    public function store(CategoryRequest $request, SaveCategory $action): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($action->execute(null, $request->validated())), __('categories.saved'), 201);
    }

    /**
     * Update a category.
     *
     * Partial update. A supplied locale object must contain its name. Parent changes cannot create cycles or exceed five levels.
     */
    public function update(CategoryRequest $request, Category $category, SaveCategory $action): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($action->execute($category, $request->validated())), __('categories.saved'));
    }

    /**
     * Delete a category.
     *
     * Deletion fails with RESOURCE_IN_USE when the category has children.
     */
    public function destroy(Category $category, SaveCategory $action): JsonResponse
    {
        $action->delete($category);

        return ApiResponse::success(null, __('categories.deleted'));
    }
}
