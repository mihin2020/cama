<?php

namespace Tests\Feature;

use App\Http\Requests\RegisterAssureRequest;
use App\Services\CmsPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SeptembreEvolutionsContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_numero_informatique_is_required_on_registration(): void
    {
        $request = new RegisterAssureRequest;
        $rules = $request->rules();

        $validator = Validator::make([
            'nom' => 'Doe',
            'prenom' => 'John',
            'sexe' => 'Masculin',
            'matricule' => 'MAT-1',
            'grade' => 'Soldat',
            'categorie' => 'Militaire',
            'numero_cim' => 'CIM-1',
            'numero_cama' => 'CAMA-1',
            'armee' => 'Terre',
            'region' => 'Centre',
            'telephone' => '+22670000000',
            'email' => 'john@example.bf',
            'password' => 'Secret123!@#',
            'password_confirmation' => 'Secret123!@#',
            'cgu' => '1',
        ], $rules, $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('numero_informatique', $validator->errors()->toArray());
    }

    public function test_legal_sections_omit_editeur_and_include_droits(): void
    {
        $sections = app(CmsPageService::class)->legalSystemSections();
        $html = json_encode($sections, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('Éditeur du Site', $html);
        $this->assertStringNotContainsString('id=\"editeur\"', $html);
        $this->assertStringNotContainsString('id=\"hebergeur\"', $html);
        $this->assertStringContainsString('Droits d\'accès et de rectification', $html);
        $this->assertStringContainsString('id=\"droits\"', $html);
    }
}
