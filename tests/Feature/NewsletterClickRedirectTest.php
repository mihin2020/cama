<?php

namespace Tests\Feature;

use App\Services\CmsNewsletterCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le suivi des clics ne doit jamais devenir une « redirection ouverte » : un abonné quelconque
 * (l'inscription est publique) ne peut pas fabriquer un lien du site vers une adresse de son choix.
 */
class NewsletterClickRedirectTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-suivi-test';

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $campaignId = DB::table('cms_newsletter_campaigns')->insertGetId([
            'name' => 'Test', 'subject' => 'Test', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $subscriberId = DB::table('cms_newsletter_subscribers')->insertGetId([
            'email' => 'abonne@example.bf', 'token' => 'jeton-desinscription', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('cms_newsletter_campaign_sends')->insert([
            'campaign_id' => $campaignId, 'subscriber_id' => $subscriberId, 'tracking_token' => self::TOKEN,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function click(string $url, ?string $signature)
    {
        return $this->get(route('public.newsletter.track.click', array_filter([
            'token' => self::TOKEN, 'u' => base64_encode($url), 's' => $signature,
        ], fn ($v) => $v !== null)));
    }

    private function sign(string $url): string
    {
        return app(CmsNewsletterCampaignService::class)->signTrackedUrl(self::TOKEN, $url);
    }

    private function clicks(): int
    {
        return (int) DB::table('cms_newsletter_campaign_sends')->where('tracking_token', self::TOKEN)->value('click_count');
    }

    public function test_signed_link_redirects_and_counts_the_click(): void
    {
        $url = 'https://www.example.bf/actualites';

        $this->click($url, $this->sign($url))->assertRedirect($url);

        $this->assertSame(1, $this->clicks());
    }

    public function test_unsigned_or_forged_link_goes_home_and_is_not_counted(): void
    {
        $evil = 'https://phishing.example.com/login';

        $this->click($evil, null)->assertRedirect('/');
        $this->click($evil, 'signature-bidon')->assertRedirect('/');
        // Signature valable pour un autre lien : refusée.
        $this->click($evil, $this->sign('https://www.example.bf/'))->assertRedirect('/');

        $this->assertSame(0, $this->clicks());
    }

    public function test_signature_is_bound_to_the_tracking_token(): void
    {
        $url = 'https://www.example.bf/';
        $signatureOfAnotherSend = app(CmsNewsletterCampaignService::class)->signTrackedUrl('autre-jeton', $url);

        $this->click($url, $signatureOfAnotherSend)->assertRedirect('/');
    }

    public function test_dangerous_schemes_and_protocol_relative_urls_are_refused_even_if_signed(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', '//evil.example.com', '/\\evil.example.com'] as $url) {
            $this->click($url, $this->sign($url))->assertRedirect('/');
        }

        $this->assertSame(0, $this->clicks());
    }

    public function test_mailto_and_relative_links_still_work_when_signed(): void
    {
        foreach (['mailto:contact@cama.bf', '/contact'] as $url) {
            $this->click($url, $this->sign($url))->assertRedirect($url);
        }
    }
}
