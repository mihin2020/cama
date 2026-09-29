<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    protected $signature = 'cama:test-mail {email? : Destinataire du test}';

    protected $description = 'Envoie un e-mail de test via la configuration SMTP active';

    public function handle(): int
    {
        $to = $this->argument('email') ?? config('mail.from.address');

        $this->info("Mailer : ".config('mail.default'));
        $this->info("Envoi vers : {$to}");

        try {
            Mail::raw('Test d\'envoi CAMA — configuration SMTP opérationnelle.', function ($message) use ($to) {
                $message->to($to)->subject('Test CAMA SMTP');
            });
        } catch (\Throwable $e) {
            $this->error('Échec : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('E-mail envoyé avec succès.');

        return self::SUCCESS;
    }
}
