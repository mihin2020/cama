<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsKeyFigure;
use App\Services\CmsKeyFigureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChiffresClesController extends Controller
{
    public function index(CmsKeyFigureService $figures): Response
    {
        return Inertia::render('Admin/Cms/ChiffresCles/Index', [
            'items' => $figures->listForAdmin(),
        ]);
    }

    public function store(Request $request, CmsKeyFigureService $figures): RedirectResponse
    {
        $data = $this->validatedFigure($request);
        $figures->create($data, $request->user('admin'));

        return back()->with('success', 'Chiffre clé enregistré.');
    }

    public function update(Request $request, CmsKeyFigure $figure, CmsKeyFigureService $figures): RedirectResponse
    {
        $data = $this->validatedFigure($request);
        $figures->update($figure, $data, $request->user('admin'));

        return back()->with('success', 'Chiffre clé mis à jour.');
    }

    public function destroy(Request $request, CmsKeyFigure $figure, CmsKeyFigureService $figures): RedirectResponse
    {
        $figures->delete($figure, $request->user('admin'));

        return back()->with('success', 'Chiffre clé supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFigure(Request $request): array
    {
        $data = $request->validate([
            'valeur' => ['required', 'numeric'],
            'suffixe' => ['nullable', 'string', 'max:10'],
            'libelle' => ['required', 'string', 'max:255'],
            'icone' => ['nullable', 'string', 'max:80'],
            'ordre' => ['required', 'integer', 'min:1'],
        ]);

        return [
            'value' => $data['valeur'],
            'suffix' => $data['suffixe'] ?? '',
            'label' => $data['libelle'],
            'icon' => $data['icone'] ?? '',
            'sort_order' => $data['ordre'],
        ];
    }
}
