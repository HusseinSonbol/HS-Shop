<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AboutFaqRequest;
use App\Models\AboutFaq;
use App\Services\Admin\WebsiteContentManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AboutFaqController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['scope' => ['nullable', 'in:active,archived']]);
        $faqs = AboutFaq::query()
            ->when(($filters['scope'] ?? 'active') === 'archived', fn ($query) => $query->onlyTrashed())
            ->with('translations')->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString();

        return view('admin.about-faqs.index', ['faqs' => $faqs, 'filters' => $filters + ['scope' => 'active']]);
    }

    public function create(): View
    {
        return view('admin.about-faqs.form', ['faq' => new AboutFaq]);
    }

    public function store(AboutFaqRequest $request, WebsiteContentManagementService $content): RedirectResponse
    {
        $content->saveFaq(null, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.about-faqs.index')->with('status', __('admin.about_faqs.created'));
    }

    public function edit(AboutFaq $faq): View
    {
        $faq->load('translations');

        return view('admin.about-faqs.form', compact('faq'));
    }

    public function update(AboutFaqRequest $request, AboutFaq $faq, WebsiteContentManagementService $content): RedirectResponse
    {
        $content->saveFaq($faq, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.about-faqs.index')->with('status', __('admin.about_faqs.updated'));
    }

    public function archive(Request $request, AboutFaq $faq, WebsiteContentManagementService $content): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('faqs.manage'), 403);
        $content->archiveFaq($faq, (int) $request->user()->id);

        return redirect()->route('admin.about-faqs.index')->with('status', __('admin.about_faqs.archived'));
    }

    public function restore(Request $request, AboutFaq $faq, WebsiteContentManagementService $content): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('faqs.manage'), 403);
        abort_unless($faq->trashed(), 404);
        $content->restoreFaq($faq, (int) $request->user()->id);

        return redirect()->route('admin.about-faqs.index', ['scope' => 'archived'])->with('status', __('admin.about_faqs.restored'));
    }
}
