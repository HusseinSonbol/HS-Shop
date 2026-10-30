<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        validator(['locale' => $locale], [
            'locale' => ['required', Rule::in(['ar', 'en'])],
        ])->validate();

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['preferred_locale' => $locale])->save();

        $previousUrl = URL::previous();
        if ($this->isLocalAdminUrl($previousUrl, $request)) {
            return redirect()->to($previousUrl);
        }

        return redirect()->route(
            $request->user()?->hasPermission('dashboard.access') ? 'admin.dashboard' : 'admin.login',
        );
    }

    private function isLocalAdminUrl(string $url, Request $request): bool
    {
        $target = parse_url($url);
        if (! is_array($target) || ! isset($target['scheme'], $target['host'], $target['path'])) {
            return false;
        }

        $current = parse_url($request->getSchemeAndHttpHost());
        $sameOrigin = isset($current['scheme'], $current['host'])
            && strtolower($target['scheme']) === strtolower($current['scheme'])
            && strtolower($target['host']) === strtolower($current['host'])
            && ($target['port'] ?? null) === ($current['port'] ?? null);
        $isAdminPath = $target['path'] === '/admin' || str_starts_with($target['path'], '/admin/');

        return $sameOrigin && $isAdminPath;
    }
}
