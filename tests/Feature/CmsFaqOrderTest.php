<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\CmsFaq;
use App\Models\CmsFaqCategory;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ordre de la FAQ : flèches monter / descendre, toujours 1, 2, 3… sans doublon (plus de saisie libre du numéro). */
class CmsFaqOrderTest extends TestCase
{
    use RefreshDatabase;

    private CmsFaqCategory $enrolement;

    private CmsFaqCategory $prestations;

    protected function setUp(): void
    {
        parent::setUp();
        CmsFaq::query()->delete();
        $this->enrolement = CmsFaqCategory::query()->firstOrCreate(['name' => 'Enrôlement'], ['sort_order' => 1]);
        $this->prestations = CmsFaqCategory::query()->firstOrCreate(['name' => 'Prestations'], ['sort_order' => 2]);
    }

    private function admin(AdminRole $role = AdminRole::Administrateur): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Web', 'prenom' => 'Master', 'email' => strtolower($role->name).'@cama.bf', 'password' => 'motdepasse-solide',
            'role' => $role, 'permissions' => AdminPermissions::presetForRole($role), 'actif' => true,
        ]);
    }

    private function faq(string $question, int $order, ?CmsFaqCategory $category = null): CmsFaq
    {
        return CmsFaq::query()->create([
            'question' => $question, 'answer' => 'R', 'category_id' => ($category ?? $this->enrolement)->id,
            'sort_order' => $order, 'published' => true,
        ]);
    }

    private function order(): array
    {
        return CmsFaq::query()->orderBy('sort_order')->pluck('sort_order', 'question')->all();
    }

    public function test_an_arrow_swaps_with_the_neighbour_and_removes_duplicate_numbers(): void
    {
        $this->faq('A', 1);
        $b = $this->faq('B', 1);
        $this->faq('C', 2);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.cms.faq.move', $b), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(['B' => 1, 'A' => 2, 'C' => 3], $this->order());
    }

    public function test_the_first_question_cannot_go_higher(): void
    {
        $a = $this->faq('A', 1);
        $this->faq('B', 2);

        $this->actingAs($this->admin(), 'admin')->post(route('admin.cms.faq.move', $a), ['direction' => 'up']);

        $this->assertSame(['A' => 1, 'B' => 2], $this->order());
    }

    public function test_in_a_filtered_list_the_neighbour_is_the_previous_question_of_the_same_category(): void
    {
        $this->faq('A', 1);
        $this->faq('P', 2, $this->prestations);
        $c = $this->faq('C', 3);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.cms.faq.move', $c), ['direction' => 'up', 'category' => $this->enrolement->id]);

        $this->assertSame(['C' => 1, 'P' => 2, 'A' => 3], $this->order());
    }

    public function test_a_new_question_goes_last_and_editing_keeps_its_place(): void
    {
        $a = $this->faq('A', 1);
        $this->faq('B', 2);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.cms.faq.store'), [
            'question' => 'Nouvelle', 'answer' => 'R', 'category_id' => $this->enrolement->id, 'sort_order' => 1, 'published' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin, 'admin')->put(route('admin.cms.faq.update', $a), [
            'question' => 'A modifiée', 'answer' => 'R', 'category_id' => $this->enrolement->id, 'sort_order' => 99, 'published' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['A modifiée' => 1, 'B' => 2, 'Nouvelle' => 3], $this->order());
    }

    public function test_an_account_without_the_faq_permission_cannot_reorder(): void
    {
        $this->faq('A', 1);
        $b = $this->faq('B', 2);

        $this->actingAs($this->admin(AdminRole::Gestionnaire), 'admin')
            ->post(route('admin.cms.faq.move', $b), ['direction' => 'up'])
            ->assertForbidden();

        $this->assertSame(['A' => 1, 'B' => 2], $this->order());
    }
}
