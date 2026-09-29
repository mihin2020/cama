<?php

use App\Models\CmsResource;
use App\Models\CmsResourceCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Remplace les ressources de démonstration (fictives) par les documents
     * officiels réels de la CAMA sur la page publique /ressources.
     */
    private array $fictives = [
        'Formulaire d\'enrôlement d\'un ayant droit',
        'Formulaire de demande de remboursement',
        'Guide de l\'assuré CAMA',
        'Attestation de prise en charge (modèle)',
        'Guide du prescripteur',
        'Décret portant création de la CAMA',
    ];

    private array $reelles = [
        ['title' => 'Fiche d\'identification de famille (FIF)', 'category' => 'Formulaires', 'description' => 'Formulaire à renseigner et faire viser (chef de corps, GRH) pour déclarer les conjoint(e)s et enfants à charge.', 'file_size' => '217 Ko', 'file_url' => 'documents/ressources/fiche-identification-famille-fif.pdf', 'sort_order' => 1],
        ['title' => 'Formulaire d\'enrôlement de l\'assuré principal', 'category' => 'Formulaires', 'description' => 'Fiche de renseignements du militaire (assuré principal) à compléter lors de l\'enrôlement.', 'file_size' => '', 'file_url' => 'documents/ressources/formulaire-enrolement-assure-principal.pdf', 'sort_order' => 2],
        ['title' => 'Formulaire de reconfection de carte CAMA', 'category' => 'Formulaires', 'description' => 'Demande de re-confection de carte CAMA en cas de perte ou de vol, à adresser au SG/CAMA.', 'file_size' => '', 'file_url' => 'documents/ressources/formulaire-reconfection-carte-cama.pdf', 'sort_order' => 3],
        ['title' => 'Pièces à fournir pour la confection de carte CAMA (famille)', 'category' => 'Guides', 'description' => 'Liste des pièces à fournir par membre : époux/épouse et enfants de 0 à 26 ans.', 'file_size' => '', 'file_url' => 'documents/ressources/pieces-a-fournir-confection-carte-cama.pdf', 'sort_order' => 4],
    ];

    public function up(): void
    {
        // Supprime les ressources de démonstration.
        CmsResource::query()->whereIn('title', $this->fictives)->delete();

        foreach ($this->reelles as $data) {
            $category = CmsResourceCategory::query()->firstOrCreate(
                ['name' => $data['category']],
                ['sort_order' => $data['sort_order']],
            );

            CmsResource::query()->updateOrCreate(
                ['title' => $data['title']],
                [
                    'description' => $data['description'],
                    'category_id' => $category->id,
                    'format' => 'PDF',
                    'file_size' => $data['file_size'],
                    'file_url' => $data['file_url'],
                    'sort_order' => $data['sort_order'],
                    'published' => true,
                ],
            );
        }
    }

    public function down(): void
    {
        CmsResource::query()
            ->whereIn('title', array_column($this->reelles, 'title'))
            ->delete();
    }
};
