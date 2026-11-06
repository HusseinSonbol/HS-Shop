<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SupportTicketReplyRequest;
use App\Http\Requests\Storefront\SupportTicketRequest;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $this->customer($request);
        $tickets = SupportTicket::query()->where('customer_id', $customer->id)
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(15);

        return view('storefront.account.tickets.index', $this->viewData($request) + compact('tickets'));
    }

    public function create(Request $request): View
    {
        $customer = $this->customer($request);
        $orders = DB::table('orders')->where('customer_id', $customer->id)
            ->orderByDesc('placed_at')->limit(50)->get(['public_id', 'order_number']);

        return view('storefront.account.tickets.create', $this->viewData($request) + compact('orders'));
    }

    public function store(SupportTicketRequest $request, SupportTicketService $tickets): RedirectResponse
    {
        $customer = $this->customer($request);
        $data = $request->validated();
        $orderId = null;
        if (! empty($data['order_public_id'])) {
            $orderId = DB::table('orders')->where('customer_id', $customer->id)
                ->where('public_id', $data['order_public_id'])->value('id');
            if (! $orderId) {
                return back()->withInput()->withErrors(['order_public_id' => __('store.tickets.order_invalid')]);
            }
        }

        $id = $tickets->createForCustomer($customer, [
            'subject' => $data['subject'], 'body' => $data['body'], 'order_id' => $orderId,
        ], $request->file('attachments', []), $request->user('customer'));

        return redirect()->route('store.account.tickets.show', $id)->with('account_status', __('store.tickets.created'));
    }

    public function show(Request $request, int $ticket): View
    {
        $customer = $this->customer($request);
        $record = SupportTicket::query()->where('customer_id', $customer->id)->findOrFail($ticket);
        $messages = DB::table('support_ticket_messages')->where('support_ticket_id', $record->id)
            ->where('visibility', 'public')->orderBy('sent_at')->get();
        $messageIds = $messages->pluck('id');
        $attachments = $messageIds->isEmpty() ? collect() : DB::table('support_ticket_attachments')
            ->whereIn('support_ticket_message_id', $messageIds)->orderBy('uploaded_at')->get();
        $events = DB::table('support_ticket_events')->where('support_ticket_id', $record->id)
            ->where('event_type', 'status_changed')->whereNotNull('new_value')->orderBy('occurred_at')->get();

        return view('storefront.account.tickets.show', $this->viewData($request) + compact('record', 'messages', 'attachments', 'events'));
    }

    public function reply(SupportTicketReplyRequest $request, int $ticket, SupportTicketService $tickets): RedirectResponse
    {
        $customer = $this->customer($request);
        abort_unless(SupportTicket::query()->where('customer_id', $customer->id)->whereKey($ticket)->exists(), 404);
        $tickets->customerReply($ticket, $customer, $request->validated('body'), $request->file('attachments', []), $request->user('customer'));

        return redirect()->route('store.account.tickets.show', $ticket)->with('account_status', __('store.tickets.reply_sent'));
    }

    public function attachment(Request $request, int $ticket, int $attachment): StreamedResponse
    {
        $customer = $this->customer($request);
        $file = DB::table('support_ticket_attachments')->join('support_ticket_messages', 'support_ticket_messages.id', '=', 'support_ticket_attachments.support_ticket_message_id')
            ->join('support_tickets', 'support_tickets.id', '=', 'support_ticket_messages.support_ticket_id')
            ->where('support_ticket_attachments.id', $attachment)->where('support_tickets.id', $ticket)
            ->where('support_tickets.customer_id', $customer->id)->where('support_ticket_messages.visibility', 'public')
            ->select('support_ticket_attachments.storage_path', 'support_ticket_attachments.original_name')->first();
        abort_unless($file && Storage::disk('local')->exists($file->storage_path), 404);

        return Storage::disk('local')->download($file->storage_path, basename($file->original_name));
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer')?->customer;
        abort_unless($customer && ! $customer->trashed(), 403);

        return $customer;
    }

    private function viewData(Request $request): array
    {
        return ['locale' => app()->getLocale(), 'isRtl' => app()->getLocale() === 'ar'];
    }
}
