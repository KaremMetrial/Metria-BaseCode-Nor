<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Shared\Media\ManageMedia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\MediaRequest;
use App\Http\Resources\Shared\MediaResource;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController extends Controller
{
    /** Upload a temporary file.
     * Multipart file + purpose (support or page). Images are re-encoded; PDF requires antivirus. Attach the returned ID within 24 hours.
     */
    public function store(MediaRequest $request, ManageMedia $action): JsonResponse
    {
        return ApiResponse::success(new MediaResource($action->upload($request->user(), $request->file('file'), $request->validated('purpose'))), status: 201);
    }

    /** Read authorized media metadata. */
    public function show(Request $request, Media $media, ManageMedia $action): JsonResponse
    {
        abort_unless($action->canRead($request->user(), $media), 404);

        return ApiResponse::success(new MediaResource($media));
    }

    /** Download a private file. A current bearer token and resource access are required on every request. */
    public function download(Request $request, Media $media, ManageMedia $action): StreamedResponse
    {
        abort_unless($action->canRead($request->user(), $media), 404);

        return $this->stream($media, false);
    }

    /** Discard an unattached upload. Physical deletion is retried by media:prune. */
    public function destroy(Request $request, Media $media, ManageMedia $action): JsonResponse
    {
        $action->discard($request->user(), $media);

        return ApiResponse::success();
    }

    /** Display a page image only while referenced by the current published revision. */
    public function public(Media $media): StreamedResponse
    {
        abort_unless($media->purpose === 'page' && $media->status === 'attached'
            && Page::query()->whereKey($media->page_id)->where('status', 'published')
                ->whereHas('publishedRevision', fn ($query) => $query->whereJsonContains('media_ids', $media->id))->exists(), 404);

        return $this->stream($media, true);
    }

    /** Display an avatar through a five-minute signed URL. Replacing it revokes the previous URL. */
    public function avatar(Media $media): StreamedResponse
    {
        abort_unless($media->purpose === 'avatar' && $media->status === 'attached'
            && User::query()->whereKey($media->user_id)->where('status', 'active')->where('avatar_media_id', $media->id)->exists(), 404);

        return $this->stream($media, true);
    }

    private function stream(Media $media, bool $inline): StreamedResponse
    {
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response($media->path, $media->id.($media->mime_type === 'application/pdf' ? '.pdf' : '.png'),
            ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"], $inline ? 'inline' : 'attachment');
    }
}
