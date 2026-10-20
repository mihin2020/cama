<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AssureController;
use App\Http\Controllers\Admin\Auth\InvitationController;
use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\Auth\TwoFactorController as AdminTwoFactorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DossierController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\InscriptionController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ParametresController;
use App\Http\Controllers\Admin\ProfilController;
use App\Http\Controllers\Admin\SessionController as AdminSessionController;
use App\Http\Controllers\DossierPieceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest.admin')->group(function () {
    Route::get('/connexion', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/connexion', [AdminLoginController::class, 'store'])->name('login.store');
    Route::get('/connexion/2fa', [AdminTwoFactorController::class, 'create'])->name('login.2fa');
    Route::post('/connexion/2fa', [AdminTwoFactorController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.2fa.store');
    Route::post('/connexion/2fa/renvoyer', [AdminTwoFactorController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('login.2fa.resend');
    Route::post('/connexion/2fa/annuler', [AdminTwoFactorController::class, 'destroy'])->name('login.2fa.cancel');
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invitation', [InvitationController::class, 'store'])->name('invitation.store');
});

// auth.session : un changement de mot de passe (ou « déconnecter mes autres appareils »)
// invalide les autres sessions et les cookies « Se souvenir de moi » déjà émis.
Route::middleware(['auth:admin', 'auth.session', 'admin.active', 'admin.idle', 'admin.permission'])->group(function () {
    Route::get('/tableau-de-bord', AdminDashboardController::class)->name('dashboard');
    Route::post('/deconnexion', [AdminLoginController::class, 'destroy'])->name('logout');
    Route::post('/session/ping', [AdminSessionController::class, 'ping'])->name('session.ping');

    Route::get('/dossiers', [DossierController::class, 'index'])->name('dossiers');
    Route::post('/dossiers/affecter', [DossierController::class, 'assign'])->name('dossiers.assign');
    Route::post('/dossiers/affecter-lot', [DossierController::class, 'batchAssign'])->name('dossiers.batch-assign');
    Route::post('/dossiers/{dossier}/valider', [DossierController::class, 'validate'])->name('dossiers.validate');
    Route::post('/dossiers/{dossier}/refuser', [DossierController::class, 'reject'])->name('dossiers.reject');
    Route::post('/dossiers/{dossier}/retrait', [DossierController::class, 'confirmWithdrawal'])->name('dossiers.retrait');
    Route::post('/dossiers/{dossier}/complement', [DossierController::class, 'complement'])->name('dossiers.complement');
    Route::post('/dossiers/{dossier}/en-attente', [DossierController::class, 'enAttente'])->name('dossiers.en-attente');
    Route::post('/dossiers/{dossier}/message', [DossierController::class, 'message'])->name('dossiers.message');
    Route::get('/dossiers/{dossier}/pieces/telecharger', [DossierPieceController::class, 'download'])->name('dossiers.piece.download');

    Route::get('/assures', [AssureController::class, 'index'])->name('assures');
    Route::post('/assures/{assure}/statut', [AssureController::class, 'updateStatut'])->name('assures.statut');

    // Accès décidé par les permissions cochées pour chaque compte (AdminPermissions::permissionForRoute),
    // plus de verrou par rôle : un compte n'a que ce qui lui a été attribué.
    Route::get('/inscriptions', [InscriptionController::class, 'index'])->name('inscriptions');
    Route::patch('/inscriptions/{assure}/identifiants', [InscriptionController::class, 'updateIdentifiers'])->name('inscriptions.identifiers');
    Route::post('/inscriptions/{assure}/valider', [InscriptionController::class, 'validateRegistration'])->name('inscriptions.validate');
    Route::post('/inscriptions/{assure}/refuser', [InscriptionController::class, 'reject'])->name('inscriptions.reject');
    Route::get('/inscriptions/{assure}/documents/{key}', [InscriptionController::class, 'downloadDocument'])->name('inscriptions.document');

    Route::get('/utilisateurs-internes', [AdminUserController::class, 'index'])->name('utilisateurs');
    Route::post('/utilisateurs-internes', [AdminUserController::class, 'store'])->name('utilisateurs.store');
    Route::put('/utilisateurs-internes/{adminUser}', [AdminUserController::class, 'update'])->name('utilisateurs.update');
    Route::post('/utilisateurs-internes/{adminUser}/toggle', [AdminUserController::class, 'toggleActif'])->name('utilisateurs.toggle');
    Route::post('/utilisateurs-internes/{adminUser}/renvoyer-invitation', [AdminUserController::class, 'resendInvitation'])->name('utilisateurs.resend-invitation');
    Route::delete('/utilisateurs-internes/{adminUser}', [AdminUserController::class, 'destroy'])->name('utilisateurs.destroy');

    require base_path('routes/cms.php');

    Route::get('/exports', [ExportController::class, 'index'])->name('exports');
    Route::post('/exports/telecharger', [ExportController::class, 'download'])->name('exports.download');
    Route::post('/exports/dossier-pdf', [ExportController::class, 'dossierPdf'])->name('exports.dossier-pdf');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/lire-tout', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/lire', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::post('/profil/mot-de-passe', [ProfilController::class, 'updatePassword'])->name('profil.password');
    Route::patch('/profil/2fa', [ProfilController::class, 'toggle2fa'])->name('profil.2fa');
    Route::patch('/profil/deconnexion-auto', [ProfilController::class, 'toggleIdleLogout'])->name('profil.idle');
    Route::post('/profil/deconnecter-appareils', [ProfilController::class, 'logoutOtherDevices'])->name('profil.devices');

    Route::get('/parametres', [ParametresController::class, 'index'])->name('parametres');
    Route::post('/parametres/membres', [ParametresController::class, 'updateMembres'])->name('parametres.membres');
    Route::post('/parametres/photos', [ParametresController::class, 'updatePhotos'])->name('parametres.photos');
    Route::post('/parametres/filiations', [ParametresController::class, 'updateFiliations'])->name('parametres.filiations');
    Route::post('/parametres/fif', [ParametresController::class, 'updateFif'])->name('parametres.fif');
    Route::post('/parametres/retention', [ParametresController::class, 'updateRetention'])->name('parametres.retention');
    Route::post('/parametres/affectation', [ParametresController::class, 'updateAffectation'])->name('parametres.affectation');
    Route::post('/parametres/libelles', [ParametresController::class, 'updateLibelles'])->name('parametres.libelles');
    Route::post('/parametres/structure', [ParametresController::class, 'updateStructure'])->name('parametres.structure');
    Route::post('/parametres/structure/reinitialiser', [ParametresController::class, 'resetStructure'])->name('parametres.structure.reset');
    Route::post('/parametres/pieces-inscription', [ParametresController::class, 'updateInscriptionDocuments'])->name('parametres.inscription-documents');
    Route::post('/parametres/champs-inscription', [ParametresController::class, 'updateInscriptionFields'])->name('parametres.inscription-fields');
});
