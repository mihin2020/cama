<?php

namespace App\Http\Requests;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('admin');

        return $actor !== null && ($actor->isAdministrateur() || $actor->hasPermission('users.manage'));
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
            'role' => ['required', 'string', Rule::exists('admin_roles', 'slug')->where('actif', true)],
            'matricule_interne' => ['nullable', 'string', 'max:50'],
        ];
    }
}
