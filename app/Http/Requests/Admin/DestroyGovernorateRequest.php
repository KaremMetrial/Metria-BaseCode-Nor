<?php

namespace App\Http\Requests\Admin;

use App\Support\Access\PermissionRegistry;

/**
 * DELETE /admin/locations/governorates/{governorate}
 */
class DestroyGovernorateRequest extends AdminFormRequest
{
    protected function permission(): string
    {
        return PermissionRegistry::LOCATIONS_MANAGE;
    }
}
