<?php

namespace App\Http\Requests\Admin;

use App\Support\Access\PermissionRegistry;

/**
 * DELETE /admin/locations/cities/{city}
 */
class DestroyCityRequest extends AdminFormRequest
{
    protected function permission(): string
    {
        return PermissionRegistry::LOCATIONS_MANAGE;
    }
}
