<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsSlide;
use App\Services\CmsInfoBannerService;
use App\Services\CmsSlideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BanniereController extends Controller
{
    public function index(CmsSlideService $slides, CmsInfoBannerService $banner): Response
    {
        return Inertia::render('Admin/Cms/Banniere/Index', [
            'slides' => $slides->listForAdmin(),
            'banner' => $banner->getForAdmin(),
        ]);
    }

    public function updateBanner(Request $request, CmsInfoBannerService $banner): RedirectResponse
    {
        $data = $request->validate([
            'active' => ['boolean'],
            'type' => ['required', 'in:info,warning'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['active'] = $request->boolean('active');

        $banner->update($data, $request->user('admin'));

        return back()->with('success', 'Bandeau publié sur le site public.');
    }

    public function storeSlide(Request $request, CmsSlideService $slides): RedirectResponse
    {
        $data = $this->validatedSlide($request);
        $slides->create($data, $request->file('image_file'), $request->user('admin'));

        return back()->with('success', 'Slide enregistré.');
    }

    public function updateSlide(Request $request, CmsSlide $slide, CmsSlideService $slides): RedirectResponse
    {
        $data = $this->validatedSlide($request);
        $slides->update($slide, $data, $request->file('image_file'), $request->user('admin'));

        return back()->with('success', 'Slide mis à jour.');
    }

    public function destroySlide(Request $request, CmsSlide $slide, CmsSlideService $slides): RedirectResponse
    {
        $slides->delete($slide, $request->user('admin'));

        return back()->with('success', 'Slide supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSlide(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'active' => ['boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        if ($request->hasFile('image_file')) {
            unset($data['image_url']);
        }

        return $data;
    }
}
