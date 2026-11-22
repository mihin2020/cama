<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les adresses e-mail sont insensibles à la casse sur toutes les bases (MySQL, PostgreSQL, SQLite). */
class EmailCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_emails_are_stored_in_lowercase(): void
    {
        $admin = AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => '  Agent.Test@CAMA.bf ', 'password' => 'motdepasse-solide',
            'role' => 'gestionnaire', 'actif' => true,
        ]);

        $this->assertSame('agent.test@cama.bf', $admin->fresh()->email);
    }

    public function test_login_works_whatever_the_case_typed(): void
    {
        AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => 'agent@cama.bf', 'password' => 'motdepasse-solide',
            'role' => 'gestionnaire', 'actif' => true,
        ]);

        $this->post(route('admin.login.store'), ['email' => 'AGENT@Cama.BF ', 'password' => 'motdepasse-solide'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated('admin');
    }
}
