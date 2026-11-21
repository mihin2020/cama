<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Role;
use App\Services\AdminPermissionService;
use App\Services\AdminRoleService;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request, AdminRoleService $service): Response
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $service->list(),
            'permissionsCatalog' => AdminPermissions::catalog(),
            // Permission réservée => [niveau interne minimal, texte] (cases grisées à l'écran).
            'restrictions' => collect(AdminPermissions::MIN_RANK)
                ->map(fn (int $rank, string $key) => ['minRank' => $rank, 'label' => AdminPermissions::restrictionLabel($key)])
                ->all(),
            'customRank' => Role::RANG_PERSONNALISE,
            'grantablePermissions' => AdminPermissions::grantableBy(
                $actor->isAdministrateur(),
                app(AdminPermissionService::class)->expanded($actor),
            ),
            'actorRole' => $actor->role,
        ]);
    }

    public function store(Request $request, AdminRoleService $service): RedirectResponse
    {
        $role = $service->create($this->validated($request), $request->user('admin'));

        return back()->with('success', "Rôle « {$role->nom} » créé.");
    }

    public function update(Request $request, Role $adminRole, AdminRoleService $service): RedirectResponse
    {
        $data = $this->validated($request, $adminRole);
        $updated = $service->update($adminRole, $data, $request->user('admin'));

        $message = "Rôle « {$adminRole->nom} » mis à jour.";
        if (! empty($data['apply_to_users'])) {
            $message .= " Permissions réappliquées à {$updated} compte(s).";
        }

        return back()->with('success', $message);
    }

    public function toggle(Request $request, Role $adminRole, AdminRoleService $service): RedirectResponse
    {
        $role = $service->toggle($adminRole, $request->user('admin'));

        return back()->with('success', $role->actif ? "Rôle « {$role->nom} » activé." : "Rôle « {$role->nom} » désactivé : ses comptes ne peuvent plus se connecter.");
    }

    public function destroy(Role $adminRole, AdminRoleService $service): RedirectResponse
    {
        $nom = $adminRole->nom;
        $service->delete($adminRole);

        return back()->with('success', "Rôle « {$nom} » supprimé.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('admin_roles', 'nom')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::allKeys())],
            'apply_to_users' => ['nullable', 'boolean'],
        ], [
            'nom.unique' => 'Un rôle porte déjà ce nom.',
        ]);
    }
}
