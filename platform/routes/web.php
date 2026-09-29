<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\InstitutionController;
use App\Http\Controllers\Public\LegalController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\NewsletterTrackingController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\RegistrationController;
use App\Http\Controllers\Public\ResourceController;
use App\Http\Controllers\Public\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest.assure')->group(function () {
    Route::get('/inscription-assure', [RegistrationController::class, 'create'])->name('registration.create');
    Route::post('/inscription-assure', [RegistrationController::class, 'store'])->name('registration.store');
});

Route::prefix('espace-assure')
    ->name('assure.')
    ->group(base_path('routes/assure.php'));

Route::prefix('admin')
    ->name('admin.')
    ->group(base_path('routes/admin.php'));

Route::get('/actualites', [ArticleController::class, 'index'])->name('public.articles.index');
Route::get('/actualites/{slug}', [ArticleController::class, 'show'])->name('public.articles.show');
Route::get('/ressources', [ResourceController::class, 'index'])->name('public.resources.index');
Route::get('/contact', [ContactController::class, 'index'])->name('public.contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('public.contact.submit');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('public.newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('public.newsletter.unsubscribe');
Route::get('/newsletter/track/open/{token}', [NewsletterTrackingController::class, 'open'])->name('public.newsletter.track.open');
Route::get('/newsletter/track/click/{token}', [NewsletterTrackingController::class, 'click'])->name('public.newsletter.track.click');
Route::get('/recherche', [SearchController::class, 'index'])->name('public.search');
Route::redirect('/cartographie', '/contact#section-carte')->name('public.map');
Route::get('/apropos', [InstitutionController::class, 'about'])->name('public.about');
Route::get('/services', [InstitutionController::class, 'services'])->name('public.services');
Route::get('/mention_legales', [LegalController::class, 'legal'])->name('public.legal');
Route::get('/accessibilite', [LegalController::class, 'accessibility'])->name('public.accessibility');
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('public.pages.show');
