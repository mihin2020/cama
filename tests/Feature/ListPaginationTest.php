<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminNotification;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPaginationTest extends TestCase
{
    use RefreshDatabase;

    /** Liste => tailles de page attendues (selon le type de liste). */
    private const LISTS = [
        'admin.assures' => [20, 50, 100],
        'admin.inscriptions' => [20, 50, 100],
        'admin.utilisateurs' => [20, 50, 100],
        'admin.notifications' => [15, 30, 60],
        'admin.cms.pages' => [20, 50, 100],
        'admin.cms.actualites' => [20, 50, 100],
        'admin.cms.faq' => [20, 50, 100],
        'admin.cms.ressources' => [20, 50, 100],
        'admin.cms.media' => [24, 48, 96],
        'admin.cms.contacts' => [20, 50, 100],
        'admin.cms.newsletter' => [20, 50, 100],
    ];

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Admin', 'prenom' => 'Listes', 'email' => 'listes@cama.bf',
            'password' => 'motdepasse-solide', 'role' => AdminRole::Administrateur, 'actif' => true,
        ]);
    }

    public function test_every_list_is_paginated_with_sizes_suited_to_its_type(): void
    {
        $admin = $this->admin();

        foreach (self::LISTS as $routeName => $sizes) {
            $pagination = $this->actingAs($admin, 'admin')->get(route($routeName))
                ->assertOk()
                ->viewData('page')['props']['pagination'] ?? null;

            $this->assertIsArray($pagination, "{$routeName} : pas de pagination.");
            $this->assertSame($sizes, $pagination['perPageOptions'], "{$routeName} : tailles de page inattendues.");
            $this->assertSame($sizes[0], $pagination['perPage'], "{$routeName} : taille par défaut inattendue.");

            // Taille proposée acceptée, taille fantaisiste ramenée à la valeur par défaut.
            $this->assertSame($sizes[1], $this->actingAs($admin, 'admin')->get(route($routeName, ['per_page' => $sizes[1]]))
                ->viewData('page')['props']['pagination']['perPage'], "{$routeName} : taille {$sizes[1]} refusée.");
            $this->assertSame($sizes[0], $this->actingAs($admin, 'admin')->get(route($routeName, ['per_page' => 5000]))
                ->viewData('page')['props']['pagination']['perPage'], "{$routeName} : taille 5000 acceptée.");
        }
    }

    public function test_notifications_are_split_into_pages(): void
    {
        $admin = $this->admin();
        foreach (range(1, 20) as $n) {
            AdminNotification::query()->create([
                'admin_user_id' => $admin->id, 'type' => 'soumission', 'titre' => "Notification {$n}", 'contenu' => 'Texte',
            ]);
        }

        $props = $this->actingAs($admin, 'admin')->get(route('admin.notifications', ['page' => 2]))->viewData('page')['props'];

        $this->assertCount(5, $props['notifications']);
        $this->assertSame(2, $props['pagination']['lastPage']);
        $this->assertSame(20, $props['pagination']['total']);

        $this->actingAs($admin, 'admin')->get(route('admin.notifications', ['page' => 9]))
            ->assertRedirect(route('admin.notifications', ['page' => 2]));
    }
}
