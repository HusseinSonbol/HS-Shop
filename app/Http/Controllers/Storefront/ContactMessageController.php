<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;

class ContactMessageController extends Controller
{
    public function store(ContactMessageRequest $request): RedirectResponse
    {
        $customerId = $request->user()
            ? Customer::query()->where('user_id', $request->user()->id)->value('id')
            : null;

        ContactMessage::query()->create([
            ...$request->safe()->only(['sender_name', 'email', 'phone', 'subject', 'message']),
            'customer_id' => $customerId,
            'status' => 'new',
            'received_at' => now(),
        ]);

        return redirect()->to(route('store.home').'#hs-contact')->with('contact_status', __('store.contact.sent'));
    }
}
