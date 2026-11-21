<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Mail\AssurePlatformNotificationMail;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Support\MailAfterResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailAfterResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_is_sent_after_the_response_not_during_it(): void
    {
        config(['mail.default' => 'smtp']);
        \Illuminate\Support\Facades\Cache::forget('envoi-test');

        // Témoin partagé : la fonction d'envoi est sérialisée avant d'être exécutée en fin de requête.
        MailAfterResponse::send('test', fn () => \Illuminate\Support\Facades\Cache::put('envoi-test', true));

        // Rien n'est parti pendant la requête : la page n'attend pas le serveur SMTP…
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('envoi-test'));

        // … l'envoi a lieu une fois la réponse terminée.
        app()->terminate();
        $this->assertTrue(\Illuminate\Support\Facades\Cache::get('envoi-test'));
    }

    public function test_a_failed_send_never_breaks_the_page_and_calls_the_failure_handler(): void
    {
        config(['mail.default' => 'log']);
        $reported = null;

        MailAfterResponse::send('test', fn () => throw new \RuntimeException('SMTP indisponible'), function ($e) use (&$reported) {
            $reported = $e->getMessage();
        });

        $this->assertSame('SMTP indisponible', $reported);
    }

    public function test_validating_an_account_now_emails_the_insured(): void
    {
        Mail::fake();
        $admin = AdminUser::query()->create([
            'nom' => 'Admin', 'prenom' => 'Test', 'email' => 'valid@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur, 'actif' => true,
        ]);
        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Valide', 'matricule' => 'MAT-V1', 'numero_cim' => 'CIM-V1', 'numero_cama' => 'CAMA-V1',
            'email' => 'valide@example.bf', 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::EnAttenteValidation, 'journal' => [],
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.inscriptions.validate', $assure))->assertSessionHasNoErrors();

        // Bug corrigé : l'e-mail « compte validé » ne partait jamais (return placé avant l'envoi).
        Mail::assertSent(AssurePlatformNotificationMail::class, fn ($mail) => $mail->hasTo('valide@example.bf')
            && str_contains($mail->render(), 'validé'));
    }

    public function test_response_length_is_announced_so_the_browser_does_not_wait_for_the_mail(): void
    {
        $response = $this->get(route('admin.login'));

        $this->assertSame((string) strlen($response->getContent()), $response->headers->get('Content-Length'));
    }
}
