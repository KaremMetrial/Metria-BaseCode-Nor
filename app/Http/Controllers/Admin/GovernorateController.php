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
    public function index(IndexGovernorateRequest $request): JsonResponse
    {
        $governorates = Governorate::query()
            ->forCountry((int) $request->validated('country_id'))
            ->ordered()
            ->with('translations')
            ->get();

        return ApiResponse::success(AdminGovernorateResource::collection($governorates));
    }

    public function show(Governorate $governorate): JsonResponse
    {
        return ApiResponse::success(
            new AdminGovernorateResource($governorate->load('translations'))
        );
    }

    public function store(StoreGovernorateRequest $request, CreateGovernorate $action): JsonResponse
    {
        $governorate = $action->execute($request->validated());

        return ApiResponse::success(
            new AdminGovernorateResource($governorate),
            $this->message('created', 'governorate'),
            201,
        );
    }

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
