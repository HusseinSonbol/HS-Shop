<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConvertContactToTicketRequest;
use App\Http\Requests\Admin\SupportTicketAssignmentRequest;
use App\Http\Requests\Admin\SupportTicketReplyRequest;
use App\Http\Requests\Admin\SupportTicketStatusRequest;
use App\Models\ContactMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])],
        ]);
        $tickets = SupportTicket::query()->leftJoin('customers', 'customers.id', '=', 'support_tickets.customer_id')
            ->leftJoin('users as assignees', 'assignees.id', '=', 'support_tickets.assigned_to')
            ->select('support_tickets.*', 'customers.display_name as customer_name', 'assignees.name as assignee_name')
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('support_tickets.status', $filters['status']))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $query->where(fn ($search) => $search->where('support_tickets.ticket_number', 'like', $term)
                    ->orWhere('support_tickets.subject', 'like', $term)->orWhere('support_tickets.requester_name', 'like', $term)
                    ->orWhere('support_tickets.requester_email', 'like', $term));
            })->orderByRaw("FIELD(support_tickets.status, 'open', 'in_progress', 'waiting_customer', 'resolved', 'closed')")
            ->orderByDesc('support_tickets.updated_at')->paginate(20)->withQueryString();

        return view('admin.support-tickets.index', compact('tickets', 'filters'));
    }

    public function show(int $ticket): View
    {
        $record = SupportTicket::query()->leftJoin('customers', 'customers.id', '=', 'support_tickets.customer_id')
            ->leftJoin('orders', 'orders.id', '=', 'support_tickets.order_id')
            ->leftJoin('users as assignees', 'assignees.id', '=', 'support_tickets.assigned_to')
            ->where('support_tickets.id', $ticket)
            ->select('support_tickets.*', 'customers.display_name as customer_name', 'orders.order_number', 'assignees.name as assignee_name')
            ->firstOrFail();
        $messages = DB::table('support_ticket_messages')->leftJoin('users as staff', 'staff.id', '=', 'support_ticket_messages.staff_user_id')
            ->leftJoin('customers', 'customers.id', '=', 'support_ticket_messages.customer_id')
            ->where('support_ticket_messages.support_ticket_id', $ticket)
            ->select('support_ticket_messages.*', 'staff.name as staff_name', 'customers.display_name as customer_name')->orderBy('sent_at')->get();
        $attachments = $messages->isEmpty() ? collect() : DB::table('support_ticket_attachments')
            ->whereIn('support_ticket_message_id', $messages->pluck('id'))->orderBy('uploaded_at')->get()->groupBy('support_ticket_message_id');
        $events = DB::table('support_ticket_events')->leftJoin('users', 'users.id', '=', 'support_ticket_events.actor_user_id')
            ->where('support_ticket_id', $ticket)->select('support_ticket_events.*', 'users.name as actor_name')->orderByDesc('occurred_at')->get();
        $staff = User::query()->where('is_active', true)->whereNull('deleted_at')
            ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.code', 'support_tickets.manage'))
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.support-tickets.show', compact('record', 'messages', 'attachments', 'events', 'staff'));
    }

    public function convertContact(ConvertContactToTicketRequest $request, ContactMessage $contactMessage, SupportTicketService $tickets): RedirectResponse
    {
        $ticketId = $tickets->convertContact($contactMessage, $request->validated(), $request->user());

        return redirect()->route('admin.support-tickets.show', $ticketId)->with('status', __('admin.support_tickets.converted'));
    }

    public function assign(SupportTicketAssignmentRequest $request, int $ticket, SupportTicketService $tickets): RedirectResponse
    {
        $tickets->assign($ticket, $request->user(), $request->validated('assigned_to'));

        return back()->with('status', __('admin.support_tickets.assignment_saved'));
    }

    public function updateStatus(SupportTicketStatusRequest $request, int $ticket, SupportTicketService $tickets): RedirectResponse
    {
        $tickets->updateStatus($ticket, $request->user(), $request->validated('status'), $request->validated('note'));

        return back()->with('status', __('admin.support_tickets.status_saved'));
    }

    public function reply(SupportTicketReplyRequest $request, int $ticket, SupportTicketService $tickets): RedirectResponse
    {
        $tickets->staffReply($ticket, $request->user(), $request->validated('body'), $request->validated('visibility'), $request->file('attachments', []));

        return back()->with('status', __('admin.support_tickets.message_saved'));
    }

    public function attachment(int $ticket, int $attachment): StreamedResponse
    {
        $file = DB::table('support_ticket_attachments')->join('support_ticket_messages', 'support_ticket_messages.id', '=', 'support_ticket_attachments.support_ticket_message_id')
            ->where('support_ticket_attachments.id', $attachment)->where('support_ticket_messages.support_ticket_id', $ticket)
            ->select('support_ticket_attachments.storage_path', 'support_ticket_attachments.original_name')->first();
        abort_unless($file && Storage::disk('local')->exists($file->storage_path), 404);

        return Storage::disk('local')->download($file->storage_path, basename($file->original_name));
    }
}
