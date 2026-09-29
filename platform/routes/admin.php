<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\Auth\InvitationController;
use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\AssureController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DossierController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\DossierPieceController;
use App\Http\Controllers\Admin\InscriptionController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ParametresController;
use App\Http\Controllers\Admin\ProfilController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest.admin')->group(function () {
    Route::get('/connexion', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/connexion', [AdminLoginController::class, 'store'])->name('login.store');
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invitation', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::middleware(['auth:admin', 'admin.permission'])->group(function () {
    Route::get('/tableau-de-bord', AdminDashboardController::class)->name('dashboard');
    Route::post('/deconnexion', [AdminLoginController::class, 'destroy'])->name('logout');

    Route::get('/dossiers', [DossierController::class, 'index'])->name('dossiers');
    Route::post('/dossiers/affecter', [DossierController::class, 'assign'])->name('dossiers.assign');
    Route::post('/dossiers/affecter-lot', [DossierController::class, 'batchAssign'])->name('dossiers.batch-assign');
    Route::post('/dossiers/{dossier}/valider', [DossierController::class, 'validate'])->name('dossiers.validate');
    Route::post('/dossiers/{dossier}/refuser', [DossierController::class, 'reject'])->name('dossiers.reject');
    Route::post('/dossiers/{dossier}/complement', [DossierController::class, 'complement'])->name('dossiers.complement');
    Route::post('/dossiers/{dossier}/en-attente', [DossierController::class, 'enAttente'])->name('dossiers.en-attente');
    Route::post('/dossiers/{dossier}/message', [DossierController::class, 'message'])->name('dossiers.message');
    Route::get('/dossiers/{dossier}/pieces/telecharger', [DossierPieceController::class, 'download'])->name('dossiers.piece.download');

    Route::get('/assures', [AssureController::class, 'index'])->name('assures');
    Route::post('/assures/{assure}/statut', [AssureController::class, 'updateStatut'])->name('assures.statut');

    Route::middleware('admin.role:gestionnaire,superviseur,administrateur')->group(function () {
        Route::get('/inscriptions', [InscriptionController::class, 'index'])->name('inscriptions');
        Route::patch('/inscriptions/{assure}/identifiants', [InscriptionController::class, 'updateIdentifiers'])->name('inscriptions.identifiers');
        Route::post('/inscriptions/{assure}/valider', [InscriptionController::class, 'validateRegistration'])->name('inscriptions.validate');
        Route::post('/inscriptions/{assure}/refuser', [InscriptionController::class, 'reject'])->name('inscriptions.reject');
        Route::get('/inscriptions/{assure}/documents/{key}', [InscriptionController::class, 'downloadDocument'])->name('inscriptions.document');
    });

    Route::middleware('admin.role:superviseur,administrateur')->group(function () {
        Route::get('/utilisateurs-internes', [AdminUserController::class, 'index'])->name('utilisateurs');
        Route::post('/utilisateurs-internes', [AdminUserController::class, 'store'])->name('utilisateurs.store');
        Route::put('/utilisateurs-internes/{adminUser}', [AdminUserController::class, 'update'])->name('utilisateurs.update');
        Route::post('/utilisateurs-internes/{adminUser}/toggle', [AdminUserController::class, 'toggleActif'])->name('utilisateurs.toggle');
        Route::post('/utilisateurs-internes/{adminUser}/renvoyer-invitation', [AdminUserController::class, 'resendInvitation'])->name('utilisateurs.resend-invitation');
        Route::delete('/utilisateurs-internes/{adminUser}', [AdminUserController::class, 'destroy'])->name('utilisateurs.destroy');
    });

    Route::get('/audit', [AuditController::class, 'index'])->name('audit');
    require base_path('routes/cms.php');

    Route::get('/exports', [ExportController::class, 'index'])->name('exports');
    Route::get('/exports/membres', [ExportController::class, 'membres'])->name('exports.membres');
    Route::get('/exports/dossiers', [ExportController::class, 'dossiers'])->name('exports.dossiers');
    Route::post('/exports/dossier-pdf', [ExportController::class, 'dossierPdf'])->name('exports.dossier-pdf');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/lire-tout', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/lire', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::post('/profil/mot-de-passe', [ProfilController::class, 'updatePassword'])->name('profil.password');

    Route::middleware('admin.role:administrateur')->group(function () {
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
    });
});
