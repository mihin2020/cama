<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\Assure;
use App\Models\AssureConnexion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AssureLoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    private const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private function makeAssure(): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Histo', 'matricule' => 'MAT-H1', 'numero_cim' => 'CIM-H1',
            'numero_cama' => 'CAMA-H1', 'email' => 'histo@example.bf', 'password' => 'mot-de-passe-solide',
            'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
        $assure->forceFill(['email_verified_at' => now()])->save();

        return $assure;
    }

    private function login(string $userAgent)
    {
        return $this->withHeader('User-Agent', $userAgent)->post(route('assure.login.store'), [
            'email' => 'histo@example.bf', 'password' => 'mot-de-passe-solide',
        ]);
    }

    public function test_logins_are_recorded_and_a_new_device_is_flagged_unusual(): void
    {
        $assure = $this->makeAssure();

        $this->login(self::CHROME_WINDOWS);
        $this->post(route('assure.logout'));
        $this->login(self::CHROME_WINDOWS);
        $this->post(route('assure.logout'));
        $this->login(self::SAFARI_IPHONE);

        $rows = AssureConnexion::query()->where('assure_id', $assure->id)->orderBy('id')->get();

        $this->assertSame(['Chrome / Windows', 'Chrome / Windows', 'Safari / iPhone'], $rows->pluck('appareil')->all());
        // Première connexion et appareil déjà connu : normales ; nouvel appareil : inhabituelle.
        $this->assertSame([false, false, true], $rows->pluck('inhabituelle')->all());
    }

    public function test_profile_shows_real_history_not_placeholder_data(): void
    {
        $assure = $this->makeAssure();
        AssureConnexion::query()->create(['assure_id' => $assure->id, 'ip' => '10.0.0.1', 'appareil' => 'Chrome / Windows']);

        $this->actingAs($assure, 'assure')->get(route('assure.profil'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Assure/Profil/Show')
                ->has('securityLog', 1)
                ->where('securityLog.0.appareil', 'Chrome / Windows')
                ->where('securityLog.0.suspect', false));
    }
}
