<?php

use App\Http\Controllers\Admin\AboutFaqController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerAddressController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomepageContentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductSupplierController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ContactMessageController as StorefrontContactMessageController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Storefront\CartItemController as StorefrontCartItemController;
use App\Http\Controllers\Storefront\CustomerAccountController;
use App\Http\Controllers\Storefront\ProductFavoriteController;
use App\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('store.home');
Route::get('/index.html', HomeController::class);
Route::get('/shop', [StorefrontCategoryController::class, 'index'])->name('store.shop');
Route::get('/category.html', [StorefrontCategoryController::class, 'index']);
Route::get('/category/{slug?}', [StorefrontCategoryController::class, 'index'])->name('store.category');
Route::view('/product-v2.html', 'storefront.product-v2');
Route::get('/product/{slug?}', [StorefrontProductController::class, 'show'])->name('store.product');
Route::post('/cart/items', [StorefrontCartItemController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('store.cart.items.store');
Route::get('/account/login', [CustomerAccountController::class, 'createLogin'])->name('store.account.login');
Route::post('/account/login', [CustomerAccountController::class, 'storeLogin'])
    ->middleware('throttle:10,1')
    ->name('store.account.login.store');
Route::get('/account/register', [CustomerAccountController::class, 'createRegistration'])->name('store.account.register');
Route::post('/account/register', [CustomerAccountController::class, 'storeRegistration'])
    ->middleware('throttle:5,10')
    ->name('store.account.register.store');
Route::post('/account/logout', [CustomerAccountController::class, 'destroy'])->name('store.account.logout');
Route::post('/product/{product:slug}/favorite', [ProductFavoriteController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('store.favorites.update');
Route::view('/about-us', 'storefront.about-us-4')->name('store.about');
Route::view('/about-us-4.html', 'storefront.about-us-4');

Route::post('/contact-messages', [StorefrontContactMessageController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('store.contact-messages.store');

Route::post('/locale/{locale}', [LocaleController::class, 'update'])
    ->whereIn('locale', ['ar', 'en'])
    ->name('locale.update');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });

    Route::middleware(['auth', 'admin.permission:dashboard.access'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
    });

    Route::middleware(['auth', 'admin.permission:contact_messages.manage'])->prefix('contact-messages')->name('contact-messages.')->group(function (): void {
        Route::get('/', [AdminContactMessageController::class, 'index'])->name('index');
        Route::get('/{contactMessage}', [AdminContactMessageController::class, 'show'])->whereNumber('contactMessage')->name('show');
        Route::patch('/{contactMessage}/status', [AdminContactMessageController::class, 'updateStatus'])->whereNumber('contactMessage')->name('status');
    });

    Route::middleware(['auth', 'admin.permission:users.manage'])->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
        Route::patch('/staff/{user}/active', [StaffController::class, 'toggleActive'])->name('staff.active');
        Route::delete('/staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });

    Route::middleware(['auth', 'admin.permission:customers.manage'])->group(function (): void {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->withTrashed()->name('customers.show');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'archive'])->name('customers.archive');
        Route::patch('/customers/{customer}/restore', [CustomerController::class, 'restore'])->withTrashed()->name('customers.restore');

        Route::get('/customers/{customer}/addresses/create', [CustomerAddressController::class, 'create'])->name('customers.addresses.create');
        Route::post('/customers/{customer}/addresses', [CustomerAddressController::class, 'store'])->name('customers.addresses.store');
        Route::get('/customers/{customer}/addresses/{address}/edit', [CustomerAddressController::class, 'edit'])->name('customers.addresses.edit');
        Route::put('/customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update'])->name('customers.addresses.update');
        Route::delete('/customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'archive'])->name('customers.addresses.archive');
    });

    Route::middleware(['auth', 'admin.permission:catalog.manage'])->group(function (): void {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'archive'])->name('products.archive');
        Route::patch('/products/{product}/restore', [ProductController::class, 'restore'])->withTrashed()->name('products.restore');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'archive'])->name('categories.archive');
        Route::patch('/categories/{category}/restore', [CategoryController::class, 'restore'])->withTrashed()->name('categories.restore');
    });

    Route::middleware(['auth', 'admin.permission:suppliers.manage'])->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'archive'])->name('suppliers.archive');
        Route::patch('/suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->withTrashed()->name('suppliers.restore');
        Route::get('/suppliers/{supplier}/offers/create', [ProductSupplierController::class, 'create'])->name('suppliers.offers.create');
        Route::post('/suppliers/{supplier}/offers', [ProductSupplierController::class, 'store'])->name('suppliers.offers.store');
        Route::get('/suppliers/{supplier}/offers/{offer}/edit', [ProductSupplierController::class, 'edit'])->name('suppliers.offers.edit');
        Route::put('/suppliers/{supplier}/offers/{offer}', [ProductSupplierController::class, 'update'])->name('suppliers.offers.update');
    });

    Route::middleware(['auth', 'admin.permission:homepage.manage'])->prefix('homepage')->name('homepage.')->group(function (): void {
        Route::get('/', [HomepageContentController::class, 'index'])->name('index');
        Route::post('/initialize', [HomepageContentController::class, 'initializeDefaults'])->name('initialize');
        Route::patch('/sections', [HomepageContentController::class, 'updateSections'])->name('sections.update');
        Route::get('/banners/create', [HomepageContentController::class, 'createBanner'])->name('banners.create');
        Route::post('/banners', [HomepageContentController::class, 'storeBanner'])->name('banners.store');
        Route::get('/banners/{banner}/edit', [HomepageContentController::class, 'editBanner'])->name('banners.edit');
        Route::put('/banners/{banner}', [HomepageContentController::class, 'updateBanner'])->name('banners.update');
        Route::get('/testimonials/create', [HomepageContentController::class, 'createTestimonial'])->name('testimonials.create');
        Route::post('/testimonials', [HomepageContentController::class, 'storeTestimonial'])->name('testimonials.store');
        Route::get('/testimonials/{testimonialId}/edit', [HomepageContentController::class, 'editTestimonial'])->whereUuid('testimonialId')->name('testimonials.edit');
        Route::put('/testimonials/{testimonialId}', [HomepageContentController::class, 'updateTestimonial'])->whereUuid('testimonialId')->name('testimonials.update');
        Route::delete('/testimonials/{testimonialId}', [HomepageContentController::class, 'destroyTestimonial'])->whereUuid('testimonialId')->name('testimonials.destroy');
        Route::get('/collections', [HomepageContentController::class, 'collections'])->name('collections');
        Route::post('/collections/{collection}/items', [HomepageContentController::class, 'addCollectionProduct'])->name('collections.items.store');
        Route::patch('/collections/{collection}/items/order', [HomepageContentController::class, 'reorderCollection'])->name('collections.items.order');
        Route::delete('/collections/{collection}/items/{item}', [HomepageContentController::class, 'removeCollectionProduct'])->whereNumber('item')->name('collections.items.destroy');
    });

    Route::middleware(['auth', 'admin.permission:faqs.manage'])->prefix('about-faqs')->name('about-faqs.')->group(function (): void {
        Route::get('/', [AboutFaqController::class, 'index'])->name('index');
        Route::get('/create', [AboutFaqController::class, 'create'])->name('create');
        Route::post('/', [AboutFaqController::class, 'store'])->name('store');
        Route::get('/{faq}/edit', [AboutFaqController::class, 'edit'])->name('edit');
        Route::put('/{faq}', [AboutFaqController::class, 'update'])->name('update');
        Route::delete('/{faq}', [AboutFaqController::class, 'archive'])->name('archive');
        Route::patch('/{faq}/restore', [AboutFaqController::class, 'restore'])->withTrashed()->name('restore');
    });

    Route::middleware(['auth', 'admin.permission:reviews.moderate'])->prefix('reviews')->name('reviews.')->group(function (): void {
        Route::get('/', [ReviewModerationController::class, 'reviews'])->name('index');
        Route::patch('/{review}/moderation', [ReviewModerationController::class, 'decideReview'])->name('moderation');
        Route::patch('/{review}/visibility', [ReviewModerationController::class, 'reviewVisibility'])->name('visibility');
    });

    Route::middleware(['auth', 'admin.permission:feedback.moderate'])->prefix('feedback')->name('feedback.')->group(function (): void {
        Route::get('/', [ReviewModerationController::class, 'feedback'])->name('index');
        Route::patch('/{feedback}/moderation', [ReviewModerationController::class, 'decideFeedback'])->name('moderation');
        Route::patch('/{feedback}/visibility', [ReviewModerationController::class, 'feedbackVisibility'])->name('visibility');
    });
});
