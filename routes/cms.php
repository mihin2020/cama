<?php



use App\Http\Controllers\Admin\Cms\ArticleController as CmsArticleController;
use App\Http\Controllers\Admin\Cms\RessourceController;
use App\Http\Controllers\Admin\Cms\ChiffresClesController;
use App\Http\Controllers\Admin\Cms\BanniereController as CmsBanniereController;
use App\Http\Controllers\Admin\Cms\DashboardController as CmsDashboardController;
use App\Http\Controllers\Admin\Cms\FooterController as CmsFooterController;

use App\Http\Controllers\Admin\Cms\FaqController as CmsFaqController;
use App\Http\Controllers\Admin\Cms\ContactMessageController as CmsContactMessageController;
use App\Http\Controllers\Admin\Cms\MediaController as CmsMediaController;
use App\Http\Controllers\Admin\Cms\MenuController as CmsMenuController;
use App\Http\Controllers\Admin\Cms\NewsletterController as CmsNewsletterController;
use App\Http\Controllers\Admin\Cms\PageController as CmsPageController;
use App\Http\Controllers\Admin\Cms\PartenaireController as CmsPartenaireController;

use App\Http\Controllers\Admin\Cms\PlaceholderController as CmsPlaceholderController;

use Illuminate\Support\Facades\Route;



Route::prefix('cms')

    ->name('cms.')

    ->group(function () {

        Route::get('/', [CmsDashboardController::class, 'index'])->name('dashboard');



        Route::get('/faq', [CmsFaqController::class, 'index'])->name('faq');

        Route::post('/faq', [CmsFaqController::class, 'store'])->name('faq.store');

        Route::put('/faq/{faq}', [CmsFaqController::class, 'update'])->name('faq.update');

        Route::delete('/faq/{faq}', [CmsFaqController::class, 'destroy'])->name('faq.destroy');

        Route::post('/faq/categories', [CmsFaqController::class, 'storeCategory'])->name('faq.categories.store');

        Route::put('/faq/categories/{category}', [CmsFaqController::class, 'updateCategory'])->name('faq.categories.update');

        Route::delete('/faq/categories/{category}', [CmsFaqController::class, 'destroyCategory'])->name('faq.categories.destroy');



        Route::get('/actualites', [CmsArticleController::class, 'index'])->name('actualites');
        Route::post('/actualites', [CmsArticleController::class, 'store'])->name('actualites.store');
        Route::put('/actualites/{article}', [CmsArticleController::class, 'update'])->name('actualites.update');
        Route::delete('/actualites/{article}', [CmsArticleController::class, 'destroy'])->name('actualites.destroy');
        Route::post('/actualites/categories', [CmsArticleController::class, 'storeCategory'])->name('actualites.categories.store');
        Route::put('/actualites/categories/{category}', [CmsArticleController::class, 'updateCategory'])->name('actualites.categories.update');
        Route::delete('/actualites/categories/{category}', [CmsArticleController::class, 'destroyCategory'])->name('actualites.categories.destroy');

        Route::get('/banniere', [CmsBanniereController::class, 'index'])->name('banniere');
        Route::put('/banniere/bandeau', [CmsBanniereController::class, 'updateBanner'])->name('banniere.bandeau');
        Route::post('/banniere/slides', [CmsBanniereController::class, 'storeSlide'])->name('banniere.slides.store');
        Route::put('/banniere/slides/{slide}', [CmsBanniereController::class, 'updateSlide'])->name('banniere.slides.update');
        Route::delete('/banniere/slides/{slide}', [CmsBanniereController::class, 'destroySlide'])->name('banniere.slides.destroy');

        Route::get('/chiffres-cles', [ChiffresClesController::class, 'index'])->name('chiffres_cles');
        Route::post('/chiffres-cles', [ChiffresClesController::class, 'store'])->name('chiffres_cles.store');
        Route::put('/chiffres-cles/{figure}', [ChiffresClesController::class, 'update'])->name('chiffres_cles.update');
        Route::delete('/chiffres-cles/{figure}', [ChiffresClesController::class, 'destroy'])->name('chiffres_cles.destroy');

        Route::get('/ressources', [RessourceController::class, 'index'])->name('ressources');
        Route::post('/ressources', [RessourceController::class, 'store'])->name('ressources.store');
        Route::put('/ressources/{resource}', [RessourceController::class, 'update'])->name('ressources.update');
        Route::delete('/ressources/{resource}', [RessourceController::class, 'destroy'])->name('ressources.destroy');
        Route::post('/ressources/categories', [RessourceController::class, 'storeCategory'])->name('ressources.categories.store');
        Route::delete('/ressources/categories/{category}', [RessourceController::class, 'destroyCategory'])->name('ressources.categories.destroy');

        Route::get('/partenaires', [CmsPartenaireController::class, 'index'])->name('partenaires');
        Route::post('/partenaires', [CmsPartenaireController::class, 'store'])->name('partenaires.store');
        Route::put('/partenaires/{partner}', [CmsPartenaireController::class, 'update'])->name('partenaires.update');
        Route::delete('/partenaires/{partner}', [CmsPartenaireController::class, 'destroy'])->name('partenaires.destroy');

        Route::get('/pied-de-page', [CmsFooterController::class, 'index'])->name('footer');
        Route::put('/pied-de-page', [CmsFooterController::class, 'update'])->name('footer.update');

        Route::get('/menus', [CmsMenuController::class, 'index'])->name('menus');
        Route::post('/menus/pages', [CmsMenuController::class, 'addPages'])->name('menus.pages.store');
        Route::post('/menus/custom', [CmsMenuController::class, 'addCustom'])->name('menus.custom.store');
        Route::put('/menus/structure', [CmsMenuController::class, 'saveStructure'])->name('menus.structure');
        Route::delete('/menus/{menuItem}', [CmsMenuController::class, 'destroy'])->name('menus.destroy');

        Route::get('/pages', [CmsPageController::class, 'index'])->name('pages');
        Route::post('/pages', [CmsPageController::class, 'store'])->name('pages.store');
        Route::put('/pages/{page}', [CmsPageController::class, 'update'])->name('pages.update');
        Route::put('/pages/{page}/statut', [CmsPageController::class, 'toggleStatus'])->name('pages.status');
        Route::post('/pages/{page}/dupliquer', [CmsPageController::class, 'duplicate'])->name('pages.duplicate');
        Route::delete('/pages/{page}', [CmsPageController::class, 'destroy'])->name('pages.destroy');

        Route::get('/media', [CmsMediaController::class, 'index'])->name('media');
        Route::post('/media', [CmsMediaController::class, 'store'])->name('media.store');
        Route::put('/media/{media}', [CmsMediaController::class, 'update'])->name('media.update');
        Route::delete('/media/{media}', [CmsMediaController::class, 'destroy'])->name('media.destroy');

        Route::get('/contacts', [CmsContactMessageController::class, 'index'])->name('contacts');
        Route::put('/contacts/{message}', [CmsContactMessageController::class, 'update'])->name('contacts.update');
        Route::put('/contacts/settings/recipients', [CmsContactMessageController::class, 'updateRecipients'])->name('contacts.settings.recipients');
        Route::post('/contacts/archive', [CmsContactMessageController::class, 'archive'])->name('contacts.archive');
        Route::delete('/contacts/archived', [CmsContactMessageController::class, 'purgeArchived'])->name('contacts.purge');
        Route::get('/contacts/stats/unread', [CmsContactMessageController::class, 'unreadCount'])->name('contacts.stats.unread');

        Route::get('/newsletter', [CmsNewsletterController::class, 'index'])->name('newsletter');
        Route::get('/newsletter/campaigns/create', [CmsNewsletterController::class, 'createCampaign'])->name('newsletter.campaigns.create');
        Route::get('/newsletter/campaigns/{campaign}/edit', [CmsNewsletterController::class, 'editCampaign'])->name('newsletter.campaigns.edit');
        Route::post('/newsletter/campaigns', [CmsNewsletterController::class, 'storeCampaign'])->name('newsletter.campaigns.store');
        Route::put('/newsletter/campaigns/{campaign}', [CmsNewsletterController::class, 'updateCampaign'])->name('newsletter.campaigns.update');
        Route::post('/newsletter/campaigns/{campaign}/send', [CmsNewsletterController::class, 'sendCampaign'])->name('newsletter.campaigns.send');
        Route::delete('/newsletter/campaigns/{campaign}', [CmsNewsletterController::class, 'destroyCampaign'])->name('newsletter.campaigns.destroy');
        Route::put('/newsletter/subscribers/{subscriber}', [CmsNewsletterController::class, 'updateSubscriber'])->name('newsletter.subscribers.update');
        Route::post('/newsletter/clean', [CmsNewsletterController::class, 'cleanList'])->name('newsletter.clean');
        Route::put('/newsletter/{subscriber}/toggle', [CmsNewsletterController::class, 'toggle'])->name('newsletter.toggle');
        Route::get('/newsletter/export', [CmsNewsletterController::class, 'exportCsv'])->name('newsletter.export');
        Route::post('/newsletter/batches', [CmsNewsletterController::class, 'storeBatch'])->name('newsletter.batches.store');
        Route::delete('/newsletter/batches/{batch}', [CmsNewsletterController::class, 'destroyBatch'])->name('newsletter.batches.destroy');

        Route::get('/page-builder', [CmsPageController::class, 'builder'])->name('page_builder');
        Route::put('/page-builder/{page}/sections', [CmsPageController::class, 'saveSections'])->name('page_builder.sections');
        Route::put('/page-builder/{page}/publish', [CmsPageController::class, 'publishFromBuilder'])->name('page_builder.publish');
        Route::post('/page-builder/{page}/versions/{version}/restore', [CmsPageController::class, 'restoreVersion'])->name('page_builder.versions.restore');
        Route::post('/page-builder/{page}/versions/{version}/duplicate', [CmsPageController::class, 'duplicateVersion'])->name('page_builder.versions.duplicate');

        $modules = [

        ];



        foreach ($modules as $slug => $config) {

            Route::get("/{$slug}", CmsPlaceholderController::class)

                ->defaults('module', $config['label'])

                ->defaults('nav', $config['nav'])

                ->name(str_replace('-', '_', $slug));

        }

    });

