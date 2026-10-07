<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SearchCategoryRequest;
use App\Http\Resources\Shared\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Search public categories.
     *
     * Returns active categories whose entire ancestor chain is active. Supports keyword, parent, sorting and pagination filters.
     */
    public function index(SearchCategoryRequest $request): JsonResponse
    {
        $q = Category::query()->visible()->with('translations');
        if ($request->has('parent_id')) {
            $q->where('parent_id', $request->validated('parent_id'));
        }
        if ($request->filled('keyword')) {
            $q->whereHas('translations', fn ($t) => $t->where('locale', app()->getLocale())->where('name', 'like', '%'.$request->validated('keyword').'%'));
        }

        return ApiResponse::paginated(CategoryResource::class, $q->orderBy($request->validated('sort', 'sort_order'))->orderBy('id')->paginate($request->integer('per_page', 25)));
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
