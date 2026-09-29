<?php



namespace App\Http\Controllers\Assure;



use App\Http\Controllers\Controller;
use App\Models\Assure;
use App\Services\AssureDashboardService;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;

use Illuminate\Validation\Rules\Password;

use Illuminate\Validation\ValidationException;

use Inertia\Inertia;

use Inertia\Response;



class ProfilController extends Controller

{

    public function show(AssureDashboardService $dashboard): Response

    {

        $assure = auth('assure')->user();



        return Inertia::render('Assure/Profil/Show', [

            'profil' => $this->formatProfil($assure),

            'securityLog' => $this->securityLog(),

            'unreadCount' => $dashboard->stats($assure)['unreadCount'],

        ]);

    }



    public function update(Request $request): RedirectResponse

    {

        $assure = auth('assure')->user();



        $data = $request->validate([

            'email' => ['required', 'email', 'max:255'],

            'telephone' => ['required', 'string', 'max:80'],

            'personne_a_prevenir' => ['nullable', 'string', 'max:200'],

            'tel_personne_a_prevenir' => ['nullable', 'string', 'max:80'],

            'numero_cama' => ['nullable', 'string', 'max:50'],

        ]);



        if ($data['email'] !== $assure->email && Assure::query()->where('email', $data['email'])->where('id', '!=', $assure->id)->exists()) {

            throw ValidationException::withMessages(['email' => 'Cette adresse e-mail est déjà utilisée.']);

        }



        $assure->update([

            'email' => $data['email'],

            'telephone' => $data['telephone'],

            'personne_a_prevenir' => $data['personne_a_prevenir'],

            'tel_personne_a_prevenir' => $data['tel_personne_a_prevenir'],

            'numero_cama' => $data['numero_cama'] ?: $assure->numero_cama,

        ]);



        return back()->with('success', 'Coordonnées mises à jour.');

    }



    public function updatePassword(Request $request): RedirectResponse

    {

        $assure = auth('assure')->user();



        $data = $request->validate([

            'current_password' => ['required', 'string'],

            'password' => ['required', 'confirmed', Password::min(12)],

        ]);



        if (! Hash::check($data['current_password'], $assure->password)) {

            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);

        }



        $assure->update(['password' => $data['password']]);



        return back()->with('success', 'Mot de passe mis à jour.');

    }



    public function toggle2fa(Request $request): RedirectResponse

    {

        $assure = auth('assure')->user();



        $data = $request->validate([

            'active' => ['required', 'boolean'],

        ]);



        $assure->update(['deux_fa_active' => $data['active']]);



        return back()->with('success', $data['active'] ? '2FA activée.' : '2FA désactivée.');

    }



    private function formatProfil($assure): array

    {

        return [

            'fullName' => $assure->full_name,

            'nom' => $assure->nom,

            'prenom' => $assure->prenom,

            'initiales' => $assure->initiales,

            'sexe' => $assure->sexe,

            'matricule' => $assure->matricule,

            'grade' => $assure->grade,

            'categorie' => $assure->categorie,

            'numeroCim' => $assure->numero_cim,

            'numeroCama' => $assure->numero_cama,

            'numeroIup' => $assure->numero_iup,

            'numeroInformatique' => $assure->numero_informatique,

            'armee' => $assure->armee,

            'region' => $assure->region,

            'corps' => $assure->corps,

            'service' => $assure->service,

            'section' => $assure->section,

            'sousSection' => $assure->sous_section,

            'telephone' => $assure->telephone,

            'email' => $assure->email,

            'personneAPrevenir' => $assure->personne_a_prevenir,

            'telPersonneAPrevenir' => $assure->tel_personne_a_prevenir,

            'statut' => $assure->statut->label(),

            'statutActif' => $assure->statut->value === 'actif',

            'peutEnroler' => $assure->peutEnroler(),

            'deuxFa' => $assure->deux_fa_active,

            'dateCreation' => $assure->created_at->format('d/m/Y'),

            'derniereConnexion' => $assure->updated_at->format('d/m/Y H:i'),

        ];

    }



    private function securityLog(): array

    {

        return [

            ['date' => '18/06/2026 09:14', 'lieu' => 'Ouagadougou, BF', 'appareil' => 'Chrome / Windows', 'suspect' => true],

            ['date' => '10/06/2026 07:50', 'lieu' => 'Ouagadougou, BF', 'appareil' => 'Safari / iPhone', 'suspect' => false],

            ['date' => '02/06/2026 18:22', 'lieu' => 'Bobo-Dioulasso, BF', 'appareil' => 'Chrome / Windows', 'suspect' => false],

        ];

    }

}


