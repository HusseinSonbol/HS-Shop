<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\AboutFaq;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function __invoke(): View
    {
        $locale = app()->getLocale();
        $faqs = AboutFaq::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (AboutFaq $faq) use ($locale): AboutFaq {
                $translation = $faq->translations->firstWhere('locale', $locale)
                    ?? $faq->translations->firstWhere('locale', 'en')
                    ?? $faq->translations->first();

                $faq->setAttribute('display_question', $translation?->question);
                $faq->setAttribute('display_answer', $translation?->answer);

                return $faq;
            })
            ->filter(fn (AboutFaq $faq) => filled($faq->display_question) && filled($faq->display_answer))
            ->values();

        return view('storefront.about-us-4', [
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
            'faqs' => $faqs,
        ]);
    }
}
