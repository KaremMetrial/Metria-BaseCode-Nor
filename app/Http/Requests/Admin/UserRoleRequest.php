<?php

namespace App\Http\Requests\Admin;

use App\Support\Access\RoleRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() && $this->user()?->hasRole(RoleRegistry::SUPER_ADMIN);
    }

    public function rules(): array
    {
        return ['role' => ['required', Rule::in(RoleRegistry::all())]];
    }
}
