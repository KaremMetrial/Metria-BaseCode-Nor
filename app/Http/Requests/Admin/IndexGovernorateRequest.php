<?php

namespace App\Http\Requests\Admin;

use App\Support\Access\PermissionRegistry;
use Illuminate\Validation\Rule;

/**
 * GET /admin/locations/governorates?country_id={id}
 *
 * `country_id` is required, not optional. An unfiltered listing of every
 * governorate in the database is not a screen anybody uses -- administrators
 * drill down through a country -- and it is the query that stops being safe the
 * moment a full ISO import is loaded. Requiring the parent turns an accidental
 * full scan into a 422.
 */
class IndexGovernorateRequest extends AdminFormRequest
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
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
        ];
    }
}
