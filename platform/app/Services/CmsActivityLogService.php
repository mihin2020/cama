<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsActivityLog;

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
     * @return array<int, array{id: int, icon: string, text: string, author_name: string, date: string}>
     */
    public function recent(int $limit = 10): array
    {
        return CmsActivityLog::query()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (CmsActivityLog $log) => [
                'id' => $log->id,
                'icon' => $log->icon,
                'text' => $log->text,
                'author_name' => $log->author_name,
                'date' => $log->created_at?->format('d/m/Y H:i') ?? '',
            ])
            ->all();
    }
}
