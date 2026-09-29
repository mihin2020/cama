<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AdminAuditService
{
    private const RETENTION_DAYS = 90;

    public function logConnexion(AdminUser $admin, Request $request): void
    {
        $this->record($admin, 'connexion', 'Connexion réussie', $request);
    }

    public function logDeconnexion(AdminUser $admin, Request $request): void
    {
        $this->record($admin, 'deconnexion', 'Déconnexion', $request);
    }

    /**
     * @param  array{action?: string, admin_user_id?: int|string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = AdminAuditLog::query()
            ->with('adminUser')
            ->whereIn('action', ['connexion', 'deconnexion'])
            ->orderByDesc('created_at');

        if (! empty($filters['action']) && in_array($filters['action'], ['connexion', 'deconnexion'], true)) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['admin_user_id'])) {
            $query->where('admin_user_id', (int) $filters['admin_user_id']);
        }

        return $query->paginate($perPage)->through(fn (AdminAuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'actionLabel' => $log->action === 'connexion' ? 'Connexion' : 'Déconnexion',
            'utilisateur' => $log->adminUser?->display_name_with_grade ?? 'Compte supprimé',
            'ipAddress' => $log->ip_address,
            'date' => $log->created_at?->format('d/m/Y H:i'),
        ]);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    public function userFilterOptions(): array
    {
        return AdminUser::query()
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->map(fn (AdminUser $user) => [
                'id' => $user->id,
                'label' => $user->display_name_with_grade,
            ])
            ->all();
    }

    private function record(AdminUser $admin, string $action, string $description, Request $request): void
    {
        AdminAuditLog::query()->create([
            'admin_user_id' => $admin->id,
            'action' => $action,
            'module' => 'session',
            'description' => $description,
            'metadata' => null,
            'ip_address' => $request->ip(),
        ]);

        $this->purgeOldEntries();
    }

    private function purgeOldEntries(): void
    {
        AdminAuditLog::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->delete();
    }
}
