<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\Storefront\FavoriteManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    public function createLogin(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->intended(route('store.home'));
        }

        return view('storefront.account.login', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
        ]);
    }

    public function storeLogin(Request $request, FavoriteManagementService $favorites): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:1024'],
        ], [
            'email.required' => __('store.account.validation.email_required'),
            'email.string' => __('store.account.validation.email_invalid'),
            'email.email' => __('store.account.validation.email_invalid'),
            'email.max' => __('store.account.validation.email_max'),
            'password.required' => __('store.account.validation.password_required'),
            'password.string' => __('store.account.validation.password_invalid'),
            'password.max' => __('store.account.validation.password_invalid'),
        ]);

        $throttleKey = hash('sha256', Str::lower($credentials['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('store.account.login_throttled', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ])]);
        }

        $authenticated = Auth::guard('customer')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ]);

        $user = Auth::guard('customer')->user();
        $hasCustomerRecord = $user && Customer::withTrashed()->where('user_id', $user->id)->exists();

        if (! $authenticated || ! $hasCustomerRecord) {
            Auth::guard('customer')->logout();
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('store.account.login_failed')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $favoriteApplied = $user->customer
            ? $favorites->applyPendingFavorite($request, $user->customer)
            : false;
        $response = redirect()->intended(route('store.home'))
            ->with('account_status', __('store.account.logged_in'));

        return $favoriteApplied
            ? $response->with('favorite_status', __('store.favorites.added'))
            : $response;
    }

    public function createRegistration(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->intended(route('store.home'));
        }

        return view('storefront.account.register', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
        ]);
    }

    public function storeRegistration(Request $request, FavoriteManagementService $favorites): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:9', 'max:255', 'confirmed'],
        ], [
            'name.required' => __('store.account.validation.name_required'),
            'name.string' => __('store.account.validation.name_required'),
            'name.max' => __('store.account.validation.name_max'),
            'email.required' => __('store.account.validation.email_required'),
            'email.string' => __('store.account.validation.email_invalid'),
            'email.email' => __('store.account.validation.email_invalid'),
            'email.max' => __('store.account.validation.email_max'),
            'email.unique' => __('store.account.validation.email_taken'),
            'phone.required' => __('store.account.validation.phone_required'),
            'phone.string' => __('store.account.validation.phone_required'),
            'phone.max' => __('store.account.validation.phone_max'),
            'password.required' => __('store.account.validation.password_required'),
            'password.string' => __('store.account.validation.password_invalid'),
            'password.min' => __('store.account.validation.password_min'),
            'password.max' => __('store.account.validation.password_invalid'),
            'password.confirmed' => __('store.account.validation.password_confirmation'),
        ]);

        try {
            $user = DB::transaction(function () use ($data): User {
                $user = User::query()->create([
                    'name' => trim($data['name']),
                    'email' => mb_strtolower(trim($data['email'])),
                    'phone' => trim($data['phone']),
                    'password' => $data['password'],
                    'preferred_locale' => app()->getLocale(),
                    'is_active' => true,
                ]);

                Customer::query()->create([
                    'user_id' => $user->id,
                    'customer_number' => 'CUS-'.Str::upper((string) Str::ulid()),
                    'display_name' => trim($data['name']),
                    'phone' => trim($data['phone']),
                    'email' => mb_strtolower(trim($data['email'])),
                ]);

                return $user;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return back()
                ->withInput($request->only('name', 'email', 'phone'))
                ->withErrors(['email' => __('store.account.validation.email_taken')]);
        }

        Auth::guard('customer')->login($user);
        $request->session()->regenerate();
        $favoriteApplied = $favorites->applyPendingFavorite($request, $user->customer()->firstOrFail());
        $response = redirect()->intended(route('store.home'))
            ->with('account_status', __('store.account.registered'));

        return $favoriteApplied
            ? $response->with('favorite_status', __('store.favorites.added'))
            : $response;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->forget(['url.intended', 'pending_favorite_product_id']);
        $request->session()->regenerate();

        return redirect()->route('store.home')->with('account_status', __('store.account.logged_out'));
    }
}
