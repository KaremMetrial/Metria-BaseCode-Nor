<?php

namespace App\Http\Resources\Shared;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Media */
final class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'purpose' => $this->purpose, 'status' => $this->status,
            'name' => $this->original_name, 'mime_type' => $this->mime_type, 'size' => $this->size,
            'width' => $this->width, 'height' => $this->height, 'expires_at' => $this->expires_at?->toIso8601String(),
            'url' => $this->purpose === 'page' ? route('api.v1.media.public', ['media' => $this->id]) : null,
            'download_url' => route('api.v1.'.$request->user()->type->value.'.media.download', ['media' => $this->id])];
    }
}
