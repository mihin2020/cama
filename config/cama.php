<?php

return [
    'grades' => [
        'Soldat de 2e classe', 'Soldat de 1re classe', 'Caporal', 'Caporal-chef',
        'Sergent', 'Sergent-chef', 'Adjudant', 'Adjudant-chef',
        'Aspirant', 'Sous-lieutenant', 'Lieutenant', 'Capitaine',
        'Commandant', 'Lieutenant-colonel', 'Colonel', 'Colonel-major', 'Général',
    ],

    // Hiérarchie de rattachement (Région/Groupement → Corps → Service → Section → Sous-section).
    // Défaut modifiable dans Admin > Paramètres > Structure militaire.
    // Source publique à valider et compléter par l'état-major (services/sections notamment).
    'org_structure' => [
        'armees' => ['Armée de Terre', 'Armée de l\'Air', 'Gendarmerie Nationale', 'Sapeurs-Pompiers Militaires'],
        'categories' => ['Officier', 'Sous-officier', 'Militaire du rang', 'Personnel civil'],
        'regions' => [
            [
                'id' => 'rm-1',
                'libelle' => '1re Région Militaire — Kaya',
                'corps' => [
                    ['id' => 'rm1-c1', 'libelle' => '10e RCAS — Kaya', 'services' => []],
                    ['id' => 'rm1-c2', 'libelle' => '12e RIC — Ouahigouya', 'services' => []],
                ],
            ],
            [
                'id' => 'rm-2',
                'libelle' => '2e Région Militaire — Bobo-Dioulasso',
                'corps' => [
                    ['id' => 'rm2-c1', 'libelle' => '20e RCAS — Bobo-Dioulasso', 'services' => []],
                    ['id' => 'rm2-c2', 'libelle' => '22e RIC — Gaoua', 'services' => []],
                    ['id' => 'rm2-c3', 'libelle' => '24e RIA — Banfora', 'services' => []],
                ],
            ],
            [
                'id' => 'rm-3',
                'libelle' => '3e Région Militaire — Ouagadougou',
                'corps' => [
                    ['id' => 'rm3-c1', 'libelle' => '30e RCAS — Ouagadougou', 'services' => []],
                ],
            ],
            [
                'id' => 'rm-4',
                'libelle' => '4e Région Militaire — Dori',
                'corps' => [
                    ['id' => 'rm4-c1', 'libelle' => '41e RIC — Dori', 'services' => []],
                    ['id' => 'rm4-c2', 'libelle' => '42e RIC — Djibo', 'services' => []],
                ],
            ],
            [
                'id' => 'rm-5',
                'libelle' => '5e Région Militaire — Dédougou',
                'corps' => [
                    ['id' => 'rm5-c1', 'libelle' => '51e RIC — Dédougou', 'services' => []],
                    ['id' => 'rm5-c2', 'libelle' => '52e RIC — Tougan', 'services' => []],
                    ['id' => 'rm5-c3', 'libelle' => '53e RIC — Nouna', 'services' => []],
                ],
            ],
            [
                'id' => 'rm-6',
                'libelle' => '6e Région Militaire — Fada N\'Gourma',
                'corps' => [
                    ['id' => 'rm6-c1', 'libelle' => '61e RIC — Tenkodogo', 'services' => []],
                    ['id' => 'rm6-c2', 'libelle' => '64e RIA — Fada N\'Gourma', 'services' => []],
                ],
            ],
            [
                'id' => 'gcp',
                'libelle' => 'Groupement Commando Parachutiste (GCP) — Bobo-Dioulasso',
                'corps' => [
                    ['id' => 'gcp-c1', 'libelle' => '25e RPC — Bobo-Dioulasso', 'services' => []],
                    ['id' => 'gcp-c2', 'libelle' => 'CITAP', 'services' => []],
                ],
            ],
            [
                'id' => 'grp-artillerie',
                'libelle' => 'Groupement d\'Artillerie — Kaya',
                'corps' => [
                    ['id' => 'grp-art-c1', 'libelle' => 'Groupement d\'Artillerie', 'services' => []],
                ],
            ],
            [
                'id' => 'cecf',
                'libelle' => 'CECF',
                'corps' => [
                    ['id' => 'cecf-c1', 'libelle' => 'GIFA', 'services' => []],
                    ['id' => 'cecf-c2', 'libelle' => 'ENSOA', 'services' => []],
                    ['id' => 'cecf-c3', 'libelle' => 'AMGN', 'services' => []],
                ],
            ],
            [
                'id' => 'gr',
                'libelle' => 'Garde Républicaine (GR) — Ouagadougou',
                'corps' => [
                    ['id' => 'gr-c1', 'libelle' => 'Groupement Républicain (GR)', 'services' => []],
                ],
            ],
            [
                'id' => 'forsatec-horonya',
                'libelle' => 'FORSATEC / HORONYA',
                'corps' => [
                    ['id' => 'fh-c1', 'libelle' => 'FORSATEC', 'services' => []],
                    ['id' => 'fh-c2', 'libelle' => 'HORONYA', 'services' => []],
                ],
            ],
            [
                'id' => 'gca',
                'libelle' => 'Groupement Central des Armées (GCA)',
                'corps' => [
                    ['id' => 'gca-c1', 'libelle' => 'BCS', 'services' => []],
                    ['id' => 'gca-c2', 'libelle' => 'BGM', 'services' => []],
                    ['id' => 'gca-c3', 'libelle' => 'BTSA', 'services' => []],
                    ['id' => 'gca-c4', 'libelle' => 'BMAT', 'services' => []],
                    ['id' => 'gca-c5', 'libelle' => 'BTR', 'services' => []],
                    ['id' => 'gca-c6', 'libelle' => 'Bataillon d\'Intervention (BAT. INT)', 'services' => []],
                    ['id' => 'gca-c7', 'libelle' => 'Bataillon Santé (BAT. SANTÉ)', 'services' => []],
                    ['id' => 'gca-c8', 'libelle' => 'BJM', 'services' => []],
                ],
            ],
            [
                'id' => 'cnec',
                'libelle' => 'Centre National d\'Entraînement Commando (CNEC)',
                'corps' => [
                    ['id' => 'cnec-c1', 'libelle' => 'CNEC', 'services' => []],
                ],
            ],
            [
                'id' => 'bnsp',
                'libelle' => 'Brigade Nationale de Sapeurs-Pompiers (BNSP)',
                'corps' => [
                    ['id' => 'bnsp-c1', 'libelle' => 'CCS BNSP — Ouagadougou', 'services' => []],
                    ['id' => 'bnsp-c2', 'libelle' => '1re CIE BNSP — Ouagadougou', 'services' => []],
                    ['id' => 'bnsp-c3', 'libelle' => '2e CIE BNSP — Bobo-Dioulasso', 'services' => []],
                    ['id' => 'bnsp-c4', 'libelle' => '3e CIE BNSP — Koudougou', 'services' => []],
                    ['id' => 'bnsp-c5', 'libelle' => '4e CIE BNSP — Ouahigouya', 'services' => []],
                    ['id' => 'bnsp-c6', 'libelle' => '5e CIE BNSP — Banfora', 'services' => []],
                    ['id' => 'bnsp-c7', 'libelle' => '6e CIE BNSP — Boromo', 'services' => []],
                    ['id' => 'bnsp-c8', 'libelle' => '7e CIE BNSP — Kaya', 'services' => []],
                    ['id' => 'bnsp-c9', 'libelle' => '8e CIE BNSP — Ouagadougou', 'services' => []],
                    ['id' => 'bnsp-c10', 'libelle' => '9e CIE BNSP — Koupéla', 'services' => []],
                    ['id' => 'bnsp-c11', 'libelle' => '11e CIE BNSP — Ouagadougou', 'services' => []],
                    ['id' => 'bnsp-c12', 'libelle' => '12e CIE BNSP — Dori', 'services' => []],
                    ['id' => 'bnsp-c13', 'libelle' => 'ENASAP — Bobo-Dioulasso', 'services' => []],
                ],
            ],
            [
                'id' => 'gfs',
                'libelle' => 'Groupement des Forces Spéciales (GFS)',
                'corps' => [
                    ['id' => 'gfs-c1', 'libelle' => 'Commando GAMBO', 'services' => []],
                ],
            ],
            [
                'id' => 'ra-1',
                'libelle' => '1re Région Aérienne',
                'corps' => [
                    ['id' => 'ra1-c1', 'libelle' => 'Base Aérienne 511', 'services' => []],
                    ['id' => 'ra1-c2', 'libelle' => 'Base Aérienne 134 — Kaya', 'services' => []],
                    ['id' => 'ra1-c3', 'libelle' => 'Base Aérienne 111 — Fada N\'Gourma', 'services' => []],
                ],
            ],
            [
                'id' => 'ra-2',
                'libelle' => '2e Région Aérienne',
                'corps' => [
                    ['id' => 'ra2-c1', 'libelle' => 'Base Aérienne 210', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-1',
                'libelle' => '1er Groupement d\'Intervention Rapide (GIR) — Ouagadougou',
                'corps' => [
                    ['id' => 'gir1-c1', 'libelle' => '1er BIR — Ouagadougou', 'services' => []],
                    ['id' => 'gir1-c2', 'libelle' => '2e BIR — Ouagadougou', 'services' => []],
                    ['id' => 'gir1-c3', 'libelle' => '3e BIR — Ouagadougou', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-2',
                'libelle' => '2e Groupement d\'Intervention Rapide (GIR) — Bobo-Dioulasso',
                'corps' => [
                    ['id' => 'gir2-c1', 'libelle' => '4e BIR — Ouagadougou', 'services' => []],
                    ['id' => 'gir2-c2', 'libelle' => '5e BIR — Ouagadougou', 'services' => []],
                    ['id' => 'gir2-c3', 'libelle' => '6e BIR — Ouagadougou', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-3',
                'libelle' => '3e Groupement d\'Intervention Rapide (GIR) — Kaya',
                'corps' => [
                    ['id' => 'gir3-c1', 'libelle' => '7e BIR — Bobo-Dioulasso', 'services' => []],
                    ['id' => 'gir3-c2', 'libelle' => '15e BIR — Gaoua', 'services' => []],
                    ['id' => 'gir3-c3', 'libelle' => '17e BIR — Banfora', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-4',
                'libelle' => '4e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir4-c1', 'libelle' => '10e BIR — Dédougou', 'services' => []],
                    ['id' => 'gir4-c2', 'libelle' => '18e BIR — Solenzo', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-5',
                'libelle' => '5e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir5-c1', 'libelle' => '23e BIR — Toma', 'services' => []],
                    ['id' => 'gir5-c2', 'libelle' => '29e BIR — Di', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-6',
                'libelle' => '6e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir6-c1', 'libelle' => '14e BIR — Ouahigouya', 'services' => []],
                    ['id' => 'gir6-c2', 'libelle' => '21e BIR — Titao', 'services' => []],
                    ['id' => 'gir6-c3', 'libelle' => '22e BIR — Djibo', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-7',
                'libelle' => '7e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir7-c1', 'libelle' => '8e BIR — Kaya', 'services' => []],
                    ['id' => 'gir7-c2', 'libelle' => '16e BIR — Kongoussi', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-8',
                'libelle' => '8e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir8-c1', 'libelle' => '9e BIR — Dori', 'services' => []],
                    ['id' => 'gir8-c2', 'libelle' => '28e BIR — Arbinda', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-9',
                'libelle' => '9e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir9-c1', 'libelle' => '12e BIR — Fada N\'Gourma', 'services' => []],
                    ['id' => 'gir9-c2', 'libelle' => '19e BIR — Bogandé', 'services' => []],
                    ['id' => 'gir9-c3', 'libelle' => '20e BIR — Gayéri', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-10',
                'libelle' => '10e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir10-c1', 'libelle' => '24e BIR — Kantchari', 'services' => []],
                    ['id' => 'gir10-c2', 'libelle' => '26e BIR — Ougarou', 'services' => []],
                    ['id' => 'gir10-c3', 'libelle' => '27e BIR — Diapaga', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-11',
                'libelle' => '11e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir11-c1', 'libelle' => '25e BIR — Pama', 'services' => []],
                ],
            ],
            [
                'id' => 'gir-12',
                'libelle' => '12e Groupement d\'Intervention Rapide (GIR)',
                'corps' => [
                    ['id' => 'gir12-c1', 'libelle' => '11e BIR — Tenkodogo', 'services' => []],
                    ['id' => 'gir12-c2', 'libelle' => '30e BIR — Pitou', 'services' => []],
                ],
            ],
        ],
    ],

    'liens_famille' => [
        'Conjoint(e)',
        'Enfant biologique',
        'Enfant du conjoint',
        'Enfant adopté',
        'Parent',
        'Autre',
    ],

    'dossier_statuts' => [
        'Brouillon',
        'Soumis',
        'En instruction',
        'Pièce manquante demandée',
        'En attente supervision',
        'Validé',
        'Refusé',
        'Retiré',
    ],

    'groupes_sanguins' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],

    'age_max_enfant' => 26,

    'fif_signee_requise' => false,

    'certificat_scolarite_actif' => true,

    'certificat_scolarite_label' => 'Certificat de scolarité',

    'membre_photo' => [
        'conjoint' => ['actif' => true, 'required' => false],
        'enfant' => ['actif' => true, 'required' => false],
    ],

    'enfant_filiations' => [
        [
            'key' => 'enfant_biologique',
            'label' => 'Enfant biologique',
            'actif' => true,
            'pieces' => [
                ['key' => 'acte_naissance', 'label' => 'Acte de naissance', 'required' => true],
                ['key' => 'cnib_parent', 'label' => 'Copie CNIB du parent', 'required' => true],
            ],
        ],
        [
            'key' => 'enfant_conjoint',
            'label' => 'Enfant du conjoint',
            'actif' => true,
            'pieces' => [
                ['key' => 'acte_naissance_enfant', 'label' => 'Acte de naissance', 'required' => true],
                ['key' => 'acte_mariage_parent', 'label' => 'Acte de mariage avec le parent', 'required' => true],
                ['key' => 'piece_garde', 'label' => 'Pièce justifiant la garde', 'required' => false],
            ],
        ],
        [
            'key' => 'enfant_adopte',
            'label' => 'Enfant adopté',
            'actif' => true,
            'pieces' => [
                ['key' => 'acte_naissance', 'label' => 'Acte de naissance', 'required' => true],
                ['key' => 'certificat_tutelle', 'label' => 'Certificat de tutelle', 'required' => true],
            ],
        ],
    ],

    'pieces_famille' => [
        'Conjoint(e)' => [
            ['key' => 'acte_mariage', 'label' => 'Acte de mariage', 'required' => true],
            ['key' => 'cnib_conjoint', 'label' => 'Copie CNIB du conjoint', 'required' => true],
            ['key' => 'acte_divorce', 'label' => 'Acte de divorce du précédent conjoint', 'required' => false],
        ],
        'Enfant biologique' => [
            ['key' => 'acte_naissance', 'label' => 'Acte de naissance', 'required' => true],
            ['key' => 'cnib_parent', 'label' => 'Copie CNIB du parent', 'required' => true],
        ],
        'Enfant du conjoint' => [
            ['key' => 'acte_naissance_enfant', 'label' => 'Acte de naissance', 'required' => true],
            ['key' => 'acte_mariage_parent', 'label' => 'Acte de mariage avec le parent', 'required' => true],
            ['key' => 'piece_garde', 'label' => 'Pièce justifiant la garde', 'required' => false],
        ],
        'Enfant adopté' => [
            ['key' => 'acte_naissance', 'label' => 'Acte de naissance', 'required' => true],
            ['key' => 'certificat_tutelle', 'label' => 'Certificat de tutelle', 'required' => true],
        ],
    ],

    'piece_labels' => [
        'acte_mariage' => 'Acte de mariage',
        'cnib_conjoint' => 'Copie CNIB du conjoint',
        'acte_divorce' => 'Acte de divorce du précédent conjoint',
        'acte_naissance' => 'Acte de naissance',
        'cnib_parent' => 'Copie CNIB du parent',
        'certificat_scolarite' => 'Certificat de scolarité',
        'certificat_tutelle' => 'Certificat de tutelle',
        'photo_membre' => 'Photo du membre',
        'acte_naissance_enfant' => 'Acte de naissance',
        'acte_mariage_parent' => 'Acte de mariage avec le parent',
        'piece_garde' => 'Pièce justifiant la garde',
        'acte_naissance_assure' => 'Acte de naissance de l\'assuré',
        'cnib_parent_p' => 'Copie CNIB du parent',
        'piece_autre' => 'Pièces justificatives',
    ],

    'fif_piece_type' => 'FIF signée',

    'lot_types' => [
        'initial' => 'Lot initial',
        'complementaire' => 'Complément familial',
    ],

    'complement_generic_type' => 'Pièce complémentaire',
];
