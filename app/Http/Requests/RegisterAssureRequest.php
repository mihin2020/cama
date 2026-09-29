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
        $org = app(PlatformSettingsService::class)->orgStructure();

        $rules = [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'sexe' => ['required', 'in:Masculin,Féminin'],
            'matricule' => ['required', 'string', 'max:50', 'unique:assures,matricule'],
            'grade' => ['required', 'string', 'max:100', Rule::in($org['grades'] ?? [])],
            'categorie' => ['required', 'string', 'max:100', Rule::in($org['categories'] ?? [])],
            'numero_informatique' => ['required', 'string', 'max:50'],
            'numero_cim' => ['required', 'string', 'max:50'],
            'numero_cama' => ['required', 'string', 'max:50'],
            'numero_iup' => ['nullable', 'string', 'max:50'],
            'armee' => ['required', 'string', 'max:150', Rule::in($org['armees'] ?? [])],
            'region' => ['required', 'string', 'max:200'],
            'corps' => ['nullable', 'string', 'max:200'],
            'service' => ['nullable', 'string', 'max:200'],
            'section' => ['nullable', 'string', 'max:200'],
            'sous_section' => ['nullable', 'string', 'max:200'],
            'telephone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:assures,email'],
            'personne_a_prevenir' => ['nullable', 'string', 'max:200'],
            'tel_personne_a_prevenir' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'cgu' => ['accepted'],
        ];

        foreach (app(PlatformSettingsService::class)->activeInscriptionDocuments() as $slot) {
            $rules["documents.{$slot['key']}"] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
        }

        return $rules;
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
