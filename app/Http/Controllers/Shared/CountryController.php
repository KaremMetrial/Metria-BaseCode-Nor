<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\ListCountriesRequest;
use App\Http\Requests\Shared\ListGovernoratesRequest;
use App\Http\Resources\Shared\CountryResource;
use App\Http\Resources\Shared\GovernorateResource;
use App\Models\Country;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;

/**
 * Public country reads -- the first step of every hierarchy picker.
 *
 * Two invariants hold across this controller:
 *
 *  - Only active rows are reachable. A deactivated country is a 404, not a 403:
 *    clients should not have to branch on a status field to decide whether they
 *    are allowed to use a country. "Not available here" and "does not exist" are
 *    the same thing to a marketplace.
 *  - `translations` is eager-loaded on every query. The resource reads the
 *    localized name, and without the eager load Astrotomic would fire one query
 *    per row -- 250 queries on a listing endpoint.
 *
 * Route parameters are constrained to digits in routes/shared/locations.php, so the
 * `int` type hints below cannot receive a non-numeric string.
 */
final class CountryController extends Controller
{
    /**
     * List available countries.
     *
     * Returns all active countries, ordered for display. Use the ID and calling code for phone authentication. phone_lengths and phone_regex describe national significant digits (without calling code or national dialing prefix), following the configured mobile-only policy. They are UI hints; server-side validation remains authoritative.
     */
    public function index(ListCountriesRequest $request, ListQuery $filters): JsonResponse
    {
        $countries = $filters->apply(Country::query()->active()->with('translations'), $request)->get();

        return ApiResponse::success(CountryResource::collection($countries));
    }

    /**
     * Get an available country.
     *
     * Returns an active country in the requested language.
     */
    public function show(int $country): JsonResponse
    {
        $model = Country::query()
            ->active()
            ->with('translations')
            ->findOrFail($country);

        return ApiResponse::success(new CountryResource($model));
    }

    /**
     * The second step of the picker: the divisions of one country.
     */
    /**
     * List a country’s governorates.
     *
     * Returns active governorates of an active country. The list is not paginated.
     */
    public function governorates(ListGovernoratesRequest $request, int $country, ListQuery $filters): JsonResponse
    {
        $model = Country::query()->active()->findOrFail($country);
        $query = $model->governorates()->active()->with('translations');

        return ApiResponse::success(GovernorateResource::collection($filters->apply($query->getQuery(), $request)->get()));
    }
}
