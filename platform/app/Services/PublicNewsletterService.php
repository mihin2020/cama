<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsNewsletterBatch;
use App\Models\CmsNewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicNewsletterService
{
    public function subscribe(string $email, ?string $name, Request $request): CmsNewsletterSubscriber
    {
        $subscriber = CmsNewsletterSubscriber::query()->firstOrNew([
            'email' => strtolower(trim($email)),
        ]);

        if (! $subscriber->exists) {
            $subscriber->token = Str::random(48);
        }

        $subscriber->full_name = $name ? trim($name) : $subscriber->full_name;
        $subscriber->segment = $subscriber->segment ?: 'visiteur';
        $subscriber->email_status = 'active';
        $subscriber->source = 'public_site';
        $subscriber->ip_address = $request->ip();
        $subscriber->user_agent = $request->userAgent();
        $subscriber->subscribed_at = now();
        $subscriber->unsubscribed_at = null;
        $subscriber->save();

        app(AdminNotificationService::class)->notifyAllAdmins(
            type: 'newsletter',
            titre: 'Nouvel abonnement newsletter',
            contenu: $subscriber->email.' vient de s’abonner.',
            lien: route('admin.cms.newsletter'),
            roles: ['superviseur', 'administrateur'],
        );

        return $subscriber;
    }

    public function unsubscribeByToken(string $token): bool
    {
        $subscriber = CmsNewsletterSubscriber::query()
            ->where('token', $token)
            ->first();

        if (! $subscriber) {
            return false;
        }

        $subscriber->unsubscribed_at = now();
        $subscriber->email_status = 'unsubscribed';
        $subscriber->save();

        return true;
    }

    /**
     * @param  array<int, int>  $subscriberIds
     * @param  array<int, int>  $batchIds
     * @return array{sent:int, failed:int}
     */
    public function sendCampaignNow(
        string $subject,
        string $body,
        ?string $testEmail = null,
        int $maxRecipients = 50,
        array $subscriberIds = [],
        array $batchIds = [],
    ): array {
        if ($testEmail) {
            try {
                Mail::raw($body, fn ($mail) => $mail->to($testEmail)->subject($subject));

                return ['sent' => 1, 'failed' => 0];
            } catch (\Throwable $e) {
                Log::warning('Échec envoi newsletter test', ['error' => $e->getMessage()]);

                return ['sent' => 0, 'failed' => 1];
            }
        }

        $recipients = $this->resolveCampaignRecipients($subscriberIds, $batchIds, $maxRecipients);
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $subscriber) {
            try {
                Mail::raw(
                    $body."\n\nSe désinscrire: ".route('public.newsletter.unsubscribe', $subscriber->token),
                    fn ($mail) => $mail->to($subscriber->email)->subject($subject),
                );
                $sent += 1;
            } catch (\Throwable $e) {
                $failed += 1;
                Log::warning('Échec envoi newsletter', [
                    'email' => $subscriber->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @param  array<int, int>  $subscriberIds
     * @param  array<int, int>  $batchIds
     * @return array<int, CmsNewsletterSubscriber>
     */
    private function resolveCampaignRecipients(array $subscriberIds, array $batchIds, int $maxRecipients): array
    {
        $query = CmsNewsletterSubscriber::query()->whereNull('unsubscribed_at');

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

        return $query
            ->orderBy('id')
            ->limit(max(1, $maxRecipients))
            ->get()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(?string $status = null, ?string $query = null, ?string $segment = null): array
    {
        $batchMap = $this->subscriberBatchMap();
        $segments = CmsNewsletterCampaignService::SEGMENTS;

        return CmsNewsletterSubscriber::query()
            ->when($status === 'active', fn ($builder) => $builder->where('email_status', 'active')->whereNull('unsubscribed_at'))
            ->when($status === 'unsubscribed', fn ($builder) => $builder->where('email_status', 'unsubscribed'))
            ->when($status === 'invalid', fn ($builder) => $builder->where('email_status', 'invalid'))
            ->when($status === 'bounced', fn ($builder) => $builder->where('email_status', 'bounced'))
            ->when($segment, fn ($builder) => $builder->where('segment', $segment))
            ->when($query, fn ($builder) => $builder->where(function ($inner) use ($query) {
                $inner->where('email', 'like', "%{$query}%")
                    ->orWhere('full_name', 'like', "%{$query}%");
            }))
            ->orderByDesc('subscribed_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (CmsNewsletterSubscriber $subscriber) => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'fullName' => $subscriber->full_name,
                'segment' => $subscriber->segment,
                'segmentLabel' => $segments[$subscriber->segment] ?? $subscriber->segment,
                'emailStatus' => $subscriber->email_status,
                'emailStatusLabel' => CmsNewsletterCampaignService::EMAIL_STATUSES[$subscriber->email_status] ?? $subscriber->email_status,
                'status' => $subscriber->unsubscribed_at ? 'unsubscribed' : 'active',
                'statusLabel' => $subscriber->unsubscribed_at ? 'Désinscrit' : 'Actif',
                'openCount' => $subscriber->open_count,
                'clickCount' => $subscriber->click_count,
                'lastOpenedAt' => $subscriber->last_opened_at?->format('d/m/Y H:i') ?? '',
                'lastSentAt' => $subscriber->last_sent_at?->format('d/m/Y H:i') ?? '',
                'subscribedAt' => $subscriber->subscribed_at?->format('d/m/Y H:i') ?? '',
                'unsubscribedAt' => $subscriber->unsubscribed_at?->format('d/m/Y H:i') ?? '',
                'token' => $subscriber->token,
                'batchIds' => collect($batchMap[$subscriber->id] ?? [])->pluck('id')->all(),
                'batchNames' => collect($batchMap[$subscriber->id] ?? [])->pluck('name')->all(),
            ])
            ->all();
    }

    public function updateSubscriber(CmsNewsletterSubscriber $subscriber, array $data): CmsNewsletterSubscriber
    {
        $subscriber->update([
            'full_name' => $data['full_name'] ?? $subscriber->full_name,
            'segment' => $data['segment'] ?? $subscriber->segment,
            'email_status' => $data['email_status'] ?? $subscriber->email_status,
        ]);

        return $subscriber->fresh();
    }

    /**
     * @return array<int, array<int, array{id:int,name:string}>>
     */
    private function subscriberBatchMap(): array
    {
        $rows = DB::table('cms_newsletter_batch_subscriber')
            ->join('cms_newsletter_batches', 'cms_newsletter_batches.id', '=', 'cms_newsletter_batch_subscriber.batch_id')
            ->select('cms_newsletter_batch_subscriber.subscriber_id', 'cms_newsletter_batches.id', 'cms_newsletter_batches.name')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->subscriber_id][] = ['id' => (int) $row->id, 'name' => $row->name];
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBatches(): array
    {
        return CmsNewsletterBatch::query()
            ->withCount('subscribers')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CmsNewsletterBatch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'description' => $batch->description,
                'count' => $batch->subscribers_count,
                'createdAt' => $batch->created_at?->format('d/m/Y H:i') ?? '',
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $subscriberIds
     */
    public function createBatch(string $name, ?string $description, array $subscriberIds, ?AdminUser $admin = null): CmsNewsletterBatch
    {
        $batch = CmsNewsletterBatch::query()->create([
            'name' => trim($name),
            'description' => $description ? trim($description) : null,
            'created_by' => $admin?->id,
        ]);

        if ($subscriberIds) {
            $batch->subscribers()->sync($subscriberIds);
        }

        return $batch->fresh()->loadCount('subscribers');
    }

    public function deleteBatch(CmsNewsletterBatch $batch): void
    {
        $batch->delete();
    }
}
