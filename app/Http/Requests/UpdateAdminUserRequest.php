<?php

namespace App\Http\Requests;

use App\Enums\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user('admin')?->role, [AdminRole::Superviseur, AdminRole::Administrateur], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $adminUser = $this->route('adminUser');

        return [
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admin_users', 'email')->ignore($adminUser?->id)],
            'grade' => ['nullable', 'string', Rule::in(AdminPermissions::GRADES)],
            'role' => ['required', Rule::enum(AdminRole::class)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::allKeys())],
            'matricule_interne' => ['nullable', 'string', 'max:50'],
        ];
    }
}
