<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsMenuItem;
use App\Services\CmsMenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(CmsMenuService $menus): Response
    {
        return Inertia::render('Admin/Cms/Menus/Index', [
            'items' => $menus->listForAdmin(),
            'pages' => $menus->availablePages(),
        ]);
    }

    public function addPages(Request $request, CmsMenuService $menus): RedirectResponse
    {
        $data = $request->validate([
            'page_ids' => ['required', 'array', 'min:1'],
            'page_ids.*' => ['integer', 'exists:cms_pages,id'],
        ]);

        $menus->addPages($data['page_ids'], $request->user('admin'));

        return back()->with('success', 'Pages ajoutées au menu.');
    }

    public function addCustom(Request $request, CmsMenuService $menus): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'url' => ['nullable', 'string', 'max:1000'],
        ]);

        $menus->addCustomLink(trim($data['label']), trim($data['url'] ?? ''), $request->user('admin'));

        return back()->with('success', 'Lien ajouté au menu.');
    }

    public function saveStructure(Request $request, CmsMenuService $menus): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:cms_menu_items,id'],
            'items.*.label' => ['required', 'string', 'max:160'],
            'items.*.depth' => ['required', 'integer', 'min:0', 'max:1'],
        ]);

        $menus->saveStructure($data['items'], $request->user('admin'));

        return back()->with('success', 'Menu enregistré.');
    }

    public function destroy(Request $request, CmsMenuItem $menuItem, CmsMenuService $menus): RedirectResponse
    {
        $menus->delete($menuItem, $request->user('admin'));

        return back()->with('success', 'Élément retiré du menu.');
    }
}
