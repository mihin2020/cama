<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsKeyFigure;

class CmsKeyFigureService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(): array
    {
        return CmsKeyFigure::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsKeyFigure $item) => $this->format($item))
            ->all();
    }

    public function format(CmsKeyFigure $item): array
    {
        return [
            'id' => $item->id,
            'valeur' => $item->value,
            'suffixe' => $item->suffix ?? '',
            'libelle' => $item->label,
            'icone' => $item->icon ?? '',
            'ordre' => $item->sort_order,
        ];
    }

    public function create(array $data, ?AdminUser $admin = null): CmsKeyFigure
    {
        $item = CmsKeyFigure::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'monitoring',
            'Chiffre clé « '.$item->label.' » ajouté',
        );

        return $item;
    }

    public function update(CmsKeyFigure $item, array $data, ?AdminUser $admin = null): CmsKeyFigure
    {
        $item->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'monitoring',
            'Chiffre clé « '.$item->label.' » mis à jour',
        );

        return $item->fresh();
    }

    public function delete(CmsKeyFigure $item, ?AdminUser $admin = null): void
    {
        $label = $item->label;
        $item->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'monitoring',
            'Chiffre clé « '.$label.' » supprimé',
        );
    }
}
