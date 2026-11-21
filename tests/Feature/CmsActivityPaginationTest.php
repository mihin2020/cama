<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\CmsActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CmsActivityPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Web', 'prenom' => 'Master', 'email' => 'web@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur, 'actif' => true,
        ]);
    }

    public function test_studio_activity_is_paginated_newest_first(): void
    {
        foreach (range(1, 40) as $n) {
            CmsActivityLog::query()->create(['icon' => 'edit', 'text' => "Action {$n}", 'author_name' => 'CAMA']);
        }
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get(route('admin.cms.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activity', 15)
                ->where('activity.0.text', 'Action 40')
                ->where('pagination.total', 40)
                ->where('pagination.lastPage', 3)
                ->where('pagination.perPageOptions', [15, 30, 60]));

        $this->actingAs($admin, 'admin')->get(route('admin.cms.dashboard', ['page' => 3]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('activity', 10)->where('activity.9.text', 'Action 1'));

        $this->actingAs($admin, 'admin')->get(route('admin.cms.dashboard', ['per_page' => 30]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('activity', 30)->where('pagination.perPage', 30));

        // Taille non proposée : retour à la taille par défaut. Page inexistante : dernière page.
        $this->actingAs($admin, 'admin')->get(route('admin.cms.dashboard', ['per_page' => 9999]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('activity', 15));
        $this->actingAs($admin, 'admin')->get(route('admin.cms.dashboard', ['page' => 9]))
            ->assertRedirect(route('admin.cms.dashboard', ['page' => 3]));
    }
}
