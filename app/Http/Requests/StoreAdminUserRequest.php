<?php

namespace App\Http\Requests;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
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
        return [
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:admin_users,email'],
            'grade' => ['nullable', 'string', Rule::in(AdminPermissions::GRADES)],
            'role' => ['required', 'string', Rule::exists('admin_roles', 'slug')->where('actif', true)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::allKeys())],
            'matricule_interne' => ['nullable', 'string', 'max:50'],
        ];
    }
}
