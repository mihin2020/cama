<?php

namespace App\Services;

use App\Models\CmsContactMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicContactService
{
    public const STATUSES = [
        'new' => 'Nouveau',
        'in_progress' => 'En cours',
        'resolved' => 'Traité',
    ];

    /** Statuts accessibles depuis l'état courant (workflow contact). */
    public const NEXT_STATUSES = [
        'new' => ['in_progress', 'resolved'],
        'in_progress' => ['resolved'],
        'resolved' => ['in_progress'],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::NEXT_STATUSES[$from] ?? [], true);
    }

    /**
     * @return array<string, string>
     */
    public static function selectableStatuses(string $current): array
    {
        if ($current === 'new') {
            return array_intersect_key(self::STATUSES, array_flip(self::NEXT_STATUSES['new']));
        }

        $keys = array_values(array_unique(array_merge(
            [$current],
            self::NEXT_STATUSES[$current] ?? [],
        )));

        return array_intersect_key(self::STATUSES, array_flip($keys));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data, Request $request): CmsContactMessage
    {
        $message = CmsContactMessage::query()->create([
            'full_name' => trim((string) ($data['full_name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'subject' => trim((string) ($data['subject'] ?? '')) ?: 'Contact site public',
            'message' => trim((string) ($data['message'] ?? '')),
            'consent' => (bool) ($data['consent'] ?? false),
            'status' => 'new',
            'source_page' => $data['source_page'] ?? 'contact',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->sendNotificationEmail($message);
        app(AdminNotificationService::class)->notifyAllAdmins(
            type: 'contact',
            titre: 'Nouveau message contact',
            contenu: $message->full_name.' — '.Str::limit($message->subject ?: $message->message, 90),
            lien: route('admin.cms.contacts'),
            roles: ['superviseur', 'administrateur'],
        );

        return $message;
    }

    public function pendingCount(): int
    {
        return CmsContactMessage::query()
            ->whereNull('archived_at')
            ->where('status', 'new')
            ->count();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForAdmin(
        ?string $status = null,
        ?string $query = null,
        bool $archived = false,
        int $perPage = 50,
    ): LengthAwarePaginator {
        return CmsContactMessage::query()
            ->with('handler')
            ->when($archived, fn ($builder) => $builder->whereNotNull('archived_at'))
            ->when(! $archived, fn ($builder) => $builder->whereNull('archived_at'))
            ->when($status, fn ($builder) => $builder->where('status', $status))
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('full_name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('subject', 'like', "%{$query}%")
                        ->orWhere('message', 'like', "%{$query}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->through(fn (CmsContactMessage $message) => $this->format($message));
    }

    public function format(CmsContactMessage $message): array
    {
        return [
            'id' => $message->id,
            'fullName' => $message->full_name,
            'email' => $message->email,
            'phone' => $message->phone,
            'subject' => $message->subject,
            'message' => $message->message,
            'messagePreview' => Str::limit($message->message, 180),
            'status' => $message->status,
            'statusLabel' => self::STATUSES[$message->status] ?? $message->status,
            'selectableStatuses' => self::selectableStatuses($message->status),
            'adminNote' => $message->admin_note,
            'sourcePage' => $message->source_page,
            'handler' => $message->handler?->display_name,
            'handledAt' => $message->handled_at?->format('d/m/Y H:i'),
            'archivedAt' => $message->archived_at?->format('d/m/Y H:i'),
            'createdAt' => $message->created_at?->format('d/m/Y H:i') ?? '',
        ];
    }

    public function archiveBefore(string $date): int
    {
        return CmsContactMessage::query()
            ->whereNull('archived_at')
            ->whereDate('created_at', '<=', $date)
            ->update(['archived_at' => now()]);
    }

    public function purgeArchived(): int
    {
        return CmsContactMessage::query()
            ->whereNotNull('archived_at')
            ->delete();
    }

    public function archiveIds(array $ids): int
    {
        return CmsContactMessage::query()
            ->whereIn('id', $ids)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);
    }

    private function sendNotificationEmail(CmsContactMessage $message): void
    {
        $recipients = $this->recipients();
        if (! count($recipients)) {
            return;
        }

        $adminUrl = route('admin.cms.contacts');
        $subject = '[CAMA] Nouveau message de contact';
        $body = implode("\n", [
            'Un nouveau message a été reçu via le formulaire contact du site public.',
            '',
            'Nom et prénom : '.$message->full_name,
            'Téléphone : '.($message->phone ?: '—'),
            'Email : '.($message->email ?: '—'),
            '',
            'Connectez-vous à la plateforme pour lire le message complet et le traiter :',
            $adminUrl,
        ]);

        try {
            Mail::raw($body, function ($mail) use ($recipients, $subject, $message) {
                $mail->to($recipients)
                    ->subject($subject)
                    ->replyTo($message->email ?: config('mail.from.address'));
            });
        } catch (\Throwable $e) {
            Log::warning('Échec envoi email contact CAMA', [
                'error' => $e->getMessage(),
                'message_id' => $message->id,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function storedRecipients(): array
    {
        $settings = app(PlatformSettingsService::class)->get('contact_recipients');
        $configured = is_array($settings) ? $settings : explode(',', (string) $settings);

        return array_values(array_unique(array_filter(array_map(
            fn ($email) => filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) ? trim((string) $email) : null,
            Arr::flatten($configured),
        ))));
    }

    /**
     * @return array<int, string>
     */
    public function recipients(): array
    {
        $configured = $this->storedRecipients();
        $fallback = config('services.cama.contact_recipient');
        if ($fallback) {
            $configured[] = $fallback;
        }

        return array_values(array_unique($configured));
    }
}
