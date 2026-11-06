<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerNotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('storefront.account.notifications.index', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'notifications' => $request->user('customer')->notifications()->latest()->paginate(25),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user('customer')->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();
        $url = data_get($record->data, 'url');

        return is_string($url) && str_starts_with($url, '/account/tickets/') && ! str_starts_with($url, '//')
            ? redirect()->to($url)
            : back();
    }
}
