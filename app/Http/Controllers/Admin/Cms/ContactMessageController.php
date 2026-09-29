<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsContactMessage;
use App\Services\PlatformSettingsService;
use App\Services\PublicContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function index(Request $request, PublicContactService $contacts): Response
    {
        $status = $request->string('status')->toString();
        $query = $request->string('q')->toString();
        $archived = $request->boolean('archived');
        $perPage = min(100, max(10, (int) $request->input('per_page', 50)));

        $paginator = $contacts->paginateForAdmin(
            status: $status ?: null,
            query: $query ?: null,
            archived: $archived,
            perPage: $perPage,
        );

        return Inertia::render('Admin/Cms/Contacts/Index', [
            'items' => $paginator->items(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'status' => $status,
                'q' => $query,
                'archived' => $archived,
                'per_page' => $perPage,
            ],
            'statusOptions' => PublicContactService::STATUSES,
            'recipients' => $contacts->storedRecipients(),
            'fallbackRecipient' => config('services.cama.contact_recipient'),
            'newCount' => $contacts->pendingCount(),
        ]);
    }

    public function update(Request $request, CmsContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,in_progress,resolved'],
            'admin_note' => ['nullable', 'string', 'max:4000'],
        ]);

        if (! PublicContactService::canTransition($message->status, $data['status'])) {
            throw ValidationException::withMessages([
                'status' => 'Transition de statut non autorisée pour ce message.',
            ]);
        }

        $message->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'handled_by' => $request->user('admin')?->id,
            'handled_at' => $data['status'] === 'resolved' ? now() : null,
        ]);

        return back()->with('success', 'Message contact mis à jour.');
    }

    public function updateRecipients(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'recipients' => ['nullable'],
        ]);

        $raw = $data['recipients'] ?? [];
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        $emails = collect(is_array($raw) ? $raw : [])
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        $settings->updateContactRecipients($emails);

        return back()->with('success', count($emails).' destinataire(s) enregistré(s).');
    }

    public function archive(Request $request, PublicContactService $contacts): RedirectResponse
    {
        $data = $request->validate([
            'before_date' => ['nullable', 'date'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = 0;
        if (! empty($data['ids'])) {
            $count = $contacts->archiveIds($data['ids']);
        } elseif (! empty($data['before_date'])) {
            $count = $contacts->archiveBefore($data['before_date']);
        }

        return back()->with('success', "{$count} message(s) archivé(s).");
    }

    public function purgeArchived(): RedirectResponse
    {
        $count = app(PublicContactService::class)->purgeArchived();

        return back()->with('success', "{$count} message(s) archivé(s) supprimé(s).");
    }

    public function unreadCount(PublicContactService $contacts): JsonResponse
    {
        return response()->json([
            'newCount' => $contacts->pendingCount(),
        ]);
    }
}
