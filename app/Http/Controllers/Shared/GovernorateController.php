<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\CityResource;
use App\Http\Resources\Shared\GovernorateResource;
use App\Models\Governorate;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Public governorate reads.
 *
 * A governorate is only visible when it *and* its country are active. Checked
 * here rather than in the resource because visibility is an authorization-adjacent
 * decision, and a resource is not the place to make one.
 */
final class GovernorateController extends Controller
{
    /**
     * Get an available governorate.
     *
     * The governorate and its parent country must be active.
     */
    public function show(int $governorate): JsonResponse
    {
        $model = $this->visible()
            ->with('translations')
            ->findOrFail($governorate);

        return ApiResponse::success(new GovernorateResource($model));
    }

    /**
     * The third step of the picker: the cities of one governorate.
     *
     * Deliberately unbounded by pagination: the whole point of this endpoint is
     * to populate one select element, and splitting it across pages would force
     * the client to page through a picker.
     */
    /**
     * List a governorate’s cities.
     *
     * Returns active cities only when their governorate and country are active. The list is not paginated.
     */
    public function cities(int $governorate): JsonResponse
    {
        $model = $this->visible()->findOrFail($governorate);

        $cities = $model->cities()
            ->active()
            ->ordered()
            ->with('translations')
            ->get();

        return ApiResponse::success(CityResource::collection($cities));
    }

    /**
     * Governorates whose country is still active.
     *
     * @return Builder<Governorate>
     */
    private function visible(): Builder
    {
        return Governorate::query()
            ->active()
            ->whereHas('country', fn ($query) => $query->where('is_active', true));
    }
}
