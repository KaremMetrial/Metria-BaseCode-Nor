<?php

namespace App\Http\Requests\Admin;

use App\Support\Access\PermissionRegistry;

/**
 * DELETE /admin/locations/countries/{country}
 *
 * Nothing to validate -- the route parameter is the whole payload. The request
 * exists so authorization is checked next to the boundary it protects, and so
 * the permission can never be granted by the route middleware alone without the
 * request agreeing.
 */
class DestroyCountryRequest extends AdminFormRequest
{
    protected function permission(): string
    {
        return PermissionRegistry::LOCATIONS_MANAGE;
    }
}
