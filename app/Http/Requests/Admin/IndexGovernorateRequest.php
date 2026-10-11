<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListGovernoratesRequest;
use App\Support\Access\PermissionRegistry;

class IndexGovernorateRequest extends ListGovernoratesRequest
{
    public const FILTERS = parent::FILTERS + ['is_active' => ['boolean'], 'country_id' => ['required', 'integer', 'exists:countries,id']];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionRegistry::LOCATIONS_READ);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['country_id'] = ['required', 'integer', 'exists:countries,id'];

        return $rules;
    }
}
