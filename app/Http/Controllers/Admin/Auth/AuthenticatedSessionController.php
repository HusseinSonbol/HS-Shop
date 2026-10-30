<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 60;

    public function create(): View|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        if ($user?->hasPermission('dashboard.access')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'email.required' => __('admin.login.validation.email_required'),
            'email.email' => __('admin.login.validation.email_invalid'),
            'password.required' => __('admin.login.validation.password_required'),
        ]);

        $throttleKey = hash('sha256', Str::lower($credentials['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('admin.login.throttled', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ])]);
        }

        $validCredentials = Auth::guard('web')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'));

        if (!$validCredentials) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('admin.login.failed')]);
        }

        $request->session()->regenerate();
        $user = Auth::guard('web')->user();

        if (!$user || !$user->hasPermission('dashboard.access')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('admin.login.failed')]);
        }

        RateLimiter::clear($throttleKey);
        if (in_array($user->preferred_locale, ['ar', 'en'], true)) {
            $request->session()->put('locale', $user->preferred_locale);
        }

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', __('admin.logout.success'));
    }
}
