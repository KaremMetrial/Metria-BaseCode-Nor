<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\PageResource;
use App\Models\ContactSetting;
use App\Models\Country;
use App\Models\Page;
use App\Support\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PageController extends Controller
{
    /** List published pages.
     * Public bilingual content. Only published snapshots are exposed; draft navigation changes remain private.
     */
    public function index(): JsonResponse
    {
        $pages = Page::query()->where('status', 'published')->with('publishedRevision')->get()->sortBy(fn (Page $page): string => sprintf('%010d:%010d', $page->publishedRevision->navigation['sort_order'], $page->id))->values();

        return ApiResponse::success(PageResource::collection($pages));
    }

    /** Read a published page.
     * Resolves a stable slug in the requested locale. Draft and archived pages return 404.
     */
    public function show(string $slug): JsonResponse
    {
        return ApiResponse::success(new PageResource($this->published($slug)));
    }

    public function web(Request $request, string $slug): View
    {
        $data = $request->validate(['locale' => ['sometimes', 'in:en,ar']]);
        app()->setLocale($data['locale'] ?? $request->getPreferredLanguage(['en', 'ar']) ?? 'en');
        $page = (new PageResource($this->published($slug)))->resolve($request);

        return view('pages.show', ['page' => $page,
            'contact' => $slug === 'contact-us' ? ContactSetting::query()->findOrFail(1)->publicSettings() : null,
            'countries' => $slug === 'contact-us' ? Country::query()->active()->ordered()->with('translations')->get() : collect(),
        ]);
    }

    private function published(string $slug): Page
    {
        return Page::query()->where('slug', $slug)->where('status', 'published')->with('publishedRevision')->firstOrFail();
    }
}
