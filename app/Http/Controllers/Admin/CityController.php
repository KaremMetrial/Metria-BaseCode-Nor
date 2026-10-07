<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Location\CreateCity;
use App\Actions\Location\DeleteCity;
use App\Actions\Location\UpdateCity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyCityRequest;
use App\Http\Requests\Admin\IndexCityRequest;
use App\Http\Requests\Admin\StoreCityRequest;
use App\Http\Requests\Admin\UpdateCityRequest;
use App\Http\Resources\Admin\AdminCityResource;
use App\Models\City;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Administrative city management.
 *
 * The listing is always scoped to one governorate: see IndexCityRequest.
 */
final class CityController extends Controller
{
    /**
     * List all cities.
     *
     * Requires the governorate_id query parameter. Includes inactive records and all translations. This list is not paginated.
     */
    public function index(IndexCityRequest $request): JsonResponse
    {
        $cities = City::query()
            ->forGovernorate((int) $request->validated('governorate_id'))
            ->ordered()
            ->with('translations')
            ->get();

        return ApiResponse::success(AdminCityResource::collection($cities));
    }

    /**
     * Get a city for editing.
     *
     * Returns administrative fields and all translations, including inactive records.
     */
    public function show(City $city): JsonResponse
    {
        return ApiResponse::success(new AdminCityResource($city->load('translations')));
    }

    /**
     * Create a city.
     *
     * Requires an English name in translations.en. Arabic is optional; every supplied locale object must include its name. Only en and ar translation keys are accepted.
     */
    public function store(StoreCityRequest $request, CreateCity $action): JsonResponse
    {
        $city = $action->execute($request->validated());

        return ApiResponse::success(
            new AdminCityResource($city),
            $this->message('created', 'city'),
            201,
        );
    }

    /**
     * Update a city.
     *
     * Partial update: omit unchanged fields. When providing a locale object, include its name. Parent changes must preserve the location hierarchy.
     */
    public function update(UpdateCityRequest $request, City $city, UpdateCity $action): JsonResponse
    {
        $updated = $action->execute($city, $request->validated());

        return ApiResponse::success(
            new AdminCityResource($updated),
            $this->message('updated', 'city'),
        );
    }

    /**
     * Delete a city.
     *
     * Returns a null data payload on success. Referenced locations cannot be deleted and return 409 RESOURCE_IN_USE.
     */
    public function destroy(DestroyCityRequest $request, City $city, DeleteCity $action): JsonResponse
    {
        $action->execute($city);

        return ApiResponse::success(null, $this->message('deleted', 'city'));
    }

    private function message(string $verb, string $resource): string
    {
        return __('messages.'.$verb, ['resource' => __('messages.resources.'.$resource)]);
    }
}
