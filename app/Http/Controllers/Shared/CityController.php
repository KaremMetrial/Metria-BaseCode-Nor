<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\CityResource;
use App\Models\City;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Public city reads.
 *
 * This is the endpoint an address form uses to confirm a selected city, so the
 * `coords` a client stored are never trusted from the client side.
 */
final class CityController extends Controller
{
    /**
     * Get an available city.
     *
     * The city and all its ancestors must be active. Includes coordinates when configured.
     */
    public function show(int $city): JsonResponse
    {
        $model = $this->visible()
            ->with('translations')
            ->findOrFail($city);

        return ApiResponse::success(new CityResource($model));
    }

    /**
     * Visible = the city, its governorate and its country are all active.
     *
     * Walking the whole chain matters: deactivating a country must take its
     * cities offline too, and relying on the FK alone would leave them reachable
     * by id.
     *
     * @return Builder<City>
     */
    private function visible(): Builder
    {
        return City::query()
            ->active()
            ->whereHas('governorate', function ($query): void {
                $query->where('is_active', true)
                    ->whereHas('country', fn ($country) => $country->where('is_active', true));
            });
    }
}
