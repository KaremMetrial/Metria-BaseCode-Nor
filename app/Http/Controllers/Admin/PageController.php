<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Content\PublishPage;
use App\Actions\Admin\Content\SavePage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Http\Resources\Admin\PageResource;
use App\Models\Page;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PageController extends Controller
{
    /** List content pages.
     * Includes drafts and archived pages. Filter by status; pages contain draft translations and publication metadata.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])]]);
        $query = Page::query()->with(['translations', 'publishedRevision'])->orderBy('sort_order')->orderBy('id');
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return ApiResponse::paginated(PageResource::class, $query->paginate(25));
    }

    /** Create a draft page.
     * Slug is permanent. Supply localized Markdown; publication requires both en and ar translations.
     */
    public function store(PageRequest $request, SavePage $action): JsonResponse
    {
        return ApiResponse::success(new PageResource($action->execute(null, $request->validated(), $request->user())), status: 201);
    }

    /** Read a page draft.
     * Returns draft content without changing the currently published snapshot.
     */
    public function show(Page $page): JsonResponse
    {
        return ApiResponse::success(new PageResource($page->load(['translations', 'publishedRevision'])));
    }

    /** Update a draft page.
     * Send draft_version from the latest response. Stale edits return RESOURCE_CONFLICT; publication is separate.
     */
    public function update(PageRequest $request, Page $page, SavePage $action): JsonResponse
    {
        return ApiResponse::success(new PageResource($action->execute($page, $request->validated(), $request->user())));
    }

    /** Publish a page snapshot.
     * Requires draft_version and requires_acceptance. A new required terms release changes the registration contract atomically.
     */
    public function publish(Request $request, Page $page, PublishPage $action): JsonResponse
    {
        $data = $request->validate(['draft_version' => ['required', 'integer', 'min:1'], 'requires_acceptance' => ['required', 'boolean']]);

        return ApiResponse::success(new PageResource($action->execute($page, (int) $data['draft_version'], (bool) $data['requires_acceptance'], $request->user())));
    }

    /** Archive a page.
     * Send draft_version. Published terms cannot be archived while registration relies on them.
     */
    public function archive(Request $request, Page $page, PublishPage $action): JsonResponse
    {
        $data = $request->validate(['draft_version' => ['required', 'integer', 'min:1']]);

        return ApiResponse::success(new PageResource($action->archive($page, (int) $data['draft_version'], $request->user())));
    }

    /** List immutable published revisions.
     * Returns complete bilingual snapshots, latest first; draft updates never modify these records.
     */
    public function revisions(Page $page): JsonResponse
    {
        $rows = $page->revisions()->orderByDesc('version')->paginate(25);

        return ApiResponse::success(['items' => $rows->getCollection()->map(fn ($row): array => ['id' => $row->id, 'version' => $row->version, 'translations' => $row->translations, 'navigation' => $row->navigation, 'media_ids' => $row->media_ids ?? [], 'terms_version' => $row->terms_version, 'published_by' => $row->published_by, 'published_at' => $row->published_at->toIso8601String()]), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()]]);
    }
}
