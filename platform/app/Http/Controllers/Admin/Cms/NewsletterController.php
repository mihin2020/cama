<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsNewsletterBatch;
use App\Models\CmsNewsletterCampaign;
use App\Models\CmsNewsletterSubscriber;
use App\Services\CmsNewsletterCampaignService;
use App\Services\PublicNewsletterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterController extends Controller
{
    public function index(Request $request, PublicNewsletterService $newsletter, CmsNewsletterCampaignService $campaigns): Response
    {
        $tab = $request->string('tab')->toString() ?: 'overview';
        $status = $request->string('status')->toString();
        $segment = $request->string('segment')->toString();
        $query = $request->string('q')->toString();

        return Inertia::render('Admin/Cms/Newsletter/Index', [
            'tab' => $tab,
            'items' => $newsletter->listForAdmin($status ?: null, $query ?: null, $segment ?: null),
            'batches' => $newsletter->listBatches(),
            'campaigns' => $campaigns->listCampaigns(),
            'templates' => $campaigns->listTemplates(),
            'stats' => $campaigns->globalStats(),
            'segments' => CmsNewsletterCampaignService::SEGMENTS,
            'emailStatuses' => CmsNewsletterCampaignService::EMAIL_STATUSES,
            'filters' => ['status' => $status, 'segment' => $segment, 'q' => $query],
        ]);
    }

    public function createCampaign(Request $request, CmsNewsletterCampaignService $campaigns): Response
    {
        $preselected = null;
        if ($request->filled('template')) {
            $preselected = collect($campaigns->listTemplates())
                ->firstWhere('id', (int) $request->input('template'));
        }

        return Inertia::render('Admin/Cms/Newsletter/CampaignEdit', [
            'campaign' => null,
            'templates' => $campaigns->listTemplates(),
            'preselectedTemplateId' => $preselected['id'] ?? null,
            'segments' => CmsNewsletterCampaignService::SEGMENTS,
            'batches' => app(PublicNewsletterService::class)->listBatches(),
        ]);
    }

    public function editCampaign(CmsNewsletterCampaign $campaign, CmsNewsletterCampaignService $campaigns): Response
    {
        return Inertia::render('Admin/Cms/Newsletter/CampaignEdit', [
            'campaign' => $campaigns->formatCampaign($campaign),
            'templates' => $campaigns->listTemplates(),
            'segments' => CmsNewsletterCampaignService::SEGMENTS,
            'batches' => app(PublicNewsletterService::class)->listBatches(),
        ]);
    }

    public function storeCampaign(Request $request, CmsNewsletterCampaignService $campaigns): RedirectResponse
    {
        $data = $this->validatedCampaign($request);
        $campaign = $campaigns->createCampaign($data, $request->user('admin'));

        return redirect()->route('admin.cms.newsletter.campaigns.edit', $campaign)
            ->with('success', 'Campagne créée.');
    }

    public function updateCampaign(Request $request, CmsNewsletterCampaign $campaign, CmsNewsletterCampaignService $campaigns): RedirectResponse
    {
        $data = $this->validatedCampaign($request);
        $campaigns->updateCampaign($campaign, $data);

        return back()->with('success', 'Campagne enregistrée.');
    }

    public function sendCampaign(Request $request, CmsNewsletterCampaign $campaign, CmsNewsletterCampaignService $campaigns): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['nullable', 'email', 'max:190'],
            'max_recipients' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        $result = $campaigns->sendCampaign(
            campaign: $campaign,
            testEmail: $data['test_email'] ?? null,
            maxRecipients: (int) ($data['max_recipients'] ?? 50),
        );

        return redirect()->route('admin.cms.newsletter', ['tab' => 'campaigns'])
            ->with('success', "Campagne envoyée. {$result['sent']} email(s), {$result['failed']} échec(s).");
    }

    public function destroyCampaign(CmsNewsletterCampaign $campaign): RedirectResponse
    {
        if ($campaign->status === 'sent') {
            return back()->with('error', 'Impossible de supprimer une campagne déjà envoyée.');
        }

        $campaign->delete();

        return back()->with('success', 'Campagne supprimée.');
    }

    public function toggle(CmsNewsletterSubscriber $subscriber)
    {
        $reactivate = $subscriber->unsubscribed_at !== null;
        $subscriber->update([
            'unsubscribed_at' => $reactivate ? null : now(),
            'email_status' => $reactivate ? 'active' : 'unsubscribed',
        ]);

        return back()->with('success', 'Statut newsletter mis à jour.');
    }

    public function updateSubscriber(Request $request, CmsNewsletterSubscriber $subscriber, PublicNewsletterService $newsletter): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:160'],
            'segment' => ['required', 'string', 'in:'.implode(',', array_keys(CmsNewsletterCampaignService::SEGMENTS))],
            'email_status' => ['required', 'string', 'in:'.implode(',', array_keys(CmsNewsletterCampaignService::EMAIL_STATUSES))],
        ]);

        $newsletter->updateSubscriber($subscriber, $data);

        return back()->with('success', 'Abonné mis à jour.');
    }

    public function cleanList(Request $request, CmsNewsletterCampaignService $campaigns): RedirectResponse
    {
        $type = $request->string('type')->toString();
        $count = match ($type) {
            'invalid' => $campaigns->cleanInvalidEmails(),
            'inactive' => $campaigns->cleanInactive((int) $request->input('months', 12)),
            default => 0,
        };

        return back()->with('success', "{$count} abonné(s) marqué(s).");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $status = $request->string('status')->toString();
        $subscribers = CmsNewsletterSubscriber::query()
            ->when($status === 'active', fn ($builder) => $builder->where('email_status', 'active'))
            ->when($status === 'unsubscribed', fn ($builder) => $builder->where('email_status', 'unsubscribed'))
            ->orderByDesc('subscribed_at')
            ->get();

        $filename = 'newsletter_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($subscribers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'nom', 'segment', 'statut', 'ouvertures', 'clics', 'abonne_le', 'desabonne_le']);
            foreach ($subscribers as $subscriber) {
                fputcsv($out, [
                    $subscriber->email,
                    $subscriber->full_name,
                    $subscriber->segment,
                    $subscriber->email_status,
                    $subscriber->open_count,
                    $subscriber->click_count,
                    $subscriber->subscribed_at?->format('Y-m-d H:i:s'),
                    $subscriber->unsubscribed_at?->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function storeBatch(Request $request, PublicNewsletterService $newsletter): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'subscriber_ids' => ['required', 'array', 'min:1'],
            'subscriber_ids.*' => ['integer'],
        ]);

        $newsletter->createBatch(
            name: $data['name'],
            description: $data['description'] ?? null,
            subscriberIds: array_map('intval', $data['subscriber_ids']),
            admin: $request->user('admin'),
        );

        return back()->with('success', 'Lot d\'abonnés créé.');
    }

    public function destroyBatch(CmsNewsletterBatch $batch, PublicNewsletterService $newsletter): RedirectResponse
    {
        $newsletter->deleteBatch($batch);

        return back()->with('success', 'Lot supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCampaign(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:190'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'content_html' => ['nullable', 'string'],
            'blocks_json' => ['nullable', 'array'],
            'segment' => ['nullable', 'string', 'in:'.implode(',', array_keys(CmsNewsletterCampaignService::SEGMENTS))],
            'batch_ids' => ['nullable', 'array'],
            'batch_ids.*' => ['integer'],
            'subscriber_ids' => ['nullable', 'array'],
            'subscriber_ids.*' => ['integer'],
            'template_id' => ['nullable', 'integer', 'exists:cms_newsletter_templates,id'],
        ]);

        return $data;
    }
}
