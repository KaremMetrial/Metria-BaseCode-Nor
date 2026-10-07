<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Location\CreateCountry;
use App\Actions\Location\DeleteCountry;
use App\Actions\Location\UpdateCountry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyCountryRequest;
use App\Http\Requests\Admin\StoreCountryRequest;
use App\Http\Requests\Admin\UpdateCountryRequest;
use App\Http\Resources\Admin\AdminCountryResource;
use App\Models\Country;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Administrative country management.
 *
 * Reads and writes sit behind different permissions (`locations.read` on the
 * route, `locations.manage` on the write requests). Writes are authorized twice
 * on purpose: once by route middleware and once inside the FormRequest, so a
 * future route that forgets the middleware still refuses the write.
 *
 * Bound by model, not by id. The Update request needs the *record* to exclude it
 * from the uniqueness check on `iso2`; if `$this->route('country')` were a raw id
 * string, a PATCH that re-sends an unchanged iso2 would be rejected as a
 * duplicate of itself. Binding also turns a missing id into a clean 404 for free.
 *
 * Unlike the public controller, this one returns inactive rows and every
 * translation -- an edit form cannot populate itself from a filtered view.
 */
final class CountryController extends Controller
{
    /**
     * List all countries.
     *
     * Includes inactive records and all translations. This list is not paginated.
     */
    public function index(): JsonResponse
    {
        $countries = Country::query()
            ->ordered()
            ->with('translations')
            ->get();

        return ApiResponse::success(AdminCountryResource::collection($countries));
    }

    /**
     * Get a country for editing.
     *
     * Returns administrative fields and all translations, including inactive records.
     */
    public function show(Country $country): JsonResponse
    {
        return ApiResponse::success(new AdminCountryResource($country->load('translations')));
    }

    /**
     * Create a country.
     *
     * Requires an English name in translations.en. Arabic is optional; every supplied locale object must include its name. Only en and ar translation keys are accepted.
     */
    public function store(StoreCountryRequest $request, CreateCountry $action): JsonResponse
    {
        $country = $action->execute($request->validated());

        return ApiResponse::success(
            new AdminCountryResource($country),
            $this->message('created', 'country'),
            201,
        );
    }

    /**
     * Update a country.
     *
     * Partial update: omit unchanged fields. When providing a locale object, include its name. Parent changes must preserve the location hierarchy.
     */
    public function update(
        UpdateCountryRequest $request,
        Country $country,
        UpdateCountry $action,
    ): JsonResponse {
        $updated = $action->execute($country, $request->validated());

        return ApiResponse::success(
            new AdminCountryResource($updated),
            $this->message('updated', 'country'),
        );
    }

    /**
     * Delete a country.
     *
     * Returns a null data payload on success. Referenced locations cannot be deleted and return 409 RESOURCE_IN_USE.
     */
    public function destroy(DestroyCountryRequest $request, Country $country, DeleteCountry $action): JsonResponse
    {
        $action->execute($country);

        return ApiResponse::success(null, $this->message('deleted', 'country'));
    }

    private function message(string $verb, string $resource): string
    {
        return __('messages.'.$verb, ['resource' => __('messages.resources.'.$resource)]);
    }
}
