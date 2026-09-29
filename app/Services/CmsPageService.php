<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsPage;
use Illuminate\Support\Str;

class CmsPageService
{
    public const STATUSES = [
        'published' => 'En ligne',
        'draft' => 'Brouillon',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(?string $query = null, ?string $status = null): array
    {
        $pages = CmsPage::query()
            ->with('author')
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('title', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%");
                });
            })
            ->when($status && array_key_exists($status, self::STATUSES), fn ($builder) => $builder->where('status', $status))
            ->orderByRaw("case when slug = 'accueil' then 0 else 1 end")
            ->orderBy('title')
            ->get();

        return $pages->map(fn (CmsPage $page) => $this->format($page))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        return CmsPage::query()
            ->orderByRaw("case when slug = 'accueil' then 0 else 1 end")
            ->orderBy('title')
            ->get()
            ->map(fn (CmsPage $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'href' => $page->slug === 'accueil' ? '/' : '/'.$page->slug,
                'status' => $page->status,
                'statusLabel' => self::STATUSES[$page->status] ?? $page->status,
            ])
            ->all();
    }

    public function create(array $data, ?AdminUser $admin = null): CmsPage
    {
        $slug = $this->uniqueSlug($data['slug'] ?? $data['title']);

        $page = CmsPage::query()->create([
            'title' => $data['title'],
            'slug' => $slug,
            'status' => $data['status'] ?? 'draft',
                    'sections_json' => $this->systemSectionsForSlug($slug) ?? $this->defaultSections($data['title']),
            'author_id' => $admin?->id,
            'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        app(CmsActivityLogService::class)->log($admin, 'description', 'Page « '.$page->title.' » créée');

        return $page;
    }

    public function update(CmsPage $page, array $data, ?AdminUser $admin = null): CmsPage
    {
        $status = $data['status'] ?? $page->status;

        $page->update([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title'], $page),
            'status' => $status,
            'published_at' => $status === 'published' ? ($page->published_at ?? now()) : null,
        ]);

        app(CmsActivityLogService::class)->log($admin, 'description', 'Page « '.$page->title.' » mise à jour');

        return $page->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function updateSections(CmsPage $page, array $sections, ?AdminUser $admin = null, ?string $versionComment = null): CmsPage
    {
        $page->update([
            'sections_json' => $sections,
        ]);

        app(CmsPageVersionService::class)->snapshot($page->fresh(), 'save', $admin, $versionComment);
        app(CmsActivityLogService::class)->log($admin, 'dashboard_customize', 'Page « '.$page->title.' » modifiée dans l’éditeur');
        app(PublicSiteService::class)->forgetCaches($page->slug);

        return $page->fresh();
    }

    public function toggleStatus(CmsPage $page, ?AdminUser $admin = null): CmsPage
    {
        $status = $page->status === 'published' ? 'draft' : 'published';

        $page->update([
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        app(CmsActivityLogService::class)->log(
            $admin,
            'description',
            'Page « '.$page->title.' » '.($status === 'published' ? 'publiée' : 'repassée en brouillon'),
        );
        app(PublicSiteService::class)->forgetCaches($page->slug);

        return $page->fresh();
    }

    public function duplicate(CmsPage $page, ?AdminUser $admin = null): CmsPage
    {
        $copy = CmsPage::query()->create([
            'title' => $page->title.' (copie)',
            'slug' => $this->uniqueSlug($page->slug.'-copie'),
            'status' => 'draft',
            'sections_json' => $page->sections_json,
            'author_id' => $admin?->id,
            'published_at' => null,
        ]);

        app(CmsActivityLogService::class)->log($admin, 'content_copy', 'Page « '.$page->title.' » dupliquée');

        return $copy;
    }

    public function delete(CmsPage $page, ?AdminUser $admin = null): void
    {
        $title = $page->title;
        $page->delete();

        app(CmsActivityLogService::class)->log($admin, 'delete', 'Page « '.$title.' » supprimée');
    }

    public function format(CmsPage $page): array
    {
        $sections = $page->sections_json ?? [];

        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status,
            'statusLabel' => self::STATUSES[$page->status] ?? $page->status,
            'sectionCount' => is_array($sections) ? count($sections) : 0,
            'updatedAt' => $page->updated_at?->format('d/m/Y H:i') ?? '',
            'href' => $page->slug === 'accueil' ? '/' : '/'.$page->slug,
            'builderUrl' => '/admin/cms/page-builder?page='.$page->id,
            'canDelete' => $page->slug !== 'accueil',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function homeSystemSections(): array
    {
        return [
            $this->systemSection('home_hero', [
                'badgeIcon' => 'health_and_safety',
                'badgeText' => 'CAMA Burkina Faso',
                'primaryLabel' => "Commencer l'enrôlement",
                'primaryHref' => '/inscription-assure',
                'secondaryLabel' => 'Espace assuré',
                'secondaryHref' => '/espace-assure/connexion',
            ]),
            $this->systemSection('home_pillars', [
                'title' => 'Nos Piliers',
                'items' => [
                    [
                        'icon' => 'medical_services',
                        'title' => 'Accès',
                        'text' => 'Garantir à chaque service membre un accès immédiat à un vaste réseau de prestataires de santé agréés sur l’ensemble du territoire national.',
                    ],
                    [
                        'icon' => 'diversity_3',
                        'title' => 'Solidarité',
                        'text' => 'Un système mutualisé où la force du collectif protège l’individu, assurant une prise en charge équitable pour tous les ayants droit.',
                    ],
                    [
                        'icon' => 'visibility',
                        'title' => 'Transparence',
                        'text' => 'Une gestion rigoureuse et éthique des cotisations, avec des processus clairs pour les remboursements et la gestion des droits.',
                    ],
                ],
            ]),
            $this->systemSection('home_key_figures', [
                'eyebrow' => 'En Chiffres',
                'title' => "La CAMA aujourd'hui",
            ]),
            $this->systemSection('home_latest_articles', [
                'eyebrow' => 'Informations',
                'title' => 'Dernières Actualités',
                'linkLabel' => 'Voir tout le flux',
                'linkHref' => '/actualites',
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function servicesSystemSections(): array
    {
        return [
            $this->systemSection('services_hero', [
                'badge' => 'Prestations & Garanties',
                'title' => 'Une protection médicale complète pour nos forces armées',
                'text' => "Découvrez l'ensemble des actes couverts par la CAMA, de la consultation de routine aux interventions chirurgicales complexes.",
                'primaryLabel' => 'Voir le détail',
                'primaryHref' => '#coverage',
                'secondaryLabel' => 'Foire aux questions',
                'secondaryHref' => '#faq',
            ]),
            $this->systemSection('services_stats', [
                'items' => [
                    ['icon' => 'health_and_safety', 'value' => '80%', 'label' => 'Couverture moyenne', 'tone' => 'primary'],
                    ['icon' => 'payments', 'value' => '5.5%', 'label' => 'Taux de cotisation', 'tone' => 'secondary'],
                    ['icon' => 'family_restroom', 'value' => '6 mois', 'label' => 'Couverture des ayants droit après décès', 'tone' => 'tertiary'],
                    ['icon' => 'school', 'value' => '27 ans', 'label' => 'Âge limite enfant à charge (études)', 'tone' => 'dark'],
                ],
            ]),
            $this->systemSection('services_coverage', [
                'eyebrow' => 'Garanties',
                'title' => 'Détail des prestations couvertes',
                'items' => [
                    ['icon' => 'medical_services', 'title' => 'Consultations & Visites', 'text' => 'Médecine générale, spécialités médicales et urgences militaires auprès des prestataires conventionnés.', 'rate' => '80%', 'tone' => 'primary'],
                    ['icon' => 'biotech', 'title' => 'Biologie', 'text' => 'Analyses de sang, urines et prélèvements biologiques dans les laboratoires agréés.', 'rate' => '70 - 80%', 'tone' => 'tertiary'],
                    ['icon' => 'radiology', 'title' => 'Radiologie', 'text' => 'Imagerie médicale, scanners, IRM et échographies prescrits par un médecin.', 'rate' => '75%', 'tone' => 'secondary'],
                    ['icon' => 'local_hospital', 'title' => 'Actes Médico-Chirurgicaux', 'text' => 'Hospitalisation, interventions chirurgicales et pharmacie hospitalière, en tiers-payant chez les établissements conventionnés.', 'rate' => '90%', 'tone' => 'dark'],
                ],
            ]),
            $this->systemSection('services_steps', [
                'eyebrow' => 'Démarche',
                'title' => 'Comment ça marche ?',
                'items' => [
                    ['icon' => 'badge', 'title' => 'Présentez votre carte', 'text' => "Carte d'assuré CAMA à présenter chez le prestataire conventionné.", 'tone' => 'primary'],
                    ['icon' => 'stethoscope', 'title' => 'Recevez les soins', 'text' => 'Consultation, analyses ou hospitalisation selon votre besoin.', 'tone' => 'secondary'],
                    ['icon' => 'description', 'title' => 'Déposez le dossier', 'text' => 'Feuille de soins et facture originale à votre guichet CAMA.', 'tone' => 'tertiary'],
                    ['icon' => 'account_balance_wallet', 'title' => 'Soyez remboursé', 'text' => 'Remboursement par virement bancaire après traitement de votre dossier.', 'tone' => 'dark'],
                ],
            ]),
            $this->systemSection('services_partners', [
                'eyebrow' => 'Réseau de soins',
                'title' => 'Partenaires & centres de santé',
                'text' => 'Le réseau conventionné de la CAMA pour la prise en charge de ses assurés.',
            ]),
            $this->systemSection('services_faq', [
                'eyebrow' => 'Questions fréquentes',
                'title' => 'Foire aux questions',
                'text' => 'Tout ce que vous devez savoir sur vos remboursements et vos démarches auprès de la CAMA.',
                'cardTitle' => 'Une autre question ?',
                'cardText' => 'Notre équipe administrative reste à votre disposition.',
                'linkLabel' => 'Nous contacter',
                'linkHref' => '/contact',
            ]),
            $this->systemSection('services_cta', [
                'title' => 'Une question sur votre prise en charge ?',
                'text' => 'Notre équipe administrative est disponible pour vous accompagner dans vos démarches de remboursement.',
                'primaryLabel' => 'Nous contacter',
                'primaryHref' => '/contact',
                'secondaryLabel' => 'Urgence : 112',
                'secondaryHref' => 'tel:112',
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function systemSectionsForSlug(string $slug): ?array
    {
        return match ($slug) {
            'accueil' => $this->homeSystemSections(),
            'services' => $this->servicesSystemSections(),
            'apropos' => $this->aboutSystemSections(),
            'ressources' => $this->resourcesSystemSections(),
            'actualites' => $this->newsSystemSections(),
            'contact' => $this->contactSystemSections(),
            'mention_legales' => $this->legalSystemSections(),
            'accessibilite' => $this->accessibilitySystemSections(),
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function aboutSystemSections(): array
    {
        return [
            $this->systemSection('about_hero', [
                'badge' => 'Institution Nationale',
                'title' => 'Protéger ceux qui nous protègent',
                'text' => "Découvrez l'histoire, les missions et l'engagement de la Caisse d'Assurance Maladie des Armées au service du personnel de défense du Burkina Faso.",
            ]),
            $this->systemSection('about_director', [
                'eyebrow' => 'Gouvernance',
                'title' => 'Mot du Directeur Général',
                'imageSrc' => '/images/directeur.jpg',
                'badge' => 'En fonction depuis le 13 nov. 2025',
                'name' => 'Ph. Lt-Colonel Ousmane Sinaré',
                'role' => 'Directeur Général de la CAMA',
                'quote' => "Bienvenue sur l'espace d'information de la CAMA. Nous nous engageons chaque jour à moderniser nos services pour garantir une prise en charge rapide, efficace et humaine.",
                'text' => "Installé le 13 novembre 2025, le pharmacien lieutenant-colonel Ousmane Sinaré succède au médecin colonel-major Saïdou Yonaba à la tête de la CAMA. Il inscrit l'institution dans une dynamique de transformation digitale visant à simplifier les parcours de soins de nos assurés et de leurs ayants-droit.",
                'linkLabel' => 'Contacter la Direction Générale',
                'linkHref' => '/contact',
                'stats' => [
                    ['icon' => 'calendar_today', 'value' => '2020', 'label' => 'Création par décret', 'tone' => 'primary'],
                    ['icon' => 'health_and_safety', 'value' => '80%', 'label' => 'Taux de couverture', 'tone' => 'secondary'],
                ],
            ]),
            $this->systemSection('about_timeline', [
                'eyebrow' => 'Notre Histoire',
                'title' => 'Notre Évolution',
                'text' => "De la mutuelle traditionnelle à une caisse d'assurance moderne et performante.",
                'items' => [
                    ['icon' => 'gavel', 'date' => '16 Avril 2020', 'title' => 'Création de la CAMA', 'text' => 'Fondée par décret pour répondre aux exigences de la protection sociale moderne des armées.', 'tone' => 'secondary'],
                    ['icon' => 'rocket_launch', 'date' => '13 Février 2025', 'title' => 'Lancement Officiel', 'text' => 'Lancement officiel de la CAMA à Ouagadougou, au siège de l’ex-État-Major Général des Armées.', 'tone' => 'tertiary'],
                ],
                'todayBadge' => "Aujourd'hui",
                'todayTitle' => 'La CAMA, une institution pivot',
                'todayText' => "Rattachée au Ministère de la Guerre et de la Défense patriotique, elle garantit l'accès fiable et équitable aux soins de qualité pour les militaires et leurs familles.",
            ]),
            $this->systemSection('about_missions', [
                'title' => 'Nos Missions Régaliennes',
                'text' => "Le cadre d'action de la CAMA est défini par une vision stratégique de protection globale.",
                'linkLabel' => 'Consulter les textes officiels',
                'linkHref' => '/ressources',
                'items' => [
                    ['icon' => 'medical_services', 'title' => 'Gestion des Soins', 'items' => ['Remboursement des frais médicaux', 'Prise en charge des hospitalisations', 'Conventionnement hospitalier'], 'tone' => 'secondary'],
                    ['icon' => 'family_restroom', 'title' => 'Protection de la Famille', 'items' => ['Extension aux ayants-droit', 'Soutien aux veuves et orphelins', 'Programmes de prévention santé'], 'tone' => 'primary'],
                    ['icon' => 'shield_person', 'title' => 'Appui Opérationnel', 'items' => ['Soutien sanitaire en mission', 'Évacuations sanitaires', 'Expertise médicale militaire'], 'tone' => 'tertiary'],
                ],
            ]),
            $this->systemSection('about_cta', [
                'title' => "Besoin d'aide pour vos démarches ?",
                'text' => "Consultez notre guide de l'assuré ou contactez notre permanence téléphonique.",
                'primaryLabel' => "Guide de l'assuré",
                'primaryHref' => '/ressources',
                'secondaryLabel' => 'Nous appeler',
                'secondaryHref' => 'tel:+22625308103',
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resourcesSystemSections(): array
    {
        return [
            $this->systemSection('resources_hero', [
                'badgeIcon' => 'folder_open',
                'badge' => 'Centre de ressources',
                'title' => 'Ressources & documents',
                'text' => 'Téléchargez les formulaires, guides, attestations et textes de référence de la CAMA, classés par catégorie.',
            ]),
            $this->systemSection('resources_listing', [
                'emptyText' => 'Aucun document dans cette catégorie pour le moment.',
                'downloadLabel' => 'Télécharger',
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function newsSystemSections(): array
    {
        return [
            $this->systemSection('news_hero', [
                'title' => 'Actualités & Presse',
                'text' => "Suivez les dernières évolutions de la Caisse d'Assurance Maladie des Armées.",
            ]),
            $this->systemSection('news_filters', [
                'searchPlaceholder' => 'Rechercher un article…',
            ]),
            $this->systemSection('news_listing', [
                'readLabel' => 'Lire la suite',
                'cardReadLabel' => "Lire l'article",
                'emptyText' => 'Aucun article ne correspond à votre recherche.',
            ]),
            $this->systemSection('news_newsletter', [
                'title' => 'Restez informé',
                'text' => 'Recevez les dernières notes de service et actualités de la CAMA.',
                'placeholder' => 'votre@email.bf',
                'buttonLabel' => "S'abonner",
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function contactSystemSections(): array
    {
        return [
            $this->systemSection('contact_hero', [
                'eyebrow' => 'Nous trouver & nous écrire',
                'title' => 'Contact & Cartographie',
                'text' => 'Envoyez-nous un message ou consultez nos antennes sur la carte.',
            ]),
            $this->systemSection('contact_form', [
                'title' => 'Envoyez-nous un message',
                'text' => 'Demande administrative, réclamation ou information réponse sous 48h ouvrées.',
                'buttonLabel' => 'Envoyer le message',
                'successLabel' => 'Message envoyé',
            ]),
            $this->systemSection('contact_map', [
                'title' => 'Cartographie : antennes, partenaires & centres de santé',
                'text' => 'Sélectionnez un point pour afficher la carte et les coordonnées.',
                'searchPlaceholder' => 'Rechercher un point…',
                'directionsLabel' => 'Itinéraire',
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function legalSystemSections(): array
    {
        return [
            $this->htmlSection(<<<'HTML'
<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <aside class="hidden lg:block lg:col-span-3">
        <div class="sticky top-28 space-y-2 p-4 bg-surface-container-low rounded-xl border border-outline-variant">
            <h3 class="font-headline-md text-headline-md text-primary mb-4 px-2">Navigation</h3>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-all font-label-md text-label-md" href="#rgpd"><span class="material-symbols-outlined">security</span> Protection des Données</a>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-all font-label-md text-label-md" href="#droits"><span class="material-symbols-outlined">assignment_ind</span> Droits des assurés</a>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-all font-label-md text-label-md" href="#cookies"><span class="material-symbols-outlined">cookie</span> Cookies</a>
        </div>
    </aside>
    <div class="col-span-1 lg:col-span-9 space-y-stack-lg">
        <section class="bg-primary-container text-white p-12 rounded-xl relative overflow-hidden">
            <div class="relative z-10">
                <h1 class="font-display-lg text-display-lg mb-4">Informations Légales & Confidentialité</h1>
                <p class="font-body-lg text-body-lg max-w-2xl opacity-90">Engagement de la Caisse d'Assurance Maladie des Armées pour la transparence, la sécurité et le respect de la vie privée des forces armées et de leurs familles.</p>
            </div>
            <div class="absolute right-0 bottom-0 opacity-10 translate-x-1/4 translate-y-1/4"><span class="material-symbols-outlined text-[300px]">shield</span></div>
        </section>
        <article id="rgpd" class="bg-white p-stack-lg rounded-xl border border-outline-variant">
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-4">Politique de Protection des Données</h2>
            <p class="text-on-surface-variant mb-4">La CAMA s'engage à ce que la collecte et le traitement de vos données, effectués à partir du site cama.bf, soient conformes à la <strong>Loi n°001-2021/AN du 30 mars 2021</strong> portant protection des données à caractère personnel au Burkina Faso.</p>
            <div class="bg-surface-container-low p-6 rounded-lg mb-4">
                <h3 class="font-title-lg text-title-lg text-primary mb-3">Finalités du traitement</h3>
                <ul class="list-disc ml-6 space-y-2 text-on-surface-variant">
                    <li>Gestion des enrôlements et des droits aux prestations de santé.</li>
                    <li>Traitement des réclamations et assistance aux assurés.</li>
                    <li>Sécurisation des accès à l'Espace Personnel via authentification forte.</li>
                    <li>Amélioration des services administratifs militaires.</li>
                </ul>
            </div>
            <p class="text-on-surface-variant">Les données de santé sont soumises au <strong>secret médical</strong> et ne sont accessibles qu'au personnel habilité du service de santé des armées et des prestataires conventionnés, dans la limite de leurs attributions respectives.</p>
        </article>
        <article id="droits" class="bg-white p-stack-lg rounded-xl border border-outline-variant">
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-4">Droits d'accès et de rectification</h2>
            <p class="text-on-surface-variant mb-4">Conformément à la législation en vigueur, chaque assuré (militaire ou ayant-droit) dispose des droits suivants :</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="border border-outline-variant p-4 rounded-lg"><span class="material-symbols-outlined text-primary block mb-2">visibility</span><span class="font-bold block mb-1">Accès</span><span class="text-on-surface-variant text-sm">Droit de connaître les données stockées à votre sujet.</span></div>
                <div class="border border-outline-variant p-4 rounded-lg"><span class="material-symbols-outlined text-primary block mb-2">edit_note</span><span class="font-bold block mb-1">Rectification</span><span class="text-on-surface-variant text-sm">Droit de corriger des informations erronées ou incomplètes.</span></div>
                <div class="border border-outline-variant p-4 rounded-lg"><span class="material-symbols-outlined text-primary block mb-2">do_not_disturb_on</span><span class="font-bold block mb-1">Opposition</span><span class="text-on-surface-variant text-sm">Droit de s'opposer au traitement pour des motifs légitimes (hors obligations légales).</span></div>
            </div>
            <div class="p-6 border-2 border-primary/20 rounded-xl bg-surface-container-low">
                <h4 class="font-bold text-on-surface mb-2 flex items-center gap-2"><span class="material-symbols-outlined text-primary">mail</span> Comment exercer vos droits ?</h4>
                <p class="text-on-surface-variant">Toute demande doit être adressée au <strong>Délégué à la Protection des Données (DPO)</strong> de la CAMA :</p>
                <p class="text-on-surface-variant mt-2">• Par courrier : Direction Générale de la CAMA, Service Juridique, Ouagadougou.</p>
                <p class="text-on-surface-variant">• Par voie électronique : dpo@cama.bf</p>
            </div>
        </article>
        <article id="cookies" class="bg-white p-stack-lg rounded-xl border border-outline-variant">
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-4">Cookies</h2>
            <p class="text-on-surface-variant">Ce site utilise des cookies techniques indispensables au fonctionnement de la session et à la sécurité des espaces authentifiés. Aucun cookie publicitaire n'est déposé.</p>
        </article>
    </div>
</div>
HTML),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function accessibilitySystemSections(): array
    {
        return [
            $this->htmlSection(<<<'HTML'
<section class="bg-primary-container text-white p-8 md:p-10 rounded-xl mb-8">
    <h1 class="font-headline-lg text-2xl md:text-headline-lg mb-3">Déclaration d'accessibilité</h1>
    <p class="text-white/90 text-sm md:text-base max-w-3xl">La CAMA s'engage à rendre son site internet accessible conformément à l'article 47 de la loi n° 2006-009 et aux référentiels WCAG 2.1 niveau AA.</p>
    <p class="mt-4 text-sm font-semibold inline-flex items-center gap-2 bg-white/15 px-3 py-1.5 rounded-full"><span class="material-symbols-outlined text-[18px]">info</span> État : partiellement conforme</p>
</section>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <article class="bg-white p-6 rounded-xl border border-outline-variant">
        <h2 class="font-bold text-lg mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-primary">check_circle</span> Points conformes</h2>
        <ul class="space-y-2 text-sm text-on-surface-variant list-disc pl-5">
            <li>Contrastes de couleurs institutionnels (rouge CAMA / fond clair)</li>
            <li>Navigation clavier sur le menu principal et le pied de page</li>
            <li>Structure sémantique (titres, landmarks, labels de formulaires)</li>
            <li>Textes alternatifs sur les logos et images principales</li>
            <li>Redimensionnement du texte jusqu'à 200 % sans perte de contenu</li>
        </ul>
    </article>
    <article class="bg-white p-6 rounded-xl border border-outline-variant">
        <h2 class="font-bold text-lg mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-tertiary">warning</span> Améliorations en cours</h2>
        <ul class="space-y-2 text-sm text-on-surface-variant list-disc pl-5">
            <li>Sous-titrage des vidéos institutionnelles</li>
            <li>Descriptions audio enrichies du carrousel d'accueil</li>
            <li>Cartes interactives : alternative textuelle détaillée</li>
            <li>Audit complet des composants dynamiques (back-office)</li>
        </ul>
    </article>
</div>
<article class="bg-white p-6 md:p-8 rounded-xl border border-outline-variant mb-6">
    <h2 class="font-bold text-lg mb-4">Signaler un problème d'accessibilité</h2>
    <p class="text-sm text-on-surface-variant mb-4">Si vous rencontrez un obstacle à la navigation ou à la consultation des contenus, contactez notre référent accessibilité :</p>
    <div class="grid md:grid-cols-2 gap-4">
        <div class="p-4 bg-surface-container-low rounded-lg"><p class="text-xs uppercase text-on-surface-variant mb-1">E-mail</p><a class="font-bold text-primary" href="mailto:accessibilite@cama.bf">accessibilite@cama.bf</a></div>
        <div class="p-4 bg-surface-container-low rounded-lg"><p class="text-xs uppercase text-on-surface-variant mb-1">Délai de réponse</p><p class="font-bold">15 jours ouvrés maximum</p></div>
    </div>
</article>
<p class="text-xs text-on-surface-variant">Dernière revue : juin 2026 — Référentiel : WCAG 2.1 niveau AA, RGAA 4.1 (inspiration).</p>
HTML),
        ];
    }

    private function uniqueSlug(string $value, ?CmsPage $ignore = null): string
    {
        $base = Str::slug($value) ?: 'page';
        $slug = $base;
        $index = 2;

        while (CmsPage::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($builder) => $builder->whereKeyNot($ignore->id))
            ->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultSections(string $title): array
    {
        return [[
            'type' => 'section',
            'columns' => [[
                'widgets' => [
                    [
                        'type' => 'elementor_heading',
                        'content' => [
                            'title' => $title,
                            'tag' => 'h1',
                            'link' => ['url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => ''],
                            'size' => 'default',
                        ],
                        'style' => [
                            'titleColor' => '#0A083B',
                            'fontFamily' => 'Poppins',
                            'fontSize' => 49,
                            'fontSizeTablet' => 45,
                            'fontSizeMobile' => 35,
                            'fontWeight' => '700',
                            'lineHeight' => 1.1,
                            'letterSpacing' => -0.5,
                            'alignMobile' => 'center',
                            'padding' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0],
                            'paddingTablet' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0],
                            'paddingMobile' => ['t' => 10, 'r' => 0, 'b' => 0, 'l' => 0],
                            'margin' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0],
                        ],
                        'advanced' => ['animation' => 'none'],
                    ],
                    [
                        'type' => 'elementor_text_editor',
                        'content' => ['html' => '<p>Contenu de la page à compléter dans l’éditeur visuel.</p>'],
                        'style' => ['textColor' => '#5c403f', 'fontFamily' => 'Inter', 'fontSize' => 16, 'lineHeight' => 1.5],
                    ],
                ],
            ]],
        ]];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function systemSection(string $widgetType, array $content = []): array
    {
        return [
            'type' => 'section',
            'settings' => [
                'contentWidth' => 'full',
                'bgColor' => 'transparent',
                'padding' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0],
            ],
            'columns' => [[
                'type' => 'column',
                'width' => 100,
                'settings' => ['padding' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0]],
                'widgets' => [[
                    'type' => $widgetType,
                    'content' => $content,
                    'style' => [],
                ]],
            ]],
        ];
    }

    private function htmlSection(string $html): array
    {
        return [
            'type' => 'section',
            'settings' => [
                'contentWidth' => 'boxed',
                'bgColor' => 'transparent',
                'padding' => ['t' => 48, 'r' => 16, 'b' => 48, 'l' => 16],
            ],
            'columns' => [[
                'type' => 'column',
                'width' => 100,
                'settings' => ['padding' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0]],
                'widgets' => [[
                    'type' => 'html',
                    'content' => ['html' => $html],
                    'style' => [],
                ]],
            ]],
        ];
    }
}
