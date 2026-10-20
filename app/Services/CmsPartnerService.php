<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsPartner;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

class CmsPartnerService
{
    public const TYPES = ['Siège CAMA', 'Antenne CAMA', 'Centre de santé', 'Partenaire'];

    /**
     * Filtre admin (liste de travail interne) : fiches sans coordonnées GPS. Elles restent
     * affichées sur le site comme les autres ; seul leur repère sur la carte reste à placer.
     */
    public const FILTER_NO_POSITION = 'À localiser';

    /** Types affichés comme prestataires de soins sur le site (hors bureaux de la CAMA). */
    public const PROVIDER_TYPES = ['Centre de santé', 'Partenaire'];

    /** Lignes par page proposées dans l'admin (tailles communes aux tableaux). */
    public const PER_PAGE_OPTIONS = Pagination::TABLE;

    /**
     * Liste admin paginée, filtrée par type (ou « À localiser ») et par recherche.
     */
    public function paginateForAdmin(?string $type, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->adminQuery($type, $search)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CmsPartner $partner) => $this->format($partner));
    }

    /**
     * Tous les points géolocalisés du filtre courant (toutes pages confondues), pour la carte admin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mapPointsForAdmin(?string $type, ?string $search): array
    {
        return $this->adminQuery($type, $search)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'name', 'type', 'city', 'address', 'description', 'image_url', 'latitude', 'longitude', 'maps_url'])
            ->map(fn (CmsPartner $partner) => [
                'id' => $partner->id,
                'lat' => (float) $partner->latitude,
                'lon' => (float) $partner->longitude,
                'name' => $partner->name,
                'type' => $partner->type,
                'city' => $partner->city,
                'description' => $partner->address ?: $partner->description,
                'mapsUrl' => $partner->maps_url,
                'imageSrc' => $partner->image_url
                    ? (str_starts_with($partner->image_url, 'http') ? $partner->image_url : '/'.ltrim($partner->image_url, '/'))
                    : null,
            ])
            ->all();
    }

    private function adminQuery(?string $type, ?string $search): Builder
    {
        $query = CmsPartner::query();

        if ($type === self::FILTER_NO_POSITION) {
            $query->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'));
        } elseif ($type && in_array($type, self::TYPES, true)) {
            $query->where('type', $type);
        }

        if ($search = trim((string) $search)) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('address', 'like', $like));
        }

        return $query;
    }

    /**
     * Nombre de fiches par onglet de filtre (une seule requête groupée).
     *
     * @return array<string, int>
     */
    public function countsForAdmin(): array
    {
        $rows = CmsPartner::query()
            ->selectRaw('type, count(*) as total, sum(case when latitude is null or longitude is null then 1 else 0 end) as sans_position')
            ->groupBy('type')
            ->get();

        $counts = ['Tous' => (int) $rows->sum('total')];
        foreach (self::TYPES as $type) {
            $counts[$type] = (int) ($rows->firstWhere('type', $type)?->total ?? 0);
        }
        $counts[self::FILTER_NO_POSITION] = (int) $rows->sum('sans_position');

        return $counts;
    }

    /**
     * Tous les lieux publiés, qu'ils soient géolocalisés ou non : une fiche sans coordonnées
     * reste listée comme les autres (seul son repère manque sur la carte).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForPublic(): array
    {
        return CmsPartner::query()
            ->where('published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsPartner $partner) => $this->toLocation($partner))
            ->all();
    }

    /**
     * Lien « Itinéraire » pour toutes les fiches : fiche Google Maps, sinon coordonnées,
     * sinon recherche par nom et ville (Google retrouve souvent le lieu de lui-même).
     */
    public function publicMapsUrl(CmsPartner $partner): string
    {
        if ($partner->maps_url) {
            return $partner->maps_url;
        }

        $query = $partner->latitude !== null && $partner->longitude !== null
            ? $partner->latitude.','.$partner->longitude
            : collect([$partner->name, $partner->city, 'Burkina Faso'])->filter()->implode(', ');

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query);
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
            'address' => $partner->address ?: ($partner->city ?? ''),
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
            'mapsUrl' => $this->publicMapsUrl($partner),
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
