<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsPartner;
use Illuminate\Http\UploadedFile;

class CmsPartnerService
{
    public const TYPES = ['Siège CAMA', 'Antenne CAMA', 'Centre de santé', 'Partenaire'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(?string $type = null): array
    {
        $query = CmsPartner::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($type && in_array($type, self::TYPES, true)) {
            $query->where('type', $type);
        }

        return $query->get()->map(fn (CmsPartner $partner) => $this->format($partner))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForPublicMap(): array
    {
        return CmsPartner::query()
            ->where('published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsPartner $partner) => $this->toLocation($partner))
            ->filter(fn (array $location) => is_numeric($location['lat'] ?? null) && is_numeric($location['lon'] ?? null))
            ->values()
            ->all();
    }

    public function toLocation(CmsPartner $partner): array
    {
        $mapType = match ($partner->type) {
            'Siège CAMA' => 'siege',
            'Antenne CAMA' => 'antenne',
            'Centre de santé' => 'centre',
            default => 'partenaire',
        };

        return [
            'id' => 'partenaire-'.$partner->id,
            'name' => $partner->name,
            'type' => $mapType,
            'partnerType' => $partner->type,
            'address' => $partner->address
                ?: collect([$partner->city, $partner->description])->filter()->implode(' - '),
            'city' => $partner->city,
            'description' => $partner->description ?? '',
            'hours' => $partner->hours,
            'phone' => $partner->phone,
            'email' => $partner->email,
            'imageSrc' => $partner->image_url
                ? (str_starts_with($partner->image_url, 'http') ? $partner->image_url : '/'.ltrim($partner->image_url, '/'))
                : null,
            'lat' => $partner->latitude,
            'lon' => $partner->longitude,
            'mapsUrl' => $partner->maps_url,
        ];
    }

    public function format(CmsPartner $partner): array
    {
        return [
            'id' => $partner->id,
            'nom' => $partner->name,
            'type' => $partner->type,
            'ville' => $partner->city,
            'adresse' => $partner->address ?? '',
            'telephone' => $partner->phone ?? '',
            'email' => $partner->email ?? '',
            'horaires' => $partner->hours ?? '',
            'description' => $partner->description ?? '',
            'image' => $partner->image_url,
            'image_src' => $partner->image_url
                ? (str_starts_with($partner->image_url, 'http') ? $partner->image_url : '/'.ltrim($partner->image_url, '/'))
                : null,
            'lat' => $partner->latitude,
            'lon' => $partner->longitude,
            'mapsUrl' => $partner->maps_url,
            'ordre' => $partner->sort_order,
            'publie' => $partner->published,
        ];
    }

    public function create(array $data, ?UploadedFile $image, ?AdminUser $admin = null): CmsPartner
    {
        if ($image) {
            $data['image_url'] = app(CmsImageUploadService::class)->store($image, 'partners');
        }

        $partner = CmsPartner::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'handshake',
            'Partenaire « '.$partner->name.' » ajouté',
        );

        return $partner;
    }

    public function update(CmsPartner $partner, array $data, ?UploadedFile $image, ?AdminUser $admin = null): CmsPartner
    {
        if ($image) {
            app(CmsImageUploadService::class)->deleteIfUploaded($partner->image_url);
            $data['image_url'] = app(CmsImageUploadService::class)->store($image, 'partners');
        }

        $partner->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'handshake',
            'Partenaire « '.$partner->name.' » mis à jour',
        );

        return $partner->fresh();
    }

    public function delete(CmsPartner $partner, ?AdminUser $admin = null): void
    {
        $name = $partner->name;
        app(CmsImageUploadService::class)->deleteIfUploaded($partner->image_url);
        $partner->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'handshake',
            'Partenaire « '.$name.' » supprimé',
        );
    }
}
