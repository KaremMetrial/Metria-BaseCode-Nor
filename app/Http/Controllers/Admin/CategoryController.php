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
    public function index(): JsonResponse
    {
        return ApiResponse::paginated(CategoryResource::class, Category::query()->with('translations')->orderBy('sort_order')->orderBy('id')->paginate(25));
    }

    public function store(CategoryRequest $request, SaveCategory $action): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($action->execute(null, $request->validated())), __('categories.saved'), 201);
    }

    public function update(CategoryRequest $request, Category $category, SaveCategory $action): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($action->execute($category, $request->validated())), __('categories.saved'));
    }

    public function destroy(Category $category, SaveCategory $action): JsonResponse
    {
        $action->delete($category);

        return ApiResponse::success(null, __('categories.deleted'));
    }
}
