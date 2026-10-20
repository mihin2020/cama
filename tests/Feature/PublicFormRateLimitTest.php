<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function contact(string $email, string $ip = '10.0.0.1')
    {
        return $this->from(route('public.contact'))
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('public.contact.submit'), [
                'full_name' => 'Visiteur',
                'email' => $email,
                'message' => 'Bonjour',
                'consent' => '1',
            ]);
    }

    public function test_people_sharing_the_same_network_are_not_blocked(): void
    {
        // Dix personnes différentes depuis la même connexion (même IP) : aucune n'est bloquée.
        foreach (range(1, 10) as $n) {
            $this->contact("personne{$n}@example.bf")->assertSessionDoesntHaveErrors('email');
        }
    }

    public function test_same_address_is_limited_per_10_minutes(): void
    {
        foreach (range(1, 5) as $n) {
            $this->contact('meme@example.bf')->assertSessionDoesntHaveErrors('email');
        }

        $this->contact('meme@example.bf')
            ->assertRedirect(route('public.contact'))
            ->assertSessionHasErrors(['email' => 'Trop de demandes. Réessayez dans quelques minutes.']);

        // Une autre personne du même réseau peut toujours écrire.
        $this->contact('autre@example.bf')->assertSessionDoesntHaveErrors('email');
    }

    public function test_mass_sending_from_one_ip_is_stopped(): void
    {
        foreach (range(1, 20) as $n) {
            $this->contact("robot{$n}@example.bf", '10.0.0.9');
        }

        $this->contact('robot21@example.bf', '10.0.0.9')->assertSessionHasErrors('email');
    }
}
