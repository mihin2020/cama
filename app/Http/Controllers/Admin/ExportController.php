<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(ExportService $service): Response
    {
        return Inertia::render('Admin/Exports/Index', [
            'catalog' => $service->catalog(auth('admin')->user()),
        ]);
    }

    /**
     * Le fichier est demandé en arrière-plan par la page : une erreur revient en JSON (422)
     * pour être affichée telle quelle, jamais sous forme de redirection.
     */
    public function download(Request $request, ExportService $service): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        try {
            return $this->export($request, $service);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        }
    }

    private function export(Request $request, ExportService $service): StreamedResponse|BinaryFileResponse
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(array_keys(ExportService::FORMATS))],
            'datasets' => ['required', 'array', 'min:1'],
            'datasets.*' => ['nullable', 'array'],
            'datasets.*.*' => ['string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'structure' => ['boolean'],
        ], [
            'datasets.required' => 'Cochez au moins un élément à exporter.',
            'to.after_or_equal' => 'La date de fin doit être postérieure à la date de début.',
        ]);

        return $service->export(
            auth('admin')->user(),
            $data['datasets'],
            $data['format'],
            $data['from'] ?? null,
            $data['to'] ?? null,
            (bool) ($data['structure'] ?? true),
        );
    }

    public function dossierPdf(Request $request, ExportService $service): JsonResponse
    {
        $data = $request->validate(['ref' => ['required', 'string', 'max:50']]);
        $payload = $service->dossierForPdf($data['ref']);

        if (! $payload) {
            return response()->json(['error' => 'Aucun dossier ne correspond à cette référence.'], 404);
        }

        $service->log(auth('admin')->user(), "Fiche dossier {$data['ref']}", 'PDF');

        return response()->json($payload);
    }
}
