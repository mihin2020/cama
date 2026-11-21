<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FifField;
use App\Models\FifSection;
use App\Services\FifSchemaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Constructeur du formulaire FIF : sections et champs de l'assuré, du conjoint et de l'enfant. */
class FifController extends Controller
{
    public function index(FifSchemaService $fif): Response
    {
        return Inertia::render('Admin/Fif/Index', [
            'cibles' => collect(FifSection::CIBLES)
                ->map(fn (string $titre, string $cible) => ['cible' => $cible, 'titre' => $titre, 'sections' => $fif->adminSchema($cible)])
                ->values()
                ->all(),
            'types' => collect(FifField::TYPES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'choiceTypes' => FifField::CHOICE_TYPES,
            'maxOptions' => FifSchemaService::MAX_OPTIONS,
        ]);
    }

    public function storeSection(Request $request, string $cible, FifSchemaService $fif): RedirectResponse
    {
        abort_unless(in_array($cible, FifSchemaService::cibles(), true), 404);

        $fif->createSection($cible, $this->sectionData($request));

        return back()->with('success', 'Section ajoutée.');
    }

    public function updateSection(Request $request, FifSection $section, FifSchemaService $fif): RedirectResponse
    {
        $fif->updateSection($section, $this->sectionData($request));

        return back()->with('success', 'Section mise à jour.');
    }

    public function destroySection(FifSection $section, FifSchemaService $fif): RedirectResponse
    {
        $fif->deleteSection($section);

        return back()->with('success', 'Section supprimée. Les données déjà saisies sont conservées.');
    }

    public function moveSection(Request $request, FifSection $section, FifSchemaService $fif): RedirectResponse
    {
        $fif->moveSection($section, $this->direction($request));

        return back();
    }

    public function storeField(Request $request, FifSection $section, FifSchemaService $fif): RedirectResponse
    {
        $fif->createField($section, $this->fieldData($request));

        return back()->with('success', 'Champ ajouté.');
    }

    public function updateField(Request $request, FifField $field, FifSchemaService $fif): RedirectResponse
    {
        $fif->updateField($field, $this->fieldData($request));

        return back()->with('success', 'Champ mis à jour.');
    }

    public function destroyField(FifField $field, FifSchemaService $fif): RedirectResponse
    {
        $fif->deleteField($field);

        return back()->with('success', 'Champ supprimé. Les données déjà saisies sont conservées.');
    }

    public function moveField(Request $request, FifField $field, FifSchemaService $fif): RedirectResponse
    {
        $fif->moveField($field, $this->direction($request));

        return back();
    }

    /** @return array<string, mixed> */
    private function sectionData(Request $request): array
    {
        return $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'visible' => ['nullable', 'boolean'],
        ], ['titre.required' => 'Le titre de la section est obligatoire.']);
    }

    /** @return array<string, mixed> */
    private function fieldData(Request $request): array
    {
        return $request->validate([
            'libelle' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(FifField::TYPES))],
            'options' => ['nullable', 'array', 'max:'.FifSchemaService::MAX_OPTIONS],
            'options.*' => ['nullable', 'string', 'max:100'],
            'aide' => ['nullable', 'string', 'max:300'],
            'obligatoire' => ['nullable', 'boolean'],
            'visible' => ['nullable', 'boolean'],
            'imprimer_pdf' => ['nullable', 'boolean'],
        ], ['libelle.required' => 'Le libellé du champ est obligatoire.']);
    }

    private function direction(Request $request): string
    {
        return $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
    }
}
