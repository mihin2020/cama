<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsActivityLog;
use Illuminate\Pagination\LengthAwarePaginator;

class CmsActivityLogService
{
    public function log(?AdminUser $admin, string $icon, string $text, ?string $authorName = null): CmsActivityLog
    {
        return CmsActivityLog::query()->create([
            'admin_user_id' => $admin?->id,
            'icon' => $icon,
            'text' => $text,
            'author_name' => $authorName ?? $admin?->display_name ?? 'CAMA',
        ]);
    }

    /**
     * Journal d'activité du studio, du plus récent au plus ancien, paginé.
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return CmsActivityLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (CmsActivityLog $log) => [
                'id' => $log->id,
                'icon' => $log->icon,
                'text' => $log->text,
                'author_name' => $log->author_name,
                'date' => $log->created_at?->format('d/m/Y H:i') ?? '',
            ]);
    }
}
