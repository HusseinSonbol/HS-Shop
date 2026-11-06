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
        ], [
            'locale.required' => __('admin.locale.invalid'),
            'locale.in' => __('admin.locale.invalid'),
        ])->validate();

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['preferred_locale' => $locale])->save();

        $previousUrl = URL::previous();
        if ($this->isSafeSameOriginUrl($previousUrl, $request)) {
            return redirect()->to($previousUrl);
        }

        return redirect()->route(
            $request->user()?->hasPermission('dashboard.access') ? 'admin.dashboard' : 'admin.login',
        );
    }

    private function isSafeSameOriginUrl(string $url, Request $request): bool
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
        $path = $target['path'];
        $isSafeLocalPath = str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! str_contains($path, '\\');

        return $sameOrigin && $isSafeLocalPath;
    }
}
