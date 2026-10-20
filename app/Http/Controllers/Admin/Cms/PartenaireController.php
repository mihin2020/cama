<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsPartner;
use App\Services\CmsPartnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartenaireController extends Controller
{
    public function index(Request $request, CmsPartnerService $partners): Response|RedirectResponse
    {
        $type = $request->string('type')->toString();
        $filters = [...CmsPartnerService::TYPES, CmsPartnerService::FILTER_NO_POSITION];
        $activeType = in_array($type, $filters, true) ? $type : null;
        $search = mb_substr(trim($request->string('q')->toString()), 0, 100) ?: null;
        $perPage = in_array($request->integer('per_page'), CmsPartnerService::PER_PAGE_OPTIONS, true)
            ? $request->integer('per_page')
            : CmsPartnerService::PER_PAGE_OPTIONS[0];

        $paginator = $partners->paginateForAdmin($activeType, $search, $perPage);

        // Page demandée au-delà de la dernière (ex. après une suppression) : retour à la dernière page.
        if ($paginator->isEmpty() && $paginator->currentPage() > 1) {
            return redirect()->route('admin.cms.partenaires', [...$request->query(), 'page' => $paginator->lastPage()]);
        }

        return Inertia::render('Admin/Cms/Partenaires/Index', [
            'items' => $paginator->items(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'perPageOptions' => CmsPartnerService::PER_PAGE_OPTIONS,
            'mapPoints' => $partners->mapPointsForAdmin($activeType, $search),
            'types' => CmsPartnerService::TYPES,
            'activeType' => $activeType,
            'search' => $search ?? '',
            'counts' => $partners->countsForAdmin(),
            'noPositionFilter' => CmsPartnerService::FILTER_NO_POSITION,
        ]);
    }

    public function store(Request $request, CmsPartnerService $partners): RedirectResponse
    {
        $data = $this->validatedPartner($request);
        $partners->create($data, $request->file('image_file'), $request->user('admin'));

        return back()->with('success', 'Partenaire enregistré.');
    }

    public function update(Request $request, CmsPartner $partner, CmsPartnerService $partners): RedirectResponse
    {
        $data = $this->validatedPartner($request);
        $partners->update($partner, $data, $request->file('image_file'), $request->user('admin'));

        return back()->with('success', 'Partenaire mis à jour.');
    }

    public function destroy(Request $request, CmsPartner $partner, CmsPartnerService $partners): RedirectResponse
    {
        $partners->delete($partner, $request->user('admin'));

        return back()->with('success', 'Partenaire supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPartner(Request $request): array
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', Rule::in(CmsPartnerService::TYPES)],
            'ville' => ['nullable', 'string', 'max:120'],
            'adresse' => ['nullable', 'string', 'max:500'],
            'telephone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:190'],
            'horaires' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'mapsUrl' => ['nullable', 'string', 'max:1000', 'url'],
            'ordre' => ['required', 'integer', 'min:1'],
            'publie' => ['boolean'],
        ]);

        $payload = [
            'name' => $data['nom'],
            'type' => $data['type'],
            'city' => $data['ville'] ?? null,
            'address' => $data['adresse'] ?? null,
            'phone' => $data['telephone'] ?? null,
            'email' => $data['email'] ?? null,
            'hours' => $data['horaires'] ?? null,
            'description' => $data['description'] ?? null,
            'latitude' => $data['lat'] ?? null,
            'longitude' => $data['lon'] ?? null,
            'maps_url' => $data['mapsUrl'] ?? null,
            'sort_order' => $data['ordre'],
            'published' => $request->boolean('publie'),
        ];

        $url = trim($data['image_url'] ?? '');
        if ($url !== '' && ! $request->hasFile('image_file')) {
            $payload['image_url'] = $url;
        }

        return $payload;
    }
}
