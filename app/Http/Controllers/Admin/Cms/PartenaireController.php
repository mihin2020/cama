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
    public function index(Request $request, CmsPartnerService $partners): Response
    {
        $type = $request->string('type')->toString();
        $activeType = in_array($type, CmsPartnerService::TYPES, true) ? $type : null;

        return Inertia::render('Admin/Cms/Partenaires/Index', [
            'items' => $partners->listForAdmin($activeType),
            'types' => CmsPartnerService::TYPES,
            'activeType' => $activeType,
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
            'image_file' => ['nullable', 'image', 'max:2048'],
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
