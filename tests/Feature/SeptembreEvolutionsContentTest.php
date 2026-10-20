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
        $json = json_encode($sections, JSON_UNESCAPED_UNICODE);
        $articles = collect($sections[0]['columns'][0]['widgets'][0]['content']['articles']);

        // Contenu désormais structuré (widget legal_page) : même règle, vérifiée sur les données.
        $this->assertStringNotContainsStringIgnoringCase('Éditeur du site', $json);
        $this->assertStringNotContainsStringIgnoringCase('hébergeur', $json);
        $this->assertNotContains('editeur', $articles->pluck('anchor')->all());
        $this->assertNotContains('hebergeur', $articles->pluck('anchor')->all());
        $this->assertContains('droits', $articles->pluck('anchor')->all());
        $this->assertSame('Droits d\'accès et de rectification', $articles->firstWhere('anchor', 'droits')['title']);
    }
}
