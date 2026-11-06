<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupportTicketService
{
    public function createForCustomer(Customer $customer, array $data, array $files, User $actor): int
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($customer, $data, $files, $actor, &$stored): int {
                $user = $customer->user;
                $now = now();
                $ticketId = DB::table('support_tickets')->insertGetId([
                    'ticket_number' => $this->ticketNumber(),
                    'customer_id' => $customer->id,
                    'order_id' => $data['order_id'] ?? null,
                    'subject' => $data['subject'],
                    'status' => 'open',
                    'priority' => 'normal',
                    'requester_name' => $user?->name ?: $customer->display_name,
                    'requester_email' => $user?->email ?: $customer->email,
                    'requester_phone' => $user?->phone ?: $customer->phone,
                    'opened_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $messageId = $this->insertMessage($ticketId, 'customer', (int) $customer->id, null, 'public', $data['body'], $now);
                $this->storeAttachments($ticketId, $messageId, $files, $stored, $now);
                $this->event($ticketId, (int) $actor->id, 'created', null, 'open', ['source' => 'customer_portal'], $now);
                $this->notifyStaff($ticketId, 'ticket_created', __('store.tickets.staff_new_ticket', ['number' => DB::table('support_tickets')->where('id', $ticketId)->value('ticket_number')]));

                return $ticketId;
            });
        } catch (Throwable $exception) {
            $this->removeStoredFiles($stored);
            throw $exception;
        }
    }

    public function convertContact(ContactMessage $contactMessage, array $data, User $actor): int
    {
        return DB::transaction(function () use ($contactMessage, $data, $actor): int {
            $message = ContactMessage::query()->whereKey($contactMessage->id)->lockForUpdate()->firstOrFail();
            if ($message->converted_ticket_id) {
                return (int) $message->converted_ticket_id;
            }

            $now = now();
            $ticketId = DB::table('support_tickets')->insertGetId([
                'ticket_number' => $this->ticketNumber(),
                'customer_id' => $message->customer_id,
                'subject' => $message->subject ?: __('admin.contact_messages.no_subject'),
                'status' => 'open',
                'priority' => $data['priority'] ?? 'normal',
                'category' => $data['category'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'requester_name' => $message->sender_name,
                'requester_email' => $message->email,
                'requester_phone' => $message->phone,
                'opened_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertMessage($ticketId, 'customer', $message->customer_id, null, 'public', $message->message, $now);
            $this->event($ticketId, (int) $actor->id, 'converted_from_contact', null, null, ['contact_message_id' => $message->id], $now);
            $message->forceFill([
                'status' => 'converted',
                'converted_ticket_id' => $ticketId,
                'handled_by' => $actor->id,
                'handled_at' => $now,
            ])->save();
            $this->activity($actor, 'contact_message.converted_to_ticket', ContactMessage::class, $message->id, null, ['ticket_id' => $ticketId]);
            $this->notifyStaff($ticketId, 'ticket_created', __('admin.support_tickets.staff_new_ticket', ['number' => DB::table('support_tickets')->where('id', $ticketId)->value('ticket_number')]));

            return $ticketId;
        });
    }

    public function customerReply(int $ticketId, Customer $customer, string $body, array $files, User $actor): void
    {
        $stored = [];

        try {
            DB::transaction(function () use ($ticketId, $customer, $body, $files, $actor, &$stored): void {
                $ticket = DB::table('support_tickets')->where('id', $ticketId)->where('customer_id', $customer->id)->lockForUpdate()->first();
                abort_unless($ticket, 404);
                if (in_array($ticket->status, ['resolved', 'closed'], true)) {
                    throw ValidationException::withMessages(['body' => __('store.tickets.reply_closed')]);
                }

                $now = now();
                $messageId = $this->insertMessage($ticketId, 'customer', (int) $customer->id, null, 'public', $body, $now);
                $this->storeAttachments($ticketId, $messageId, $files, $stored, $now);
                if ($ticket->status === 'waiting_customer') {
                    DB::table('support_tickets')->where('id', $ticketId)->update(['status' => 'in_progress', 'updated_at' => $now]);
                    $this->event($ticketId, (int) $actor->id, 'status_changed', 'waiting_customer', 'in_progress', ['trigger' => 'customer_reply'], $now);
                }
                $this->event($ticketId, (int) $actor->id, 'customer_replied', null, null, ['message_id' => $messageId], $now);
                $this->notifyStaff($ticketId, 'customer_replied', __('admin.support_tickets.customer_replied_notice', ['number' => $ticket->ticket_number]));
            });
        } catch (Throwable $exception) {
            $this->removeStoredFiles($stored);
            throw $exception;
        }
    }

    public function staffReply(int $ticketId, User $actor, string $body, string $visibility, array $files): void
    {
        $stored = [];

        try {
            DB::transaction(function () use ($ticketId, $actor, $body, $visibility, $files, &$stored): void {
                $ticket = DB::table('support_tickets')->where('id', $ticketId)->lockForUpdate()->firstOrFail();
                $now = now();
                $messageId = $this->insertMessage($ticketId, 'staff', null, (int) $actor->id, $visibility, $body, $now);
                $this->storeAttachments($ticketId, $messageId, $files, $stored, $now);
                if ($visibility === 'public') {
                    DB::table('support_tickets')->where('id', $ticketId)->whereNull('first_response_at')->update([
                        'first_response_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $customerUser = DB::table('customers')->join('users', 'users.id', '=', 'customers.user_id')
                        ->where('customers.id', $ticket->customer_id)->whereNull('customers.deleted_at')->select('users.*')->first();
                    if ($customerUser) {
                        User::query()->find($customerUser->id)?->notify(new SupportTicketNotification($ticketId, $ticket->ticket_number, 'staff_replied', __('store.tickets.staff_replied_notice', ['number' => $ticket->ticket_number]), true));
                    }
                    $this->event($ticketId, (int) $actor->id, 'public_reply_sent', null, null, ['message_id' => $messageId], $now);
                } else {
                    $this->event($ticketId, (int) $actor->id, 'internal_note_added', null, null, ['message_id' => $messageId], $now);
                }
                $this->activity($actor, $visibility === 'public' ? 'support_ticket.public_reply' : 'support_ticket.internal_note', SupportTicket::class, $ticketId, null, ['message_id' => $messageId, 'visibility' => $visibility]);
            });
        } catch (Throwable $exception) {
            $this->removeStoredFiles($stored);
            throw $exception;
        }
    }

    public function assign(int $ticketId, User $actor, ?int $assignedTo): void
    {
        DB::transaction(function () use ($ticketId, $actor, $assignedTo): void {
            $ticket = DB::table('support_tickets')->where('id', $ticketId)->lockForUpdate()->firstOrFail();
            $assignee = $assignedTo ? User::query()->whereKey($assignedTo)->where('is_active', true)->whereNull('deleted_at')->first() : null;
            if ($assignedTo && (! $assignee || ! $assignee->hasPermission('support_tickets.manage'))) {
                throw ValidationException::withMessages(['assigned_to' => __('admin.support_tickets.validation.assignee_invalid')]);
            }
            $before = $ticket->assigned_to;
            DB::table('support_tickets')->where('id', $ticketId)->update(['assigned_to' => $assignedTo, 'updated_at' => now()]);
            $this->event($ticketId, (int) $actor->id, 'assignment_changed', $before ? (string) $before : null, $assignedTo ? (string) $assignedTo : null, null, now());
            $this->activity($actor, 'support_ticket.assignment_changed', SupportTicket::class, $ticketId, ['assigned_to' => $before], ['assigned_to' => $assignedTo]);
            if ($assignedTo) {
                $assignee?->notify(new SupportTicketNotification($ticketId, $ticket->ticket_number, 'assigned', __('admin.support_tickets.assigned_notice', ['number' => $ticket->ticket_number])));
            }
        });
    }

    public function updateStatus(int $ticketId, User $actor, string $status, ?string $note): void
    {
        DB::transaction(function () use ($ticketId, $actor, $status, $note): void {
            $ticket = DB::table('support_tickets')->where('id', $ticketId)->lockForUpdate()->firstOrFail();
            $before = $ticket->status;
            $now = now();
            DB::table('support_tickets')->where('id', $ticketId)->update([
                'status' => $status,
                'resolved_at' => $status === 'resolved' ? $now : ($before === 'resolved' ? null : $ticket->resolved_at),
                'closed_at' => $status === 'closed' ? $now : ($before === 'closed' ? null : $ticket->closed_at),
                'updated_at' => $now,
            ]);
            $this->event($ticketId, (int) $actor->id, 'status_changed', $before, $status, ['note' => $note], $now);
            $this->activity($actor, 'support_ticket.status_changed', SupportTicket::class, $ticketId, ['status' => $before], ['status' => $status]);
            $customerUserId = DB::table('customers')->where('id', $ticket->customer_id)->whereNull('deleted_at')->value('user_id');
            if ($customerUserId) {
                User::query()->find($customerUserId)?->notify(new SupportTicketNotification($ticketId, $ticket->ticket_number, 'status_changed', __('store.tickets.status_notice', ['number' => $ticket->ticket_number, 'status' => __('store.tickets.statuses.'.$status)]), true));
            }
        });
    }

    public function notifyStaff(int $ticketId, string $event, string $summary): void
    {
        $ticket = DB::table('support_tickets')->where('id', $ticketId)->first();
        if (! $ticket) {
            return;
        }
        $recipients = $ticket->assigned_to
            ? User::query()->whereKey($ticket->assigned_to)->where('is_active', true)->get()
            : User::query()->where('is_active', true)->whereNull('deleted_at')
                ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.code', 'support_tickets.manage'))->get();
        foreach ($recipients as $recipient) {
            if ($recipient->hasPermission('support_tickets.manage')) {
                $recipient->notify(new SupportTicketNotification($ticketId, $ticket->ticket_number, $event, $summary));
            }
        }
    }

    private function insertMessage(int $ticketId, string $senderType, ?int $customerId, ?int $staffId, string $visibility, string $body, $now): int
    {
        return DB::table('support_ticket_messages')->insertGetId([
            'support_ticket_id' => $ticketId,
            'sender_type' => $senderType,
            'customer_id' => $customerId,
            'staff_user_id' => $staffId,
            'visibility' => $visibility,
            'body' => $body,
            'sent_at' => $now,
            'created_at' => $now,
        ]);
    }

    private function storeAttachments(int $ticketId, int $messageId, array $files, array &$stored, $now): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = Storage::disk('local')->putFileAs('support-tickets/'.$ticketId.'/'.$messageId, $file, $file->hashName());
            if (! $path) {
                throw new \RuntimeException('Unable to store support attachment.');
            }
            $stored[] = $path;
            DB::table('support_ticket_attachments')->insert([
                'support_ticket_message_id' => $messageId,
                'storage_path' => $path,
                'original_name' => mb_substr(basename(str_replace('\\\\', '/', $file->getClientOriginalName())), 0, 255),
                'mime_type' => (string) $file->getMimeType(),
                'byte_size' => (int) $file->getSize(),
                'file_sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_at' => $now,
            ]);
        }
    }

    private function removeStoredFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    private function event(int $ticketId, ?int $actorId, string $type, ?string $before, ?string $after, ?array $details, $now): void
    {
        DB::table('support_ticket_events')->insert([
            'support_ticket_id' => $ticketId,
            'actor_user_id' => $actorId,
            'event_type' => $type,
            'previous_value' => $before,
            'new_value' => $after,
            'details' => $details ? json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
    }

    private function activity(User $actor, string $event, string $type, int $id, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actor->id,
            'event' => $event,
            'subject_type' => $type,
            'subject_id' => $id,
            'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'after' => $after ? json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
        ]);
    }

    private function ticketNumber(): string
    {
        return 'TKT-'.Str::upper(Str::random(10));
    }
}
