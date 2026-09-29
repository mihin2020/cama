<?php

use App\Http\Controllers\Assure\Auth\EmailVerificationController;
use App\Http\Controllers\Assure\Auth\LoginController as AssureLoginController;
use App\Http\Controllers\Assure\Auth\TwoFactorController;
use App\Http\Controllers\Assure\DashboardController as AssureDashboardController;
use App\Http\Controllers\Assure\DossierWizardController;
use App\Http\Controllers\Assure\HistoriqueController;
use App\Http\Controllers\Assure\MembreController;
use App\Http\Controllers\DossierPieceController;
use App\Http\Controllers\Assure\NotificationController;
use App\Http\Controllers\Assure\ProfilController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest.assure')->group(function () {
    Route::get('/connexion', [AssureLoginController::class, 'create'])->name('login');
    Route::post('/connexion', [AssureLoginController::class, 'store'])->name('login.store');

    Route::get('/connexion/2fa', [TwoFactorController::class, 'create'])->name('login.2fa');
    Route::post('/connexion/2fa', [TwoFactorController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.2fa.store');
    Route::post('/connexion/2fa/renvoyer', [TwoFactorController::class, 'resend'])
        ->middleware('throttle:5,1')
        ->name('login.2fa.resend');
    Route::post('/connexion/2fa/annuler', [TwoFactorController::class, 'destroy'])->name('login.2fa.cancel');
});

Route::middleware('auth:assure')->group(function () {
    Route::post('/deconnexion', [AssureLoginController::class, 'destroy'])->name('logout');

    Route::get('/verification-email', [EmailVerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verification-email', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/verification-email/renvoyer', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:5,1')
        ->name('verification.resend');
});

Route::middleware(['auth:assure', 'assure.verified'])->group(function () {
    Route::get('/tableau-de-bord', AssureDashboardController::class)->name('dashboard');

    Route::get('/ma-famille', [MembreController::class, 'index'])->name('membres');
    Route::delete('/dossiers/{dossier}', [MembreController::class, 'destroy'])->name('dossiers.destroy');
    Route::post('/dossiers/{dossier}/complement', [MembreController::class, 'submitComplement'])->name('dossiers.complement');
    Route::post('/dossiers/{dossier}/retrait', [MembreController::class, 'requestWithdrawal'])->name('dossiers.retrait');
    Route::get('/ajouter-membre', [DossierWizardController::class, 'create'])->name('ajouter-membre');
    Route::post('/dossiers/brouillon', [DossierWizardController::class, 'storeDraft'])->name('dossiers.draft');
    Route::post('/dossiers/soumettre', [DossierWizardController::class, 'submit'])->name('dossiers.submit');
    Route::get('/dossiers/{dossier}/pieces/telecharger', [DossierPieceController::class, 'download'])->name('dossiers.piece.download');

    Route::get('/historique', [HistoriqueController::class, 'index'])->name('historique');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::patch('/notifications/{notification}/lu', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/tout-lu', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::patch('/profil', [ProfilController::class, 'update'])->name('profil.update');
    Route::patch('/profil/mot-de-passe', [ProfilController::class, 'updatePassword'])->name('profil.password');
    Route::patch('/profil/2fa', [ProfilController::class, 'toggle2fa'])->name('profil.2fa');
});
