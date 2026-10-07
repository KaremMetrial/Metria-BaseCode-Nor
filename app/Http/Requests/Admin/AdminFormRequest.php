<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base class for admin writes.
 *
 * Authorization is checked here *as well as* on the route, deliberately. The
 * route middleware is the primary control, but a new admin route that forgets
 * `permission:...` would silently expose the endpoint; authorizing in the
 * request means the write is still refused. Route prefixes are routing, never
 * security.
 */
abstract class AdminFormRequest extends FormRequest
{
    /**
     * The permission required to perform this write.
     */
    abstract protected function permission(): string;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->permission());
    }
}
