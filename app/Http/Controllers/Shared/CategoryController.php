<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SearchCategoryRequest;
use App\Http\Resources\Shared\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Search public categories.
     *
     * Returns active categories whose entire ancestor chain is active. Supports keyword, parent, sorting and pagination filters.
     */
    public function index(SearchCategoryRequest $request, ListQuery $filters): JsonResponse
    {
        return ApiResponse::paginated(CategoryResource::class, $filters->apply(Category::query()->visible()->with('translations'), $request)->paginate($request->perPage()));
    }

    /**
     * Get a public category.
     *
     * Returns one visible category in the requested language. Hidden categories and categories below hidden ancestors return 404.
     */
    public function show(int $category): JsonResponse
    {
        return ApiResponse::success(new CategoryResource(Category::query()->visible()->with('translations')->findOrFail($category)));
    }
}
