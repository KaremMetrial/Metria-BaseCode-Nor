<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Location\CreateGovernorate;
use App\Actions\Location\DeleteGovernorate;
use App\Actions\Location\UpdateGovernorate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyGovernorateRequest;
use App\Http\Requests\Admin\IndexGovernorateRequest;
use App\Http\Requests\Admin\StoreGovernorateRequest;
use App\Http\Requests\Admin\UpdateGovernorateRequest;
use App\Http\Resources\Admin\AdminGovernorateResource;
use App\Models\Governorate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Administrative governorate management.
 *
 * The listing is always scoped to one country: see IndexGovernorateRequest.
 * Model binding matters here too -- UpdateGovernorateRequest reads the bound
 * record to scope the `code` uniqueness check to the governorate's *current*
 * country, so a PATCH that omits `country_id` cannot be tricked into skipping
 * the check.
 */
final class GovernorateController extends Controller
{
    /**
     * List all governorates.
     *
     * Requires the country_id query parameter. Includes inactive records and all translations. This list is not paginated.
     */
    public function index(IndexGovernorateRequest $request): JsonResponse
    {
        $governorates = Governorate::query()
            ->forCountry((int) $request->validated('country_id'))
            ->ordered()
            ->with('translations')
            ->get();

        return ApiResponse::success(AdminGovernorateResource::collection($governorates));
    }

    /**
     * Get a governorate for editing.
     *
     * Returns administrative fields and all translations, including inactive records.
     */
    public function show(Governorate $governorate): JsonResponse
    {
        return ApiResponse::success(
            new AdminGovernorateResource($governorate->load('translations'))
        );
    }

    /**
     * Create a governorate.
     *
     * Requires an English name in translations.en. Arabic is optional; every supplied locale object must include its name. Only en and ar translation keys are accepted.
     */
    public function store(StoreGovernorateRequest $request, CreateGovernorate $action): JsonResponse
    {
        $governorate = $action->execute($request->validated());

        return ApiResponse::success(
            new AdminGovernorateResource($governorate),
            $this->message('created', 'governorate'),
            201,
        );
    }

    /**
     * Update a governorate.
     *
     * Partial update: omit unchanged fields. When providing a locale object, include its name. Parent changes must preserve the location hierarchy.
     */
    public function update(
        UpdateGovernorateRequest $request,
        Governorate $governorate,
        UpdateGovernorate $action,
    ): JsonResponse {
        $updated = $action->execute($governorate, $request->validated());

        return ApiResponse::success(
            new AdminGovernorateResource($updated),
            $this->message('updated', 'governorate'),
        );
    }

    /**
     * Delete a governorate.
     *
     * Returns a null data payload on success. Referenced locations cannot be deleted and return 409 RESOURCE_IN_USE.
     */
    public function destroy(
        DestroyGovernorateRequest $request,
        Governorate $governorate,
        DeleteGovernorate $action,
    ): JsonResponse {
        $action->execute($governorate);

        return ApiResponse::success(null, $this->message('deleted', 'governorate'));
    }

    private function message(string $verb, string $resource): string
    {
        return __('messages.'.$verb, ['resource' => __('messages.resources.'.$resource)]);
    }
}
