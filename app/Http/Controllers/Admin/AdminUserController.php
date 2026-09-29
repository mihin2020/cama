<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\AdminUser;
use App\Services\AdminUserService;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request, AdminUserService $service): Response
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');

        $assignableRoles = $actor->role === AdminRole::Administrateur
            ? [
                ['value' => AdminRole::Gestionnaire->value, 'label' => 'Gestionnaire'],
                ['value' => AdminRole::Superviseur->value, 'label' => 'Superviseur'],
                ['value' => AdminRole::Administrateur->value, 'label' => 'Administrateur'],
            ]
            : [
                ['value' => AdminRole::Gestionnaire->value, 'label' => 'Gestionnaire'],
                ['value' => AdminRole::Superviseur->value, 'label' => 'Superviseur'],
            ];

        return Inertia::render('Admin/Utilisateurs/Index', [
            'users' => $service->listForActor($actor),
            'permissionsCatalog' => AdminPermissions::catalog(),
            'rolePresets' => [
                AdminRole::Gestionnaire->value => AdminPermissions::presetForRole(AdminRole::Gestionnaire),
                AdminRole::Superviseur->value => AdminPermissions::presetForRole(AdminRole::Superviseur),
                AdminRole::Administrateur->value => AdminPermissions::presetForRole(AdminRole::Administrateur),
            ],
            'grades' => AdminPermissions::GRADES,
            'assignableRoles' => $assignableRoles,
        ]);
    }

    public function store(StoreAdminUserRequest $request, AdminUserService $service): RedirectResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');

        $result = $service->create($request->validated(), $actor);

        if ($result['invitationSent']) {
            return back()->with('success', "Invitation envoyée à {$result['user']->email}.");
        }

        return back()->with('warning', "Compte créé pour {$result['user']->email}, mais l'e-mail n'a pas pu être envoyé. Vérifiez la configuration SMTP.");
    }

    public function update(UpdateAdminUserRequest $request, AdminUser $adminUser, AdminUserService $service): RedirectResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');

        $service->update($adminUser, $request->validated(), $actor);

        return back()->with('success', 'Compte interne mis à jour.');
    }

    public function toggleActif(Request $request, AdminUser $adminUser, AdminUserService $service): RedirectResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');

        $user = $service->toggleActif($adminUser, $actor);

        return back()->with('success', $user->actif ? 'Compte activé.' : 'Compte désactivé.');
    }

    public function resendInvitation(Request $request, AdminUser $adminUser, AdminUserService $service): RedirectResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');

        $sent = $service->resendInvitation($adminUser, $actor);

        if ($sent) {
            return back()->with('success', "Invitation renvoyée à {$adminUser->email}.");
        }

        return back()->with('warning', "L'invitation n'a pas pu être envoyée à {$adminUser->email}. Vérifiez la configuration SMTP.");
    }

    public function destroy(Request $request, AdminUser $adminUser, AdminUserService $service): RedirectResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');

        $email = $adminUser->email;
        $service->delete($adminUser, $actor);

        return back()->with('success', "Compte {$email} supprimé.");
    }
}
