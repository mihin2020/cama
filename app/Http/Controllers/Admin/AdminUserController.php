<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\AdminUser;
use App\Models\Role;
use App\Services\AdminUserService;
use App\Support\AdminPermissions;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request, AdminUserService $service): Response|RedirectResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');
        $search = mb_substr(trim($request->string('q')->toString()), 0, 100) ?: null;
        $paginator = $service->paginateForActor($actor, $search, Pagination::perPage($request));

        if (Pagination::isBeyondLastPage($paginator)) {
            return redirect()->route('admin.utilisateurs', [...$request->query(), 'page' => $paginator->lastPage()]);
        }

        $assignable = $service->assignableRoles($actor);

        return Inertia::render('Admin/Utilisateurs/Index', [
            'users' => $paginator->items(),
            'pagination' => Pagination::meta($paginator),
            'search' => $search ?? '',
            'permissionsCatalog' => AdminPermissions::catalog(),
            'actorId' => $actor->id,
            'actorIsAdmin' => $actor->isAdministrateur(),
            // Permissions de chaque rôle attribuable : affichées (lecture seule) sur la fiche du compte.
            'rolePresets' => $assignable->mapWithKeys(fn (Role $role) => [$role->slug => AdminPermissions::expand($role->permissionKeys())])->all(),
            'grades' => AdminPermissions::GRADES,
            'assignableRoles' => $assignable
                ->map(fn (Role $role) => ['value' => $role->slug, 'label' => $role->nom, 'description' => $role->description])
                ->all(),
        ]);
    }

    public function store(StoreAdminUserRequest $request, AdminUserService $service): RedirectResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user('admin');

        $result = $service->create($request->validated(), $actor);

        if ($result['invitationError'] === null) {
            return back()->with('success', "Compte créé : l'invitation part vers {$result['user']->email}. En cas d'échec d'envoi, vous serez prévenu dans vos notifications.");
        }

        return back()->with('warning', "Compte créé pour {$result['user']->email}, mais l'invitation n'est pas partie : {$result['invitationError']}");
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

        $error = $service->resendInvitation($adminUser, $actor);

        if ($error === null) {
            return back()->with('success', "L'invitation repart vers {$adminUser->email}. En cas d'échec d'envoi, vous serez prévenu dans vos notifications.");
        }

        return back()->with('warning', "L'invitation n'a pas pu être envoyée à {$adminUser->email} : {$error}");
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
