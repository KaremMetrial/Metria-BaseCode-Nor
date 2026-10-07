<?php

namespace App\Http\Requests\Admin;

/**
 * PATCH /admin/governorates/{governorate}
 */
class UpdateGovernorateRequest extends StoreGovernorateRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->governorateRules(partial: true);
    }
}
