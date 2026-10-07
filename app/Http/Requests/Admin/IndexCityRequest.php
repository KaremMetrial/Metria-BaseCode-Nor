<?php

namespace App\Http\Requests\Admin;

use App\Support\Access\PermissionRegistry;
use Illuminate\Validation\Rule;

/**
 * GET /admin/locations/cities?governorate_id={id}
 *
 * See IndexGovernorateRequest for why the parent filter is mandatory: cities are
 * the largest of the three tables and the one most likely to be imported and
 * re-imported, so an unbounded listing is both useless and expensive.
 */
class IndexCityRequest extends AdminFormRequest
{
    protected function permission(): string
    {
        return PermissionRegistry::LOCATIONS_READ;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'governorate_id' => ['required', 'integer', Rule::exists('governorates', 'id')],
        ];
    }
}
