<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(25),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();
        $url = data_get($record->data, 'url');

        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? redirect()->to($url) : back();
    }
}
