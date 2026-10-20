<?php

namespace App\Http\Requests;

use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterAssureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settings = app(PlatformSettingsService::class);
        $org = $settings->orgStructure();

        // Champs toujours présents et obligatoires (indispensables au compte).
        $rules = [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:assures,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'cgu' => ['accepted'],
        ];

        // Champs paramétrables (Paramètres › Champs d'inscription).
        $base = [
            'matricule' => ['string', 'max:50', 'unique:assures,matricule'],
            'sexe' => ['in:Masculin,Féminin'],
            'grade' => ['string', 'max:100', Rule::in($org['grades'] ?? [])],
            'categorie' => ['string', 'max:100', Rule::in($org['categories'] ?? [])],
            'numero_informatique' => ['string', 'max:50'],
            'numero_cim' => ['string', 'max:50'],
            'numero_cama' => ['string', 'max:50'],
            'numero_iup' => ['string', 'max:50'],
            'armee' => ['string', 'max:150', Rule::in($org['armees'] ?? [])],
            'region' => ['string', 'max:200'],
            'corps' => ['string', 'max:200'],
            'service' => ['string', 'max:200'],
            'section' => ['string', 'max:200'],
            'sous_section' => ['string', 'max:200'],
            'telephone' => ['string', 'max:255'],
            'personne_a_prevenir' => ['string', 'max:200'],
            'tel_personne_a_prevenir' => ['string', 'max:255'],
        ];

        $cascadeHasOptions = $this->cascadeHasOptions($org['regions'] ?? []);

        foreach ($settings->inscriptionFieldsMap() as $key => $config) {
            if (! isset($base[$key])) {
                continue;
            }

            if (! $config['actif']) {
                // Champ masqué : toute valeur envoyée est ignorée.
                $rules[$key] = ['exclude'];

                continue;
            }

            // Un niveau de rattachement sans option disponible ne peut pas être exigé.
            $required = $config['required'] && ($cascadeHasOptions[$key] ?? true);

            $rules[$key] = array_merge([$required ? 'required' : 'nullable'], $base[$key]);
        }

        foreach ($settings->activeInscriptionDocuments() as $slot) {
            $rules["documents.{$slot['key']}"] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
        }

        return $rules;
    }

    /**
     * Indique, pour chaque sous-niveau de rattachement, si la sélection du niveau
     * parent propose effectivement des options.
     *
     * @return array<string, bool>
     */
    private function cascadeHasOptions(array $regions): array
    {
        $find = fn (array $nodes, mixed $libelle) => collect($nodes)
            ->first(fn ($n) => is_array($n) && ($n['libelle'] ?? null) === $libelle);

        $region = $find($regions, $this->input('region'));
        $corps = $region ? $find($region['corps'] ?? [], $this->input('corps')) : null;
        $service = $corps ? $find($corps['services'] ?? [], $this->input('service')) : null;
        $section = $service ? $find($service['sections'] ?? [], $this->input('section')) : null;

        return [
            'corps' => ! empty($region['corps'] ?? []),
            'service' => ! empty($corps['services'] ?? []),
            'section' => ! empty($service['sections'] ?? []),
            'sous_section' => ! empty($section['sous_sections'] ?? []),
        ];
    }

    public function messages(): array
    {
        return [
            'numero_informatique.required' => 'Le N° informatique est obligatoire.',
            'numero_cim.required' => 'Le N° CIM est obligatoire.',
            'numero_cama.required' => 'Le N° Carte CAMA est obligatoire.',
            'cgu.accepted' => 'Veuillez accepter les CGU.',
            'categorie.in' => 'La catégorie sélectionnée n\'est plus proposée à l\'inscription.',
            'grade.in' => 'Le grade sélectionné n\'est plus proposé à l\'inscription.',
            'armee.in' => 'L\'armée sélectionnée n\'est plus proposée à l\'inscription.',
        ];
    }
}
