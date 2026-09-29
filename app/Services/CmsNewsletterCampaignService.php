<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsNewsletterBatch;
use App\Models\CmsNewsletterCampaign;
use App\Models\CmsNewsletterCampaignSend;
use App\Models\CmsNewsletterSubscriber;
use App\Models\CmsNewsletterTemplate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CmsNewsletterCampaignService
{
    public const SEGMENTS = [
        'visiteur' => 'Visiteur site public',
        'prospect' => 'Prospect',
        'assure' => 'Assuré',
        'partenaire' => 'Partenaire',
        'institution' => 'Institution',
    ];

    public const EMAIL_STATUSES = [
        'active' => 'Actif',
        'unsubscribed' => 'Désinscrit',
        'bounced' => 'Rebond',
        'invalid' => 'Invalide',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCampaign(array $data, ?AdminUser $admin = null): CmsNewsletterCampaign
    {
        $blocks = $data['blocks_json'] ?? [];
        $html = array_key_exists('content_html', $data) && $data['content_html'] !== null
            ? (string) $data['content_html']
            : $this->renderBlocks($blocks);

        return CmsNewsletterCampaign::query()->create([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'preheader' => $data['preheader'] ?? null,
            'blocks_json' => $blocks ?: null,
            'html_body' => $html,
            'segment' => $data['segment'] ?? null,
            'batch_ids' => $data['batch_ids'] ?? null,
            'subscriber_ids' => $data['subscriber_ids'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'status' => 'draft',
            'created_by' => $admin?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCampaign(CmsNewsletterCampaign $campaign, array $data): CmsNewsletterCampaign
    {
        if ($campaign->status === 'sent') {
            abort(422, 'Une campagne envoyée ne peut plus être modifiée.');
        }

        $blocks = $data['blocks_json'] ?? $campaign->blocks_json ?? [];
        $html = array_key_exists('content_html', $data) && $data['content_html'] !== null
            ? (string) $data['content_html']
            : ($campaign->html_body ?? $this->renderBlocks($blocks));

        $campaign->update([
            'name' => $data['name'] ?? $campaign->name,
            'subject' => $data['subject'] ?? $campaign->subject,
            'preheader' => $data['preheader'] ?? $campaign->preheader,
            'blocks_json' => $blocks ?: null,
            'html_body' => $html,
            'segment' => $data['segment'] ?? $campaign->segment,
            'batch_ids' => $data['batch_ids'] ?? $campaign->batch_ids,
            'subscriber_ids' => $data['subscriber_ids'] ?? $campaign->subscriber_ids,
            'template_id' => $data['template_id'] ?? $campaign->template_id,
        ]);

        return $campaign->fresh();
    }

    /**
     * @return array{sent:int, failed:int}
     */
    public function sendCampaign(CmsNewsletterCampaign $campaign, ?string $testEmail = null, int $maxRecipients = 50): array
    {
        if ($testEmail) {
            $html = $this->personalize($campaign->html_body ?? '', [
                'prenom' => 'Test',
                'nom' => 'Utilisateur Test',
                'email' => $testEmail,
                'lien_desinscription' => route('public.newsletter.unsubscribe', 'test-token'),
            ]);

            try {
                Mail::send([], [], function ($message) use ($testEmail, $campaign, $html) {
                    $message->to($testEmail)
                        ->subject('[TEST] '.$campaign->subject)
                        ->html($this->wrapEmail($html, $campaign->preheader));
                });

                return ['sent' => 1, 'failed' => 0];
            } catch (\Throwable $e) {
                Log::warning('Échec envoi test newsletter', ['error' => $e->getMessage()]);

                return ['sent' => 0, 'failed' => 1];
            }
        }

        $subscribers = $this->resolveRecipients(
            segment: $campaign->segment,
            subscriberIds: $campaign->subscriber_ids ?? [],
            batchIds: $campaign->batch_ids ?? [],
            maxRecipients: $maxRecipients,
        );

        $sent = 0;
        $failed = 0;

        foreach ($subscribers as $subscriber) {
            $token = Str::random(48);
            $send = CmsNewsletterCampaignSend::query()->create([
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'tracking_token' => $token,
                'status' => 'pending',
            ]);

            $vars = $this->subscriberVars($subscriber);
            $subject = $this->personalize($campaign->subject, $vars);
            $html = $this->buildTrackedHtml($campaign, $send, $subscriber);

            try {
                Mail::send([], [], function ($message) use ($subscriber, $subject, $html) {
                    $message->to($subscriber->email, $subscriber->full_name)
                        ->subject($subject)
                        ->html($html);
                });

                $send->update(['status' => 'sent', 'sent_at' => now()]);
                $subscriber->update(['last_sent_at' => now()]);
                $sent += 1;
            } catch (\Throwable $e) {
                $send->update(['status' => 'failed']);
                $failed += 1;
                Log::warning('Échec envoi newsletter', [
                    'email' => $subscriber->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => $sent,
            'failed_count' => $failed,
        ]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function recordOpen(string $token): void
    {
        $send = CmsNewsletterCampaignSend::query()->where('tracking_token', $token)->first();
        if (! $send) {
            return;
        }

        $now = now();
        if (! $send->opened_at) {
            $send->update(['opened_at' => $now]);
            CmsNewsletterCampaign::query()->whereKey($send->campaign_id)->increment('open_count');
        }

        $subscriber = $send->subscriber;
        if ($subscriber) {
            $subscriber->increment('open_count');
            $subscriber->update(['last_opened_at' => $now]);
        }
    }

    public function recordClick(string $token, string $url): ?string
    {
        $send = CmsNewsletterCampaignSend::query()->where('tracking_token', $token)->first();
        if (! $send) {
            return null;
        }

        $now = now();
        $send->increment('click_count');
        if (! $send->first_clicked_at) {
            $send->update(['first_clicked_at' => $now]);
            CmsNewsletterCampaign::query()->whereKey($send->campaign_id)->increment('click_count');
        }

        if (! $send->opened_at) {
            $this->recordOpen($token);
        }

        $subscriber = $send->subscriber;
        if ($subscriber) {
            $subscriber->increment('click_count');
            $subscriber->update(['last_clicked_at' => $now]);
        }

        return $url;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function renderBlocks(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'text';
            $content = $block['content'] ?? [];

            $parts[] = match ($type) {
                'heading' => $this->renderHeading($content),
                'text' => $this->renderText($content),
                'image' => $this->renderImage($content),
                'button' => $this->renderButton($content),
                'divider' => '<hr style="border:none;border-top:1px solid #e5bdbb;margin:24px 0;" />',
                'spacer' => '<div style="height:'.((int) ($content['height'] ?? 16)).'px;"></div>',
                default => '',
            };
        }

        return implode("\n", array_filter($parts));
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function renderHeading(array $content): string
    {
        $level = min(3, max(1, (int) ($content['level'] ?? 2)));
        $text = e($content['text'] ?? '');
        $sizes = [1 => '24px', 2 => '20px', 3 => '17px'];

        return "<h{$level} style=\"margin:0 0 12px;font-size:{$sizes[$level]};color:#9e001f;font-family:Georgia,serif;\">{$text}</h{$level}>";
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function renderText(array $content): string
    {
        $html = $content['html'] ?? '<p></p>';

        return '<div style="font-size:15px;line-height:1.6;color:#1b1c1c;margin-bottom:16px;">'.$html.'</div>';
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function renderImage(array $content): string
    {
        $src = e($content['url'] ?? '');
        if ($src === '') {
            return '';
        }
        $alt = e($content['alt'] ?? '');
        $width = (int) ($content['width'] ?? 560);

        return "<p style=\"margin:0 0 16px;text-align:center;\"><img src=\"{$src}\" alt=\"{$alt}\" style=\"max-width:{$width}px;width:100%;height:auto;border-radius:8px;\" /></p>";
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function renderButton(array $content): string
    {
        $label = e($content['label'] ?? 'En savoir plus');
        $url = e($content['url'] ?? '#');

        return "<p style=\"margin:20px 0;text-align:center;\"><a href=\"{$url}\" style=\"display:inline-block;background:#9e001f;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;\">{$label}</a></p>";
    }

    public function wrapEmail(string $bodyHtml, ?string $preheader = null): string
    {
        $preheaderHtml = $preheader
            ? '<div style="display:none;max-height:0;overflow:hidden;">'.e($preheader).'</div>'
            : '';

        $logoUrl = url('/images/logo_cama.png');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f6f3f2;font-family:Arial,Helvetica,sans-serif;">
{$preheaderHtml}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3f2;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5bdbb;">
<tr><td style="background:#9e001f;padding:20px 24px;text-align:center;">
<img src="{$logoUrl}" alt="CAMA" width="48" height="48" style="display:inline-block;" />
<p style="margin:8px 0 0;color:#fff;font-size:13px;font-weight:bold;">Caisse d'Assurance Maladie des Armées</p>
</td></tr>
<tr><td style="padding:28px 24px;">{$bodyHtml}</td></tr>
<tr><td style="background:#f0eded;padding:16px 24px;text-align:center;font-size:11px;color:#5c403f;line-height:1.5;">
<p style="margin:0 0 8px;">CAMA — Ouagadougou, Burkina Faso</p>
<p style="margin:0;">{{lien_desinscription}}</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function personalize(string $html, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $html = str_replace('{{'.$key.'}}', e($value), $html);
        }

        if (isset($vars['lien_desinscription']) && str_contains($html, '{{lien_desinscription}}')) {
            $link = '<a href="'.e($vars['lien_desinscription']).'" style="color:#9e001f;">Se désinscrire</a>';
            $html = str_replace('{{lien_desinscription}}', $link, $html);
        }

        return $html;
    }

    private function buildTrackedHtml(CmsNewsletterCampaign $campaign, CmsNewsletterCampaignSend $send, CmsNewsletterSubscriber $subscriber): string
    {
        $vars = $this->subscriberVars($subscriber);
        $body = $this->personalize($campaign->html_body ?? '', $vars);
        $body = $this->rewriteLinksForTracking($body, $send->tracking_token);
        $html = $this->wrapEmail($body, $campaign->preheader);
        $html = $this->personalize($html, $vars);
        $pixel = '<img src="'.route('public.newsletter.track.open', $send->tracking_token).'" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;" />';

        return $html.$pixel;
    }

    /**
     * @return array<string, string>
     */
    public function subscriberVars(CmsNewsletterSubscriber $subscriber): array
    {
        $fullName = trim((string) $subscriber->full_name);
        $parts = preg_split('/\s+/', $fullName) ?: [];
        $prenom = $parts[0] ?? 'Abonné';

        return [
            'prenom' => $prenom,
            'nom' => $fullName ?: $prenom,
            'email' => $subscriber->email,
            'lien_desinscription' => route('public.newsletter.unsubscribe', $subscriber->token),
        ];
    }

    private function rewriteLinksForTracking(string $html, string $trackingToken): string
    {
        return preg_replace_callback(
            '/href="([^"]+)"/i',
            function ($matches) use ($trackingToken) {
                $url = $matches[1];
                if (str_starts_with($url, '#') || str_starts_with($url, '{{')) {
                    return $matches[0];
                }
                $tracked = route('public.newsletter.track.click', [
                    'token' => $trackingToken,
                    'u' => base64_encode($url),
                ]);

                return 'href="'.e($tracked).'"';
            },
            $html,
        ) ?? $html;
    }

    /**
     * @param  array<int, int>  $subscriberIds
     * @param  array<int, int>  $batchIds
     * @return array<int, CmsNewsletterSubscriber>
     */
    public function resolveRecipients(?string $segment, array $subscriberIds, array $batchIds, int $maxRecipients): array
    {
        $query = CmsNewsletterSubscriber::query()
            ->where('email_status', 'active')
            ->whereNull('unsubscribed_at');

        if ($segment) {
            $query->where('segment', $segment);
        }

        if ($subscriberIds || $batchIds) {
            $query->where(function ($builder) use ($subscriberIds, $batchIds) {
                if ($subscriberIds) {
                    $builder->whereIn('id', $subscriberIds);
                }
                if ($batchIds) {
                    $builder->orWhereHas('batches', fn ($inner) => $inner->whereIn('cms_newsletter_batches.id', $batchIds));
                }
            });
        }

        return $query->orderBy('id')->limit(max(1, $maxRecipients))->get()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCampaigns(): array
    {
        return CmsNewsletterCampaign::query()
            ->with('creator')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (CmsNewsletterCampaign $campaign) => $this->formatCampaign($campaign))
            ->all();
    }

    public function formatCampaign(CmsNewsletterCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'preheader' => $campaign->preheader,
            'blocksJson' => $campaign->blocks_json ?? [],
            'htmlBody' => $campaign->html_body,
            'segment' => $campaign->segment,
            'batchIds' => $campaign->batch_ids ?? [],
            'subscriberIds' => $campaign->subscriber_ids ?? [],
            'status' => $campaign->status,
            'statusLabel' => $campaign->status === 'sent' ? 'Envoyée' : 'Brouillon',
            'sentCount' => $campaign->sent_count,
            'failedCount' => $campaign->failed_count,
            'openCount' => $campaign->open_count,
            'clickCount' => $campaign->click_count,
            'unsubscribeCount' => $campaign->unsubscribe_count,
            'openRate' => $campaign->openRate(),
            'clickRate' => $campaign->clickRate(),
            'sentAt' => $campaign->sent_at?->format('d/m/Y H:i'),
            'createdAt' => $campaign->created_at?->format('d/m/Y H:i') ?? '',
            'creator' => $campaign->creator?->display_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function globalStats(): array
    {
        $active = CmsNewsletterSubscriber::query()
            ->where('email_status', 'active')
            ->whereNull('unsubscribed_at')
            ->count();

        $campaigns = CmsNewsletterCampaign::query()->where('status', 'sent')->get();

        return [
            'activeSubscribers' => $active,
            'totalCampaigns' => $campaigns->count(),
            'totalSent' => $campaigns->sum('sent_count'),
            'avgOpenRate' => $campaigns->count()
                ? round($campaigns->avg(fn ($c) => $c->openRate()), 1)
                : 0,
            'avgClickRate' => $campaigns->count()
                ? round($campaigns->avg(fn ($c) => $c->clickRate()), 1)
                : 0,
            'totalUnsubscribes' => CmsNewsletterSubscriber::query()->whereNotNull('unsubscribed_at')->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        return CmsNewsletterTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsNewsletterTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'blocksJson' => $template->blocks_json ?? [],
                'html' => $this->renderBlocks($template->blocks_json ?? []),
                'isSystem' => $template->is_system,
            ])
            ->all();
    }

    public function cleanInactive(int $months = 12): int
    {
        $threshold = now()->subMonths($months);

        return CmsNewsletterSubscriber::query()
            ->where('email_status', 'active')
            ->whereNull('unsubscribed_at')
            ->where(function ($q) use ($threshold) {
                $q->whereNull('last_opened_at')
                    ->where('subscribed_at', '<', $threshold)
                    ->orWhere('last_opened_at', '<', $threshold);
            })
            ->update(['email_status' => 'invalid']);
    }

    public function cleanInvalidEmails(): int
    {
        $count = 0;
        CmsNewsletterSubscriber::query()
            ->where('email_status', 'active')
            ->chunkById(200, function ($subscribers) use (&$count) {
                foreach ($subscribers as $subscriber) {
                    if (! filter_var($subscriber->email, FILTER_VALIDATE_EMAIL)) {
                        $subscriber->update(['email_status' => 'invalid']);
                        $count += 1;
                    }
                }
            });

        return $count;
    }
}
