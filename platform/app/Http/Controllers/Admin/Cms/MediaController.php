<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsMedia;
use App\Services\CmsMediaService;
use App\Services\CmsPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request, CmsMediaService $media): Response
    {
        return Inertia::render('Admin/Cms/Media/Index', [
            'items' => $media->list(
                $request->string('q')->toString() ?: null,
                $request->string('type')->toString() ?: null,
                $request->string('folder')->toString() ?: null,
                $request->string('category')->toString() ?: null,
                $request->string('extension')->toString() ?: null,
            ),
            'options' => $media->options(),
            'permissions' => app(CmsPermissionService::class)->forAdmin($request->user('admin')),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'type' => $request->string('type')->toString(),
                'folder' => $request->string('folder')->toString(),
                'category' => $request->string('category')->toString(),
                'extension' => $request->string('extension')->toString(),
            ],
        ]);
    }

    public function store(Request $request, CmsMediaService $media): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $media->store($request->file('file'), $request->user('admin'), $request->only([
            'title',
            'alt_text',
            'folder',
            'category',
            'tags',
            'description',
        ]));

        return back()->with('success', 'Média ajouté à la bibliothèque.');
    }

    public function update(Request $request, CmsMedia $media, CmsMediaService $service): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->update($media, $data, $request->user('admin'));

        return back()->with('success', 'Métadonnées du média mises à jour.');
    }

    public function destroy(Request $request, CmsMedia $media, CmsMediaService $service): RedirectResponse
    {
        app(CmsPermissionService::class)->authorize($request->user('admin'), 'canDeleteMedia');

        $service->delete($media, $request->user('admin'));

        return back()->with('success', 'Média supprimé.');
    }
}
