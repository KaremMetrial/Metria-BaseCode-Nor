<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\ListPagesRequest;
use App\Http\Resources\Shared\PageResource;
use App\Models\ContactSetting;
use App\Models\Country;
use App\Models\Page;
use App\Support\ApiResponse;
use App\Support\SearchMatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PageController extends Controller
{
    /** List published pages.
     * Public bilingual content. Only published snapshots are exposed; draft navigation changes remain private.
     */
    public function index(ListPagesRequest $request, SearchMatcher $matcher): JsonResponse
    {
        $data = $request->validated();
        $value = static function (Page $page, string $field): mixed {
            return match ($field) {
                'title' => $page->publishedRevision->translations[app()->getLocale()]['title'],
                'sort_order', 'show_in_footer', 'show_in_settings' => $page->publishedRevision->navigation[$field],
                'published_at' => $page->publishedRevision->published_at->getTimestamp(),
                default => $page->getAttribute($field),
            };
        };
        $pages = Page::query()->where('status', 'published')->with('publishedRevision')->get()
            ->filter(function (Page $page) use ($data, $value): bool {
                foreach (['slug', 'show_in_footer', 'show_in_settings'] as $field) {
                    if (array_key_exists($field, $data) && $value($page, $field) != $data[$field]) {
                        return false;
                    }
                }

                return true;
            });
        if (isset($data['search'])) {
            foreach (['literal', 'arabic', 'ending'] as $mode) {
                $matched = $pages->filter(function (Page $page) use ($matcher, $data, $mode): bool {
                    if ($mode === 'literal' && $matcher->matches($page->slug, $data['search'], $mode)) {
                        return true;
                    }
                    foreach ($page->publishedRevision->translations as $locale => $translation) {
                        if ($matcher->matches($translation['title'], $data['search'], $mode, $locale)) {
                            return true;
                        }
                    }

                    return false;
                });
                if ($matched->isNotEmpty()) {
                    break;
                }
            }
            $pages = $matched;
        }
        $pages = $pages->sort(function (Page $left, Page $right) use ($request, $value): int {
            foreach ($request->sortTerms() as [$field, $direction]) {
                $comparison = $value($left, $field) <=> $value($right, $field);
                if ($comparison !== 0) {
                    return $direction === 'desc' ? -$comparison : $comparison;
                }
            }

            return 0;
        })->values();

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
