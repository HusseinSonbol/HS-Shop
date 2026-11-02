<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['new', 'read', 'converted', 'closed', 'spam'])],
        ]);
        $messages = ContactMessage::query()
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $query->where(fn ($search) => $search
                    ->where('sender_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('subject', 'like', $term));
            })
            ->orderByDesc('received_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.contact-messages.index', compact('messages', 'filters'));
    }

    public function show(ContactMessage $contactMessage): View
    {
        return view('admin.contact-messages.show', compact('contactMessage'));
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['read', 'closed', 'spam'])],
        ], [
            'status.required' => __('admin.contact_messages.validation.status_required'),
            'status.in' => __('admin.contact_messages.validation.status_invalid'),
        ]);

        DB::transaction(function () use ($contactMessage, $data, $request): void {
            $record = ContactMessage::query()->whereKey($contactMessage->id)->lockForUpdate()->firstOrFail();
            $before = $record->status;
            $record->status = $data['status'];
            $isHandled = in_array($data['status'], ['closed', 'spam'], true);
            $record->handled_by = $isHandled ? (int) $request->user()->id : null;
            $record->handled_at = $isHandled ? now() : null;
            $record->save();

            DB::table('activity_logs')->insert([
                'actor_user_id' => (int) $request->user()->id,
                'subject_type' => ContactMessage::class,
                'subject_id' => $record->id,
                'event' => 'contact_message.status_changed',
                'before' => json_encode(['status' => $before], JSON_THROW_ON_ERROR),
                'after' => json_encode(['status' => $record->status], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'correlation_id' => $request->attributes->get('correlation_id'),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.contact-messages.show', $contactMessage)->with('status', __('admin.contact_messages.status_updated'));
    }
}
