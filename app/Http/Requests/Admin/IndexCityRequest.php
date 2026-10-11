<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListCitiesRequest;
use App\Support\Access\PermissionRegistry;

class IndexCityRequest extends ListCitiesRequest
{
    public const FILTERS = parent::FILTERS + ['is_active' => ['boolean'], 'governorate_id' => ['required', 'integer', 'exists:governorates,id']];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionRegistry::LOCATIONS_READ);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['governorate_id'] = ['required', 'integer', 'exists:governorates,id'];

        return $rules;
    }
}
