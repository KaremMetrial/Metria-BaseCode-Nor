<?php

namespace App\Http\Requests\Admin;

/**
 * PATCH /admin/cities/{city}
 */
class UpdateCityRequest extends StoreCityRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->cityRules(partial: true);
    }
}
