<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BannerRequest;
use App\Http\Requests\Admin\HomepageSectionOrderRequest;
use App\Http\Requests\Admin\HomepageTestimonialRequest;
use App\Http\Requests\Admin\ProductCollectionItemRequest;
use App\Http\Requests\Admin\ProductCollectionOrderRequest;
use App\Models\Banner;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Services\Admin\WebsiteContentManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomepageContentController extends Controller
{
    public function index(): View
    {
        $sections = HomepageSection::query()->with(['translations', 'collection.translations'])
            ->orderBy('sort_order')->orderBy('id')->get();
        $banners = Banner::query()->with('translations')->orderBy('placement')->orderBy('sort_order')->orderBy('id')->paginate(20);

        $testimonialSection = $sections->firstWhere('code', 'home_testimonials');
        $testimonials = collect($testimonialSection?->settings['testimonials'] ?? [])->sortBy('sort_order')->values();

        return view('admin.homepage.index', compact('sections', 'banners', 'testimonials', 'testimonialSection'));
    }

    public function createTestimonial(): View
    {
        return view('admin.homepage.testimonial-form', ['testimonial' => null]);
    }

    public function editTestimonial(string $testimonialId): View
    {
        $testimonial = $this->findTestimonial($testimonialId);

        return view('admin.homepage.testimonial-form', compact('testimonial'));
    }

    public function storeTestimonial(HomepageTestimonialRequest $request, WebsiteContentManagementService $content): RedirectResponse
    {
        $content->saveHomepageTestimonial(null, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.homepage.index')->with('status', __('admin.homepage.testimonials.created'));
    }

    public function updateTestimonial(HomepageTestimonialRequest $request, string $testimonialId, WebsiteContentManagementService $content): RedirectResponse
    {
        $this->findTestimonial($testimonialId);
        $content->saveHomepageTestimonial($testimonialId, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.homepage.index')->with('status', __('admin.homepage.testimonials.updated'));
    }

    public function destroyTestimonial(Request $request, string $testimonialId, WebsiteContentManagementService $content): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('homepage.manage'), 403);
        $content->removeHomepageTestimonial($testimonialId, (int) $request->user()->id);

        return redirect()->route('admin.homepage.index')->with('status', __('admin.homepage.testimonials.removed'));
    }

    /** @return array<string, mixed> */
    private function findTestimonial(string $testimonialId): array
    {
        $settings = HomepageSection::query()->where('code', 'home_testimonials')->firstOrFail()->settings ?? [];
        $items = $settings['testimonials'] ?? [];
        $testimonial = collect($items)->firstWhere('id', $testimonialId);
        abort_if(! $testimonial, 404);

        return $testimonial;
    }

    public function initializeDefaults(Request $request, WebsiteContentManagementService $content): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('homepage.manage'), 403);
        $content->initializeHomepageDefaults((int) $request->user()->id);

        return redirect()->route('admin.homepage.index')->with('status', __('admin.homepage.defaults_ready'));
    }

    public function createBanner(): View
    {
        return view('admin.homepage.banner-form', ['banner' => new Banner]);
    }

    public function editBanner(Banner $banner): View
    {
        $banner->load('translations');

        return view('admin.homepage.banner-form', compact('banner'));
    }

    public function storeBanner(BannerRequest $request, WebsiteContentManagementService $content): RedirectResponse
    {
        $content->saveBanner(null, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.homepage.index')->with('status', __('admin.homepage.banner_created'));
    }

    public function updateBanner(BannerRequest $request, Banner $banner, WebsiteContentManagementService $content): RedirectResponse
    {
        $content->saveBanner($banner, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.homepage.index')->with('status', __('admin.homepage.banner_updated'));
    }

    public function updateSections(HomepageSectionOrderRequest $request, WebsiteContentManagementService $content): RedirectResponse
    {
        $content->updateSections($request->validated('sections'), (int) $request->user()->id);

        return redirect()->route('admin.homepage.index', [], 303)->with('status', __('admin.homepage.sections_updated'));
    }

    public function collections(Request $request): View
    {
        $filters = $request->validate(['product_q' => ['nullable', 'string', 'max:100']]);
        $collections = ProductCollection::query()->whereIn('code', ['new', 'popular'])->with([
            'translations',
            'products' => fn ($query) => $query->with(['translations', 'images' => fn ($images) => $images->where('is_primary', true)]),
        ])->orderBy('sort_order')->get();
        $products = Product::query()->where('status', 'published')->where('store_availability_status', 'available')
            ->when(filled($filters['product_q'] ?? null), function ($query) use ($filters): void {
                $term = '%'.$filters['product_q'].'%';
                $query->where(fn ($search) => $search->where('sku', 'like', $term)->orWhereHas('translations', fn ($translations) => $translations->where('name', 'like', $term)));
            })->with('translations')->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return view('admin.homepage.collections', compact('collections', 'products', 'filters'));
    }

    public function addCollectionProduct(ProductCollectionItemRequest $request, ProductCollection $collection, WebsiteContentManagementService $content): RedirectResponse
    {
        abort_unless(in_array($collection->code, ['new', 'popular'], true), 404);
        $content->addCollectionProduct($collection, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.homepage.collections')->with('status', __('admin.homepage.product_added'));
    }

    public function reorderCollection(ProductCollectionOrderRequest $request, ProductCollection $collection, WebsiteContentManagementService $content): RedirectResponse
    {
        abort_unless(in_array($collection->code, ['new', 'popular'], true), 404);
        $content->reorderCollection($collection, $request->validated('items'), (int) $request->user()->id);

        return redirect()->route('admin.homepage.collections')->with('status', __('admin.homepage.collection_reordered'));
    }

    public function removeCollectionProduct(Request $request, ProductCollection $collection, int $item, WebsiteContentManagementService $content): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('homepage.manage'), 403);
        abort_unless(in_array($collection->code, ['new', 'popular'], true), 404);
        $content->removeCollectionProduct($collection, $item, (int) $request->user()->id);

        return redirect()->route('admin.homepage.collections')->with('status', __('admin.homepage.product_removed'));
    }
}
