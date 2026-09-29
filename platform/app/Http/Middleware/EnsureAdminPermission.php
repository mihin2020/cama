<?php

namespace App\Http\Middleware;

use App\Services\AdminPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    public function __construct(
        private AdminPermissionService $permissions,
    ) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $admin = $request->user('admin');

        if (! $admin) {
            abort(403, 'Accès non autorisé.');
        }

        if ($permission) {
            $this->permissions->authorize($admin, $permission);
        } else {
            $this->permissions->authorizeRoute($admin, $request->route()?->getName());
        }

        return $next($request);
    }
}
