<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\CmsActivityLog;
use App\Models\CmsArticle;
use App\Models\CmsArticleCategory;
use App\Models\CmsFaq;
use App\Models\CmsFaqCategory;
use App\Models\CmsInfoBanner;
use App\Models\CmsKeyFigure;
use App\Models\CmsMenuItem;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsResource;
use App\Models\CmsResourceCategory;
use App\Models\CmsSlide;
use App\Services\CmsPageService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFaqCategories();
        $this->seedArticleCategories();
        $this->seedFaq();
        $this->seedSlides();
        $this->seedInfoBanner();
        $this->seedKeyFigures();
        $this->seedResourceCategories();
        $this->seedResources();
        $this->seedPartners();
        $this->seedArticles();
        $this->seedPages();
        $this->seedMenus();
        $this->seedActivity();
    }

    private function seedFaqCategories(): void
    {
        $defaults = [
            ['name' => 'Enrôlement', 'sort_order' => 1],
            ['name' => 'Prestations', 'sort_order' => 2],
            ['name' => 'Remboursements', 'sort_order' => 3],
            ['name' => 'Compte assuré', 'sort_order' => 4],
        ];

        foreach ($defaults as $cat) {
            CmsFaqCategory::query()->firstOrCreate(['name' => $cat['name']], $cat);
        }
    }

    private function seedArticleCategories(): void
    {
        $defaults = [
            ['name' => 'Institution', 'sort_order' => 1],
            ['name' => 'Événement', 'sort_order' => 2],
            ['name' => 'Communiqué', 'sort_order' => 3],
            ['name' => 'Partenariat', 'sort_order' => 4],
        ];

        foreach ($defaults as $cat) {
            CmsArticleCategory::query()->firstOrCreate(['name' => $cat['name']], $cat);
        }
    }

    private function seedFaq(): void
    {
        if (CmsFaq::query()->exists()) {
            return;
        }

        $categories = CmsFaqCategory::query()->pluck('id', 'name');

        $items = [
            ['question' => 'Comment obtenir un remboursement pour une consultation ?', 'answer' => "Présentez votre carte d'assuré CAMA lors de la consultation. Pour les établissements conventionnés, le tiers-payant peut s'appliquer. Sinon, déposez votre feuille de soins et la facture originale à votre guichet CAMA habituel.", 'category' => 'Remboursements', 'sort_order' => 1, 'published' => true],
            ['question' => "Qu'est-ce qu'une demande d'entente préalable ?", 'answer' => "C'est une demande formelle à remplir par votre médecin avant certains actes coûteux (actes chirurgicaux, IRM, prothèses), afin que la CAMA valide la prise en charge avant l'intervention.", 'category' => 'Prestations', 'sort_order' => 1, 'published' => true],
            ['question' => 'Mes ayants-droit bénéficient-ils des mêmes tarifs ?', 'answer' => "Oui, les membres de votre famille (conjoint et enfants) inscrits sur votre dossier bénéficient du même panier de soins et des mêmes taux de remboursement que l'assuré principal.", 'category' => 'Enrôlement', 'sort_order' => 1, 'published' => true],
            ['question' => 'Comment se fait le remboursement ?', 'answer' => 'Les remboursements sont effectués par virement bancaire après réception et traitement complet de votre dossier par les services de la CAMA.', 'category' => 'Remboursements', 'sort_order' => 2, 'published' => true],
            ['question' => 'Qui peut s\'inscrire sur la plateforme ?', 'answer' => 'Tout militaire en fonction et ayant un numéro CAMA valide peut créer un compte assuré.', 'category' => 'Compte assuré', 'sort_order' => 1, 'published' => true],
            ['question' => 'Quels justificatifs pour un enfant ?', 'answer' => 'Acte de naissance, copie CNIB du parent assuré, et certificat de scolarité le cas échéant.', 'category' => 'Enrôlement', 'sort_order' => 2, 'published' => true],
        ];

        foreach ($items as $item) {
            CmsFaq::query()->create([
                'question' => $item['question'],
                'answer' => $item['answer'],
                'category_id' => $categories[$item['category']],
                'sort_order' => $item['sort_order'],
                'published' => $item['published'],
            ]);
        }
    }

    private function seedSlides(): void
    {
        if (CmsSlide::query()->exists()) {
            return;
        }

        $slides = [
            ['title' => 'La santé de nos héros, notre priorité', 'subtitle' => 'Lancée officiellement le 13 février 2025, la CAMA assure une couverture santé robuste aux militaires et à leurs familles.', 'image_url' => 'images/CAMA_8.jfif', 'link_url' => 'espace-assure.html', 'link_label' => 'Espace Assuré', 'sort_order' => 1, 'active' => true],
            ['title' => 'Une solidarité au service des forces armées', 'subtitle' => 'Une cotisation de 5,5 % pour une prise en charge à 80 % des soins, dans la dignité et la transparence.', 'image_url' => 'images/CAMA_6.jfif', 'link_url' => 'services.html', 'link_label' => 'Nos prestations', 'sort_order' => 2, 'active' => true],
            ['title' => 'Une institution moderne et accessible', 'subtitle' => 'La CAMA poursuit la digitalisation de ses services pour mieux servir ses assurés.', 'image_url' => 'images/CAMA_1.jfif', 'link_url' => 'actualite.html', 'link_label' => 'Voir les actualités', 'sort_order' => 3, 'active' => true],
        ];

        foreach ($slides as $slide) {
            CmsSlide::query()->create($slide);
        }
    }

    private function seedInfoBanner(): void
    {
        if (CmsInfoBanner::query()->exists()) {
            return;
        }

        CmsInfoBanner::query()->create([
            'active' => true,
            'type' => 'info',
            'message' => 'Campagne d\'enrôlement 2026 : créez votre espace assuré et enrôlez vos ayants droit en ligne. <a href="inscription-assure.html">Commencer l\'enrôlement</a>',
            'link_url' => null,
            'link_label' => null,
        ]);
    }

    private function seedKeyFigures(): void
    {
        if (CmsKeyFigure::query()->exists()) {
            return;
        }

        $figures = [
            ['value' => 5.5, 'suffix' => '%', 'label' => 'Taux de cotisation mensuelle', 'icon' => 'percent', 'sort_order' => 1],
            ['value' => 150, 'suffix' => '+', 'label' => 'Structures de soins partenaires', 'icon' => 'local_hospital', 'sort_order' => 2],
            ['value' => 17, 'suffix' => '', 'label' => 'Régions couvertes', 'icon' => 'map', 'sort_order' => 3],
            ['value' => 2020, 'suffix' => '', 'label' => 'Année de création', 'icon' => 'history', 'sort_order' => 4],
        ];

        foreach ($figures as $figure) {
            CmsKeyFigure::query()->create($figure);
        }
    }

    private function seedResourceCategories(): void
    {
        $defaults = [
            ['name' => 'Formulaires', 'sort_order' => 1],
            ['name' => 'Guides', 'sort_order' => 2],
            ['name' => 'Attestations', 'sort_order' => 3],
            ['name' => 'Guide du prescripteur', 'sort_order' => 4],
            ['name' => 'Textes réglementaires et législatifs', 'sort_order' => 5],
        ];

        foreach ($defaults as $cat) {
            CmsResourceCategory::query()->firstOrCreate(['name' => $cat['name']], $cat);
        }

        // Renommage legacy
        CmsResourceCategory::query()
            ->where('name', 'Textes législatifs')
            ->update(['name' => 'Textes réglementaires et législatifs']);
    }

    private function seedResources(): void
    {
        if (CmsResource::query()->exists()) {
            return;
        }

        $categories = CmsResourceCategory::query()->pluck('id', 'name');

        // Documents officiels CAMA (les seuls réels — pas de contenu fictif).
        // Déposer les PDF correspondants dans public/documents/ressources/.
        $resources = [
            ['title' => 'Fiche d\'identification de famille (FIF)', 'category' => 'Formulaires', 'description' => 'Formulaire à renseigner et faire viser (chef de corps, GRH) pour déclarer les conjoint(e)s et enfants à charge.', 'format' => 'PDF', 'file_size' => '217 Ko', 'file_url' => 'documents/ressources/fiche-identification-famille-fif.pdf', 'sort_order' => 1, 'published' => true],
            ['title' => 'Formulaire d\'enrôlement de l\'assuré principal', 'category' => 'Formulaires', 'description' => 'Fiche de renseignements du militaire (assuré principal) à compléter lors de l\'enrôlement.', 'format' => 'PDF', 'file_size' => '', 'file_url' => 'documents/ressources/formulaire-enrolement-assure-principal.pdf', 'sort_order' => 2, 'published' => true],
            ['title' => 'Formulaire de reconfection de carte CAMA', 'category' => 'Formulaires', 'description' => 'Demande de re-confection de carte CAMA en cas de perte ou de vol, à adresser au SG/CAMA.', 'format' => 'PDF', 'file_size' => '', 'file_url' => 'documents/ressources/formulaire-reconfection-carte-cama.pdf', 'sort_order' => 3, 'published' => true],
            ['title' => 'Pièces à fournir pour la confection de carte CAMA (famille)', 'category' => 'Guides', 'description' => 'Liste des pièces à fournir par membre : époux/épouse et enfants de 0 à 26 ans.', 'format' => 'PDF', 'file_size' => '', 'file_url' => 'documents/ressources/pieces-a-fournir-confection-carte-cama.pdf', 'sort_order' => 4, 'published' => true],
        ];

        foreach ($resources as $resource) {
            CmsResource::query()->create([
                'title' => $resource['title'],
                'description' => $resource['description'],
                'category_id' => $categories[$resource['category']],
                'format' => $resource['format'],
                'file_size' => $resource['file_size'],
                'file_url' => $resource['file_url'],
                'sort_order' => $resource['sort_order'],
                'published' => $resource['published'],
            ]);
        }
    }

    private function seedPartners(): void
    {
        if (CmsPartner::query()->exists()) {
            return;
        }

        $partners = [
            [
                'name' => 'Hôpital Militaire de Ouagadougou',
                'type' => 'Centre de santé',
                'city' => 'Ouagadougou',
                'description' => 'Structure de référence pour les soins des militaires et de leurs ayants droit.',
                'image_url' => 'images/CAMA_1.jfif',
                'latitude' => 12.3714,
                'longitude' => -1.5197,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=12.3714,-1.5197',
                'sort_order' => 1,
                'published' => true,
            ],
            [
                'name' => 'CHU Sourô Sanou',
                'type' => 'Centre de santé',
                'city' => 'Bobo-Dioulasso',
                'description' => 'Centre hospitalier universitaire partenaire pour la prise en charge spécialisée.',
                'image_url' => 'images/CAMA_6.jfif',
                'latitude' => 11.1771,
                'longitude' => -4.2979,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=11.1771,-4.2979',
                'sort_order' => 2,
                'published' => true,
            ],
            [
                'name' => 'Clinique Les Genêts',
                'type' => 'Partenaire',
                'city' => 'Ouagadougou',
                'description' => 'Établissement conventionné offrant un tiers payant aux assurés CAMA.',
                'image_url' => 'images/CAMA_8.jfif',
                'latitude' => 12.3650,
                'longitude' => -1.5340,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=12.3650,-1.5340',
                'sort_order' => 3,
                'published' => true,
            ],
            [
                'name' => 'Pharmacie de la Liberté',
                'type' => 'Partenaire',
                'city' => 'Ouagadougou',
                'description' => 'Officine partenaire pratiquant le tiers payant pharmaceutique.',
                'image_url' => 'images/CAMA_5.jfif',
                'latitude' => 12.3580,
                'longitude' => -1.5125,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=12.3580,-1.5125',
                'sort_order' => 4,
                'published' => true,
            ],
        ];

        foreach ($partners as $partner) {
            CmsPartner::query()->create($partner);
        }
    }

    private function seedArticles(): void
    {
        if (CmsArticle::query()->exists()) {
            return;
        }

        $categories = CmsArticleCategory::query()->pluck('id', 'name');

        $articles = [
            ['slug' => 'lancement-officiel-cama', 'title' => "Lancement officiel de la Caisse d'Assurance Maladie des Armées", 'category' => 'Institution', 'status' => 'published', 'author_name' => 'CAMA', 'published_at' => '2025-02-13', 'image_url' => 'images/CAMA_8.jfif', 'excerpt' => "Au siège de l'ex-État-Major Général des Armées à Bilbalogho, la CAMA a été officiellement lancée en présence du Ministre d'État chargé de la Défense.", 'body_html' => "<p>Au siège de l'ex-État-Major Général des Armées à Bilbalogho, la CAMA a été officiellement lancée en présence du Général de Brigade Céléstin Simporé, Ministre d'État chargé de la Défense.</p><p>L'institution élargit la couverture santé aux conjoints et enfants des militaires, conformément au décret n°2020-0272.</p>", 'featured' => true],
            ['slug' => 'inauguration-siege-bilbalogho', 'title' => 'Inauguration du siège de la CAMA à Bilbalogho', 'category' => 'Institution', 'status' => 'published', 'author_name' => 'CAMA', 'published_at' => '2025-02-13', 'image_url' => 'images/CAMA_1.jfif', 'excerpt' => "Les locaux de l'ex-État-Major Général des Armées accueillent désormais la direction générale de la CAMA.", 'body_html' => "<p>Les locaux de l'ex-État-Major Général des Armées accueillent désormais la direction générale de la CAMA, au cœur de Ouagadougou.</p><p>Ce site centralise l'accueil des assurés, le traitement des dossiers et la coordination avec les antennes régionales.</p>", 'featured' => false],
            ['slug' => 'coupure-ruban-cama', 'title' => 'Coupure du ruban : la CAMA ouvre officiellement ses portes', 'category' => 'Événement', 'status' => 'published', 'author_name' => 'CAMA', 'published_at' => '2025-02-13', 'image_url' => 'images/CAMA_6.jfif', 'excerpt' => 'Une cérémonie solennelle a marqué le démarrage des activités de la caisse au bénéfice des militaires et de leurs familles.', 'body_html' => '<p>Une cérémonie solennelle a marqué le démarrage des activités de la caisse au bénéfice des militaires et de leurs familles.</p><p>Les autorités ont salué une étape majeure dans la modernisation de la protection sociale des Forces Armées Nationales.</p>', 'featured' => false],
            ['slug' => 'allocution-secretaire-general', 'title' => 'Allocution du Secrétaire Général du Ministère de la Défense', 'category' => 'Institution', 'status' => 'published', 'author_name' => 'CAMA', 'published_at' => '2025-11-13', 'image_url' => 'images/CAMA_4.jfif', 'excerpt' => 'Lors de la passation de service, la CAMA a été présentée comme un instrument de solidarité pour les forces armées.', 'body_html' => "<p>Lors de la passation de service, la CAMA a été présentée comme « un instrument de solidarité, de dignité et de stabilité » pour les forces armées.</p><p>Le pharmacien lieutenant-colonel Ousmane Sinaré a été installé à la tête de l'institution.</p>", 'featured' => false],
            ['slug' => 'campagne-sensibilisation-2026', 'title' => 'Campagne de sensibilisation 2026 sur la couverture santé', 'category' => 'Communiqué', 'status' => 'published', 'author_name' => 'Cdt. Paul SAWADOGO', 'published_at' => '2026-06-18', 'image_url' => 'images/CAMA_3.3.jfif', 'excerpt' => 'La CAMA lance une campagne nationale pour informer les militaires et leurs familles sur leurs droits et démarches.', 'body_html' => "<p>La CAMA lance une campagne nationale pour informer les militaires et leurs familles sur leurs droits, les prestations couvertes et les démarches d'enrôlement en ligne.</p>", 'featured' => false],
            ['slug' => 'partenariat-chu-yalgado', 'title' => "Signature d'un partenariat avec le CHU Yalgado Ouédraogo", 'category' => 'Partenariat', 'status' => 'published', 'author_name' => 'Ing. Awa OUÉDRAOGO', 'published_at' => '2026-06-10', 'image_url' => 'images/CAMA_7.jfif', 'excerpt' => 'Un accord de prise en charge renforce le réseau de soins conventionnés pour les assurés CAMA.', 'body_html' => '<p>Un accord de prise en charge renforce le réseau de soins conventionnés pour les assurés CAMA à Ouagadougou et dans la région du Centre.</p>', 'featured' => false],
        ];

        foreach ($articles as $article) {
            CmsArticle::query()->create([
                'slug' => $article['slug'],
                'title' => $article['title'],
                'category_id' => $categories[$article['category']],
                'status' => $article['status'],
                'author_name' => $article['author_name'],
                'published_at' => $article['published_at'],
                'image_url' => $article['image_url'],
                'excerpt' => $article['excerpt'],
                'body_html' => $article['body_html'],
                'featured' => $article['featured'],
            ]);
        }
    }

    private function seedPages(): void
    {
        if (CmsPage::query()->exists()) {
            return;
        }

        $admin = AdminUser::query()->where('email', 'admin@cama.bf')->first();

        $pages = [
            ['title' => 'Accueil', 'slug' => 'accueil', 'status' => 'published', 'subtitle' => 'La Caisse d\'Assurance Maladie des Armées au service des familles militaires.'],
            ['title' => 'À propos', 'slug' => 'apropos', 'status' => 'published', 'subtitle' => 'Notre mission, notre histoire et nos engagements.'],
            ['title' => 'Services et prestations', 'slug' => 'services', 'status' => 'published', 'subtitle' => 'Découvrez l\'ensemble de nos prestations.'],
            ['title' => 'Ressources', 'slug' => 'ressources', 'status' => 'published', 'subtitle' => 'Formulaires, guides, attestations et textes de référence.'],
            ['title' => 'Actualités', 'slug' => 'actualites', 'status' => 'published', 'subtitle' => 'Suivez les dernières évolutions de la CAMA.'],
            ['title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'subtitle' => 'Nos équipes vous répondent du lundi au vendredi.'],
            ['title' => 'Mentions légales', 'slug' => 'mention_legales', 'status' => 'draft', 'subtitle' => 'Informations légales du site CAMA.'],
            ['title' => 'Accessibilité', 'slug' => 'accessibilite', 'status' => 'published', 'subtitle' => 'Déclaration d\'accessibilité du site CAMA.'],
        ];

        foreach ($pages as $page) {
            CmsPage::query()->create([
                'title' => $page['title'],
                'slug' => $page['slug'],
                'status' => $page['status'],
                'sections_json' => match ($page['slug']) {
                    'accueil' => app(CmsPageService::class)->homeSystemSections(),
                    'services' => app(CmsPageService::class)->servicesSystemSections(),
                    default => [
                    [
                        'type' => 'section',
                        'columns' => [[
                            'widgets' => [
                                ['type' => 'heading', 'content' => ['text' => $page['title'], 'tag' => 'h1']],
                                ['type' => 'text', 'content' => ['html' => $page['subtitle']]],
                            ],
                        ]],
                    ],
                ],
                },
                'author_id' => $admin?->id,
                'published_at' => $page['status'] === 'published' ? now() : null,
            ]);
        }
    }

    private function seedMenus(): void
    {
        if (CmsMenuItem::query()->exists()) {
            return;
        }

        $pages = CmsPage::query()->whereIn('slug', ['accueil', 'apropos', 'services', 'contact'])->get()->keyBy('slug');

        $items = [
            ['type' => 'page', 'slug' => 'accueil', 'label' => 'Accueil', 'url' => null, 'sort_order' => 1],
            ['type' => 'page', 'slug' => 'apropos', 'label' => 'À propos', 'url' => null, 'sort_order' => 2],
            ['type' => 'page', 'slug' => 'services', 'label' => 'Services', 'url' => null, 'sort_order' => 3],
            ['type' => 'custom', 'slug' => null, 'label' => 'Ressources', 'url' => '/ressources', 'sort_order' => 4],
            ['type' => 'custom', 'slug' => null, 'label' => 'Actualités', 'url' => '/actualites', 'sort_order' => 5],
            ['type' => 'page', 'slug' => 'contact', 'label' => 'Contact', 'url' => null, 'sort_order' => 6],
        ];

        foreach ($items as $item) {
            $page = $item['slug'] ? $pages->get($item['slug']) : null;
            if ($item['type'] === 'page' && ! $page) {
                continue;
            }

            CmsMenuItem::query()->create([
                'type' => $item['type'],
                'page_id' => $page?->id,
                'label' => $item['label'],
                'url' => $item['url'],
                'depth' => 0,
                'sort_order' => $item['sort_order'],
            ]);
        }
    }

    private function seedActivity(): void
    {
        if (CmsActivityLog::query()->exists()) {
            return;
        }

        $entries = [
            ['icon' => 'newspaper', 'text' => 'Article « Campagne de sensibilisation 2026 » publié', 'author_name' => 'Cdt. Paul SAWADOGO', 'created_at' => '18/06/2026 14:20'],
            ['icon' => 'view_carousel', 'text' => 'Slide « Nos engagements » mise à jour sur la bannière', 'author_name' => 'Ing. Awa OUÉDRAOGO', 'created_at' => '17/06/2026 10:05'],
            ['icon' => 'quiz', 'text' => 'Nouvelle question ajoutée à la FAQ', 'author_name' => 'Cdt. Paul SAWADOGO', 'created_at' => '15/06/2026 16:42'],
            ['icon' => 'monitoring', 'text' => 'Chiffre clé « Assurés couverts » actualisé', 'author_name' => 'Ing. Awa OUÉDRAOGO', 'created_at' => '12/06/2026 09:15'],
        ];

        foreach ($entries as $entry) {
            $createdAt = Carbon::createFromFormat('d/m/Y H:i', $entry['created_at']);

            CmsActivityLog::query()->create([
                'icon' => $entry['icon'],
                'text' => $entry['text'],
                'author_name' => $entry['author_name'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
