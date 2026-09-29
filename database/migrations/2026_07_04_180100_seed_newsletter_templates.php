<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'name' => 'CAMA — Annonce institutionnelle',
                'slug' => 'cama-announcement',
                'description' => 'Titre fort, texte et bouton d\'action — idéal pour communiqués et événements.',
                'blocks_json' => json_encode([
                    ['id' => 'b1', 'type' => 'heading', 'content' => ['text' => 'Actualité CAMA', 'level' => 1]],
                    ['id' => 'b2', 'type' => 'text', 'content' => ['html' => '<p>Bonjour {{prenom}},</p><p>La CAMA vous informe d\'une nouvelle actualité importante pour les assurés et ayants droit.</p>']],
                    ['id' => 'b3', 'type' => 'button', 'content' => ['label' => 'En savoir plus', 'url' => 'https://cama.bf/actualites']],
                    ['id' => 'b4', 'type' => 'divider', 'content' => []],
                    ['id' => 'b5', 'type' => 'text', 'content' => ['html' => '<p style="font-size:12px;color:#5c403f;">Caisse d\'Assurance Maladie des Armées — Ouagadougou, Burkina Faso</p>']],
                ]),
                'is_system' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'CAMA — Info pratique',
                'slug' => 'cama-info',
                'description' => 'Format sobre pour rappels, procédures et informations assurés.',
                'blocks_json' => json_encode([
                    ['id' => 'b1', 'type' => 'heading', 'content' => ['text' => 'Information assurés', 'level' => 2]],
                    ['id' => 'b2', 'type' => 'text', 'content' => ['html' => '<p>Bonjour {{nom}},</p><p>Retrouvez ci-dessous les informations utiles pour vos démarches CAMA.</p><ul><li>Guichet : Lun–Ven 07h30–16h00</li><li>Portail assuré : disponible 24h/24</li></ul>']],
                    ['id' => 'b3', 'type' => 'button', 'content' => ['label' => 'Accéder au portail', 'url' => '/assure/login']],
                ]),
                'is_system' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'CAMA — Minimal',
                'slug' => 'cama-minimal',
                'description' => 'Message court et direct.',
                'blocks_json' => json_encode([
                    ['id' => 'b1', 'type' => 'text', 'content' => ['html' => '<p>Bonjour {{prenom}},</p><p>Votre message personnalisé ici.</p><p>Cordialement,<br>L\'équipe CAMA</p>']],
                ]),
                'is_system' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($templates as $template) {
            DB::table('cms_newsletter_templates')->updateOrInsert(
                ['slug' => $template['slug']],
                $template,
            );
        }
    }

    public function down(): void
    {
        DB::table('cms_newsletter_templates')->whereIn('slug', [
            'cama-announcement', 'cama-info', 'cama-minimal',
        ])->delete();
    }
};
