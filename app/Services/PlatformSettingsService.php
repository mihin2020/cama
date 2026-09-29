<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class PlatformSettingsService
{
    public const AGE_MAX_ENFANT_ABSOLU = 99;

    /** Contenu par défaut du pied de page public (modifiable depuis l'admin). */
    private const DEFAULT_FOOTER = [
        'tagline' => "Caisse d'Assurance Maladie des Armées du Burkina Faso. « La santé de nos héros, notre priorité ! »",
        'address' => 'Ouagadougou, Bilbalogho, Avenue de la Nation, côté Ouest de la Direction de la Justice Militaire',
        'phones' => ['+226 25 30 81 03', '+226 70 76 29 54'],
        'emails' => ['Cama_bf@gmail.com'],
        'hours' => 'Lundi au vendredi, 07h30 à 16h00',
        'play_store_url' => 'https://play.google.com/store/apps/details?id=com.cama.bf',
        'socials' => [
            ['network' => 'facebook', 'url' => 'https://www.facebook.com/people/Caisse-dAssurance-Maladie-des-Arm%C3%A9es-CAMA/'],
        ],
        'useful_links' => [
            ['label' => 'Ministère de la Guerre et de la Défense patriotique (MGDP)', 'url' => 'https://defense.gov.bf/accueil'],
            ['label' => 'État-Major Général des Armées (EMGA)', 'url' => 'https://www.facebook.com/emgabf'],
        ],
    ];

    private const DEFAULT_MOTIFS = [
        'Pièce justificative non conforme',
        'Lien de parenté non justifié',
        'Informations incohérentes avec le dossier militaire',
    ];

    private const DEFAULT_EMAIL_TEMPLATES = [
        'validation' => 'Bonjour, votre dossier {ref} ({beneficiaire}) a été validé.',
        'complement' => 'Bonjour, une pièce complémentaire est requise pour le dossier {ref} ({beneficiaire}) : {piece}.',
        'refus' => 'Bonjour, le dossier {ref} ({beneficiaire}) a été refusé. Motif : {motif}.',
        'message' => 'Bonjour, un message du gestionnaire CAMA concernant le dossier {ref} ({beneficiaire}) : {message}',
    ];

    private const DEFAULT_ASSURE_NOTIFY_CHANNELS = [
        'validation_in_app' => true,
        'validation_email' => false,
        'refus_in_app' => true,
        'refus_email' => false,
        'complement_in_app' => true,
        'complement_email' => false,
        'message_in_app' => true,
        'message_email' => false,
    ];

    /** Emplacements de pièces justificatives configurables à l'inscription assuré. */
    private const INSCRIPTION_DOCUMENT_SLOTS = ['doc1', 'doc2', 'doc3'];

    private const DEFAULT_INSCRIPTION_DOCUMENTS = [
        ['key' => 'doc1', 'actif' => false, 'titre' => 'Carte militaire'],
        ['key' => 'doc2', 'actif' => false, 'titre' => 'Carte CAMA'],
        ['key' => 'doc3', 'actif' => false, 'titre' => 'CNIB'],
    ];

    public function all(): array
    {
        $org = $this->orgStructure();

        return [
            'ageMaxEnfant' => $this->ageMaxEnfant(),
            'certificatScolarite' => $this->certificatScolarite(),
            'membrePhoto' => $this->membrePhoto(),
            'enfantFiliations' => $this->enfantFiliations(),
            'fifSigneeRequise' => $this->fifSigneeRequise(),
            'piecesFamille' => $this->piecesFamilleMatrix(),
            'motifsRefus' => $this->motifsRefus(),
            'emailTemplates' => $this->emailTemplates(),
            'affectationMode' => $this->get('affectation_mode') ?? 'manuelle',
            'validation2Niveaux' => (bool) ($this->get('validation_2niveaux') ?? false),
            'orgStructure' => $org,
            'inscriptionDocuments' => $this->inscriptionDocuments(),
            'assureNotifyChannels' => $this->assureNotifyChannels(),
        ];
    }

    /**
     * Contenu du pied de page public (défauts fusionnés avec les valeurs enregistrées).
     *
     * @return array<string, mixed>
     */
    public function footer(): array
    {
        $stored = $this->get('footer');
        $stored = is_array($stored) ? $stored : [];

        $links = is_array($stored['useful_links'] ?? null) ? $stored['useful_links'] : self::DEFAULT_FOOTER['useful_links'];
        $phones = is_array($stored['phones'] ?? null) ? $stored['phones'] : self::DEFAULT_FOOTER['phones'];

        // E-mails : nouvelle liste, avec repli sur l'ancien champ unique « email ».
        if (is_array($stored['emails'] ?? null)) {
            $emails = $stored['emails'];
        } elseif (! empty($stored['email'])) {
            $emails = [$stored['email']];
        } else {
            $emails = self::DEFAULT_FOOTER['emails'];
        }

        // Réseaux sociaux : nouvelle liste, avec repli sur l'ancien champ « facebook_url ».
        if (is_array($stored['socials'] ?? null)) {
            $socials = $stored['socials'];
        } elseif (! empty($stored['facebook_url'])) {
            $socials = [['network' => 'facebook', 'url' => $stored['facebook_url']]];
        } else {
            $socials = self::DEFAULT_FOOTER['socials'];
        }

        return [
            'tagline' => trim((string) ($stored['tagline'] ?? self::DEFAULT_FOOTER['tagline'])),
            'address' => trim((string) ($stored['address'] ?? self::DEFAULT_FOOTER['address'])),
            'phones' => array_values(array_filter(array_map(fn ($p) => trim((string) $p), $phones))),
            'emails' => array_values(array_filter(array_map(fn ($e) => trim((string) $e), $emails))),
            'hours' => trim((string) ($stored['hours'] ?? self::DEFAULT_FOOTER['hours'])),
            'play_store_url' => trim((string) ($stored['play_store_url'] ?? self::DEFAULT_FOOTER['play_store_url'])),
            'socials' => array_values(array_filter(array_map(function ($social) {
                $social = is_array($social) ? $social : [];
                $network = trim((string) ($social['network'] ?? ''));
                $url = trim((string) ($social['url'] ?? ''));

                return ($network !== '' && $url !== '') ? ['network' => $network, 'url' => $url] : null;
            }, $socials))),
            'useful_links' => array_values(array_filter(array_map(function ($link) {
                $link = is_array($link) ? $link : [];
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));

                return ($label !== '' && $url !== '') ? ['label' => $label, 'url' => $url] : null;
            }, $links))),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFooter(array $data): void
    {
        $phones = is_array($data['phones'] ?? null) ? $data['phones'] : [];
        $emails = is_array($data['emails'] ?? null) ? $data['emails'] : [];
        $socials = is_array($data['socials'] ?? null) ? $data['socials'] : [];
        $links = is_array($data['useful_links'] ?? null) ? $data['useful_links'] : [];

        $this->set('footer', [
            'tagline' => trim((string) ($data['tagline'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')),
            'phones' => array_values(array_filter(array_map(fn ($p) => trim((string) $p), $phones))),
            'emails' => array_values(array_filter(array_map(fn ($e) => trim((string) $e), $emails))),
            'hours' => trim((string) ($data['hours'] ?? '')),
            'play_store_url' => trim((string) ($data['play_store_url'] ?? '')),
            'socials' => array_values(array_filter(array_map(function ($social) {
                $social = is_array($social) ? $social : [];
                $network = trim((string) ($social['network'] ?? ''));
                $url = trim((string) ($social['url'] ?? ''));

                return ($network !== '' && $url !== '') ? ['network' => $network, 'url' => $url] : null;
            }, $socials))),
            'useful_links' => array_values(array_filter(array_map(function ($link) {
                $link = is_array($link) ? $link : [];
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));

                return ($label !== '' && $url !== '') ? ['label' => $label, 'url' => $url] : null;
            }, $links))),
        ]);

        // Le pied de page est partagé via PublicSiteService::shared() (cache 5 min).
        Cache::forget('public_site.shared.v1');
    }

    /**
     * Les 3 emplacements de documents d'inscription (fusion défauts + stockés).
     *
     * @return array<int, array{key: string, actif: bool, titre: string}>
     */
    public function inscriptionDocuments(): array
    {
        $stored = $this->get('inscription_documents');
        $storedByKey = [];

        if (is_array($stored)) {
            foreach ($stored as $slot) {
                if (is_array($slot) && ! empty($slot['key'])) {
                    $storedByKey[$slot['key']] = $slot;
                }
            }
        }

        return array_map(function (array $default) use ($storedByKey) {
            $slot = $storedByKey[$default['key']] ?? [];

            return [
                'key' => $default['key'],
                'actif' => (bool) ($slot['actif'] ?? $default['actif']),
                'titre' => trim((string) ($slot['titre'] ?? $default['titre'])),
            ];
        }, self::DEFAULT_INSCRIPTION_DOCUMENTS);
    }

    /**
     * Emplacements réellement proposés à l'inscription (actifs + titre renseigné).
     *
     * @return array<int, array{key: string, titre: string}>
     */
    public function activeInscriptionDocuments(): array
    {
        return array_values(array_map(
            fn (array $slot) => ['key' => $slot['key'], 'titre' => $slot['titre']],
            array_filter(
                $this->inscriptionDocuments(),
                fn (array $slot) => $slot['actif'] && $slot['titre'] !== '',
            ),
        ));
    }

    public function updateInscriptionDocuments(array $documents): void
    {
        $byKey = [];
        foreach ($documents as $slot) {
            if (is_array($slot) && ! empty($slot['key'])) {
                $byKey[$slot['key']] = $slot;
            }
        }

        $normalized = array_map(function (array $default) use ($byKey) {
            $slot = $byKey[$default['key']] ?? [];
            $titre = trim((string) ($slot['titre'] ?? ''));

            return [
                'key' => $default['key'],
                'actif' => (bool) ($slot['actif'] ?? false) && $titre !== '',
                'titre' => $titre,
            ];
        }, self::DEFAULT_INSCRIPTION_DOCUMENTS);

        $this->set('inscription_documents', $normalized);
    }

    public function ageMaxEnfant(): int
    {
        $value = (int) ($this->get('age_max_enfant') ?? config('cama.age_max_enfant', 26));

        return max(1, min(self::AGE_MAX_ENFANT_ABSOLU, $value));
    }

    /**
     * @return array{actif: bool, label: string}
     */
    public function certificatScolarite(): array
    {
        $stored = $this->get('certificat_scolarite');
        $defaultLabel = (string) config('cama.certificat_scolarite_label', 'Certificat de scolarité');

        if (! is_array($stored)) {
            return [
                'actif' => (bool) config('cama.certificat_scolarite_actif', true),
                'label' => $defaultLabel,
            ];
        }

        $label = trim((string) ($stored['label'] ?? $defaultLabel));

        return [
            'actif' => (bool) ($stored['actif'] ?? config('cama.certificat_scolarite_actif', true)),
            'label' => $label !== '' ? $label : $defaultLabel,
        ];
    }

    /**
     * @return array{conjoint: array{actif: bool, required: bool}, enfant: array{actif: bool, required: bool}}
     */
    public function membrePhoto(): array
    {
        $defaults = config('cama.membre_photo', [
            'conjoint' => ['actif' => true, 'required' => false],
            'enfant' => ['actif' => true, 'required' => false],
        ]);
        $stored = $this->get('membre_photo');

        $normalize = function (array $default, mixed $row): array {
            $row = is_array($row) ? $row : [];

            return [
                'actif' => (bool) ($row['actif'] ?? $default['actif'] ?? true),
                'required' => (bool) ($row['required'] ?? $default['required'] ?? false),
            ];
        };

        return [
            'conjoint' => $normalize($defaults['conjoint'] ?? [], is_array($stored) ? ($stored['conjoint'] ?? null) : null),
            'enfant' => $normalize($defaults['enfant'] ?? [], is_array($stored) ? ($stored['enfant'] ?? null) : null),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, actif: bool, pieces: array<int, array{key: string, label: string, required: bool}>}>
     */
    public function enfantFiliations(): array
    {
        $defaults = config('cama.enfant_filiations', []);
        $stored = $this->get('enfant_filiations');
        $storedByKey = [];

        if (is_array($stored)) {
            foreach ($stored as $row) {
                if (is_array($row) && ! empty($row['key'])) {
                    $storedByKey[$row['key']] = $row;
                }
            }
        }

        return array_values(array_map(function (array $default) use ($storedByKey) {
            $row = $storedByKey[$default['key']] ?? [];
            $piecesSource = is_array($row['pieces'] ?? null) ? $row['pieces'] : ($default['pieces'] ?? []);

            return [
                'key' => $default['key'],
                'label' => trim((string) ($row['label'] ?? $default['label'])),
                'actif' => (bool) ($row['actif'] ?? $default['actif'] ?? true),
                'pieces' => array_values(array_map(function ($piece) {
                    $piece = is_array($piece) ? $piece : [];

                    return [
                        'key' => (string) ($piece['key'] ?? ''),
                        'label' => trim((string) ($piece['label'] ?? '')),
                        'required' => (bool) ($piece['required'] ?? false),
                    ];
                }, $piecesSource)),
            ];
        }, $defaults));
    }

    public function fifSigneeRequise(): bool
    {
        $stored = $this->get('fif_signee_requise');

        if ($stored === null) {
            return (bool) config('cama.fif_signee_requise', false);
        }

        return (bool) $stored;
    }

    /**
     * Matrice des pièces famille (conjoint + filiations enfants actives) pour le wizard.
     *
     * @return array<string, array<int, array{key: string, label: string, required: bool}>>
     */
    public function piecesFamilleMatrix(): array
    {
        $matrix = [
            'Conjoint(e)' => config('cama.pieces_famille.Conjoint(e)', []),
        ];

        foreach ($this->enfantFiliations() as $filiation) {
            if (! ($filiation['actif'] ?? false)) {
                continue;
            }
            $label = $filiation['label'];
            $matrix[$label] = array_values(array_filter(
                $filiation['pieces'] ?? [],
                fn (array $p) => ($p['key'] ?? '') !== '',
            ));
        }

        return $matrix;
    }

    /**
     * Liens famille proposés à l'assuré (conjoint + filiations enfants actives).
     *
     * @return array<int, string>
     */
    public function liensFamilleActifs(): array
    {
        $liens = ['Conjoint(e)'];
        foreach ($this->enfantFiliations() as $filiation) {
            if ($filiation['actif'] ?? false) {
                $liens[] = $filiation['label'];
            }
        }

        return $liens;
    }

    public function renderEmailTemplate(string $key, array $vars = []): string
    {
        $template = $this->emailTemplates()[$key] ?? '';

        foreach ($vars as $name => $value) {
            $template = str_replace('{'.$name.'}', (string) $value, $template);
        }

        return $template;
    }

    public function orgStructure(): array
    {
        $defaults = config('cama.org_structure');
        $row = PlatformSetting::query()->find('org_structure');

        if ($row === null) {
            return [
                'armees' => $defaults['armees'] ?? [],
                'categories' => $defaults['categories'] ?? [],
                'grades' => config('cama.grades', []),
                'groupesSanguins' => config('cama.groupes_sanguins', []),
                'regions' => $defaults['regions'] ?? [],
            ];
        }

        $stored = is_array($row->value) ? $row->value : [];
        $gradeDefaults = ['grades' => config('cama.grades', [])];

        return [
            'armees' => $this->resolvedOrgList($stored, 'armees', $defaults),
            'categories' => $this->resolvedOrgList($stored, 'categories', $defaults),
            'grades' => $this->resolvedOrgList($stored, 'grades', $gradeDefaults),
            'groupesSanguins' => $this->resolvedOrgList(
                $stored,
                'groupesSanguins',
                $defaults,
                altKeys: ['groupes_sanguins'],
                fallback: config('cama.groupes_sanguins', []),
            ),
            'regions' => array_key_exists('regions', $stored)
                ? array_values($stored['regions'] ?? [])
                : ($defaults['regions'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $defaults
     * @param  array<int, string>  $altKeys
     * @return array<int, string>
     */
    private function resolvedOrgList(array $stored, string $key, array $defaults, array $altKeys = [], ?array $fallback = null): array
    {
        if (array_key_exists($key, $stored)) {
            return array_values(array_filter(array_map('trim', (array) $stored[$key])));
        }

        foreach ($altKeys as $altKey) {
            if (array_key_exists($altKey, $stored)) {
                return array_values(array_filter(array_map('trim', (array) $stored[$altKey])));
            }
        }

        return array_values($fallback ?? $defaults[$key] ?? []);
    }

    public function motifsRefus(): array
    {
        $stored = $this->get('motifs_refus');

        return is_array($stored) && count($stored) ? $stored : self::DEFAULT_MOTIFS;
    }

    public function emailTemplates(): array
    {
        $stored = $this->get('email_templates');

        if (! is_array($stored)) {
            return self::DEFAULT_EMAIL_TEMPLATES;
        }

        return [
            'validation' => $stored['validation'] ?? self::DEFAULT_EMAIL_TEMPLATES['validation'],
            'complement' => $stored['complement'] ?? self::DEFAULT_EMAIL_TEMPLATES['complement'],
            'refus' => $stored['refus'] ?? self::DEFAULT_EMAIL_TEMPLATES['refus'],
            'message' => $stored['message'] ?? self::DEFAULT_EMAIL_TEMPLATES['message'],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function assureNotifyChannels(): array
    {
        $stored = $this->get('assure_notify_channels');

        if (! is_array($stored)) {
            return self::DEFAULT_ASSURE_NOTIFY_CHANNELS;
        }

        $merged = [];
        foreach (self::DEFAULT_ASSURE_NOTIFY_CHANNELS as $key => $default) {
            $merged[$key] = array_key_exists($key, $stored) ? (bool) $stored[$key] : $default;
        }

        return $merged;
    }

    public function validation2Niveaux(): bool
    {
        return (bool) ($this->get('validation_2niveaux') ?? false);
    }

    public function updateMembres(int $ageMaxEnfant, ?bool $certificatActif = null, ?string $certificatLabel = null): void
    {
        $this->set('age_max_enfant', max(1, min(self::AGE_MAX_ENFANT_ABSOLU, $ageMaxEnfant)));

        if ($certificatActif !== null || $certificatLabel !== null) {
            $current = $this->certificatScolarite();
            $label = trim((string) ($certificatLabel ?? $current['label']));
            $this->set('certificat_scolarite', [
                'actif' => $certificatActif ?? $current['actif'],
                'label' => $label !== '' ? $label : $current['label'],
            ]);
        }
    }

    /**
     * @param  array{conjoint?: array{actif?: bool, required?: bool}, enfant?: array{actif?: bool, required?: bool}}  $photos
     */
    public function updateMembrePhoto(array $photos): void
    {
        $current = $this->membrePhoto();
        $this->set('membre_photo', [
            'conjoint' => [
                'actif' => (bool) ($photos['conjoint']['actif'] ?? $current['conjoint']['actif']),
                'required' => (bool) ($photos['conjoint']['required'] ?? $current['conjoint']['required']),
            ],
            'enfant' => [
                'actif' => (bool) ($photos['enfant']['actif'] ?? $current['enfant']['actif']),
                'required' => (bool) ($photos['enfant']['required'] ?? $current['enfant']['required']),
            ],
        ]);
    }

    /**
     * @param  array<int, array{key: string, label?: string, actif?: bool, pieces?: array}>  $filiations
     */
    public function updateEnfantFiliations(array $filiations): void
    {
        $defaults = config('cama.enfant_filiations', []);
        $byKey = [];
        foreach ($filiations as $row) {
            if (is_array($row) && ! empty($row['key'])) {
                $byKey[$row['key']] = $row;
            }
        }

        $normalized = array_map(function (array $default) use ($byKey) {
            $row = $byKey[$default['key']] ?? [];
            $piecesSource = is_array($row['pieces'] ?? null) ? $row['pieces'] : ($default['pieces'] ?? []);

            return [
                'key' => $default['key'],
                'label' => trim((string) ($row['label'] ?? $default['label'])),
                'actif' => (bool) ($row['actif'] ?? $default['actif'] ?? true),
                'pieces' => array_values(array_map(function ($piece) {
                    $piece = is_array($piece) ? $piece : [];

                    return [
                        'key' => (string) ($piece['key'] ?? ''),
                        'label' => trim((string) ($piece['label'] ?? '')),
                        'required' => (bool) ($piece['required'] ?? false),
                    ];
                }, array_filter($piecesSource, fn ($p) => is_array($p) && ($p['key'] ?? '') !== ''))),
            ];
        }, $defaults);

        $this->set('enfant_filiations', $normalized);
    }

    public function updateFifSigneeRequise(bool $requise): void
    {
        $this->set('fif_signee_requise', $requise);
    }

    public function updateRetention(int $years): void
    {
        $this->set('retention_years', max(1, min(30, $years)));
    }

    public function updateAffectation(string $mode, bool $validation2Niveaux): void
    {
        $this->set('affectation_mode', $mode);
        $this->set('validation_2niveaux', $validation2Niveaux);
    }

    public function roundRobinCursor(): ?string
    {
        return $this->get('affectation_rr_last');
    }

    public function rememberRoundRobinCursor(string $gestionnaire): void
    {
        $this->set('affectation_rr_last', $gestionnaire);
    }

    public function updateLibelles(array $motifs, array $templates, array $notifyChannels = []): void
    {
        $motifs = array_values(array_filter(array_map('trim', $motifs)));
        $this->set('motifs_refus', $motifs ?: self::DEFAULT_MOTIFS);
        $this->set('email_templates', [
            'validation' => $templates['validation'] ?? self::DEFAULT_EMAIL_TEMPLATES['validation'],
            'complement' => $templates['complement'] ?? self::DEFAULT_EMAIL_TEMPLATES['complement'],
            'refus' => $templates['refus'] ?? self::DEFAULT_EMAIL_TEMPLATES['refus'],
            'message' => $templates['message'] ?? self::DEFAULT_EMAIL_TEMPLATES['message'],
        ]);

        if ($notifyChannels !== []) {
            $normalized = [];
            foreach (self::DEFAULT_ASSURE_NOTIFY_CHANNELS as $key => $default) {
                $normalized[$key] = array_key_exists($key, $notifyChannels) ? (bool) $notifyChannels[$key] : $default;
            }
            $this->set('assure_notify_channels', $normalized);
        }
    }

    public function updateOrgStructure(array $structure): void
    {
        $this->set('org_structure', [
            'armees' => array_values(array_filter(array_map('trim', $structure['armees'] ?? []))),
            'categories' => array_values(array_filter(array_map('trim', $structure['categories'] ?? []))),
            'grades' => array_values(array_filter(array_map('trim', $structure['grades'] ?? []))),
            'groupesSanguins' => array_values(array_filter(array_map('trim', $structure['groupesSanguins'] ?? []))),
            'regions' => $this->sanitizeRegions($structure['regions'] ?? []),
        ]);
    }

    /**
     * @param  array<int, mixed>  $regions
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeRegions(array $regions): array
    {
        return array_values(array_filter(array_map(function ($region) {
            if (! is_array($region)) {
                return null;
            }

            $libelle = trim((string) ($region['libelle'] ?? ''));
            $corps = $this->sanitizeOrgNodes($region['corps'] ?? [], 'services');

            if ($libelle === '' && empty($corps)) {
                return null;
            }

            return [
                'id' => trim((string) ($region['id'] ?? '')) ?: 'reg-'.uniqid(),
                'libelle' => $libelle ?: 'Région sans libellé',
                'corps' => $corps,
            ];
        }, $regions)));
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeOrgNodes(array $nodes, ?string $childKey): array
    {
        $nextKeys = [
            'services' => 'sections',
            'sections' => 'sous_sections',
            'sous_sections' => null,
        ];
        $nextKey = $childKey ? ($nextKeys[$childKey] ?? null) : null;

        return array_values(array_filter(array_map(function ($node) use ($childKey, $nextKey) {
            if (! is_array($node)) {
                return null;
            }

            $libelle = trim((string) ($node['libelle'] ?? ''));
            $children = $childKey ? $this->sanitizeOrgNodes($node[$childKey] ?? [], $nextKey) : [];

            if ($libelle === '' && empty($children)) {
                return null;
            }

            $result = [
                'id' => trim((string) ($node['id'] ?? '')) ?: 'n-'.uniqid(),
                'libelle' => $libelle,
            ];

            if ($childKey !== null) {
                $result[$childKey] = $children;
            }

            return $result;
        }, $nodes)));
    }

    /**
     * @param  array<int, string>  $emails
     */
    public function updateContactRecipients(array $emails): void
    {
        $valid = array_values(array_filter(array_map(
            fn ($email) => filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) ? trim((string) $email) : null,
            $emails,
        )));

        $this->set('contact_recipients', $valid);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = PlatformSetting::query()->find($key);

        return $row?->value ?? $default;
    }

    private function set(string $key, mixed $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
