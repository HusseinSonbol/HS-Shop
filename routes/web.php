<?php

use App\Http\Controllers\Admin\AboutFaqController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CustomerAddressController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomepageContentController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductSupplierController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReturnCaseController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ShippingCompanyController;
use App\Http\Controllers\Admin\ShippingRateController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Storefront\AboutController;
use App\Http\Controllers\Storefront\CartController as StorefrontCartController;
use App\Http\Controllers\Storefront\CartItemController as StorefrontCartItemController;
use App\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use App\Http\Controllers\Storefront\CheckoutController as StorefrontCheckoutController;
use App\Http\Controllers\Storefront\ContactMessageController as StorefrontContactMessageController;
use App\Http\Controllers\Storefront\CustomerAccountController;
use App\Http\Controllers\Storefront\CustomerFeedbackSubmissionController;
use App\Http\Controllers\Storefront\CustomerNotificationController;
use App\Http\Controllers\Storefront\CustomerOrderController;
use App\Http\Controllers\Storefront\CustomerProfileController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Storefront\ProductFavoriteController;
use App\Http\Controllers\Storefront\ProductReviewSubmissionController;
use App\Http\Controllers\Storefront\SupportTicketController as StorefrontSupportTicketController;
use App\Http\Controllers\Storefront\WebsiteOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('store.home');
Route::get('/index.html', HomeController::class);
Route::get('/shop', [StorefrontCategoryController::class, 'index'])->name('store.shop');
Route::get('/category.html', [StorefrontCategoryController::class, 'index']);
Route::get('/category/{slug?}', [StorefrontCategoryController::class, 'index'])->name('store.category');
Route::get('/product/{slug?}', [StorefrontProductController::class, 'show'])->name('store.product');
Route::post('/customer-feedback', [CustomerFeedbackSubmissionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('store.customer-feedback.store');
Route::get('/cart', [StorefrontCartController::class, 'index'])->name('store.cart.index');
Route::post('/cart/items', [StorefrontCartItemController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('store.cart.items.store');
Route::patch('/cart/items/{cartItem}', [StorefrontCartController::class, 'update'])
    ->whereNumber('cartItem')
    ->middleware('throttle:30,1')
    ->name('store.cart.items.update');
Route::delete('/cart/items/{cartItem}', [StorefrontCartController::class, 'destroy'])
    ->whereNumber('cartItem')
    ->middleware('throttle:30,1')
    ->name('store.cart.items.destroy');
Route::get('/checkout', [StorefrontCheckoutController::class, 'show'])->name('store.checkout');
Route::middleware('auth:customer')->group(function (): void {
    Route::post('/product/{slug}/reviews', [ProductReviewSubmissionController::class, 'store'])
        ->where('slug', '[A-Za-z0-9-]+')
        ->middleware('throttle:5,1')
        ->name('store.product-reviews.store');
    Route::get('/account/orders', [CustomerOrderController::class, 'index'])->name('store.account.orders.index');
    Route::get('/account/orders/{publicId}', [CustomerOrderController::class, 'show'])
        ->where('publicId', '[0-9a-fA-F-]{36}')
        ->name('store.account.orders.show');
    Route::post('/checkout/place-order', [WebsiteOrderController::class, 'place'])
        ->middleware('throttle:10,1')
        ->name('store.checkout.place');
    Route::get('/orders/{publicId}/confirmation', [WebsiteOrderController::class, 'confirmation'])
        ->where('publicId', '[0-9a-fA-F-]{36}')
        ->name('store.orders.confirmation');
    Route::get('/account/tickets', [StorefrontSupportTicketController::class, 'index'])->name('store.account.tickets.index');
    Route::get('/account/tickets/create', [StorefrontSupportTicketController::class, 'create'])->name('store.account.tickets.create');
    Route::post('/account/tickets', [StorefrontSupportTicketController::class, 'store'])->middleware('throttle:10,1')->name('store.account.tickets.store');
    Route::get('/account/tickets/{ticket}', [StorefrontSupportTicketController::class, 'show'])->whereNumber('ticket')->name('store.account.tickets.show');
    Route::post('/account/tickets/{ticket}/messages', [StorefrontSupportTicketController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:10,1')->name('store.account.tickets.reply');
    Route::get('/account/tickets/{ticket}/attachments/{attachment}', [StorefrontSupportTicketController::class, 'attachment'])->whereNumber(['ticket', 'attachment'])->name('store.account.tickets.attachments.show');
    Route::get('/account/notifications', [CustomerNotificationController::class, 'index'])->name('store.account.notifications.index');
    Route::post('/account/notifications/{notification}/read', [CustomerNotificationController::class, 'markRead'])->name('store.account.notifications.read');
});
Route::get('/account/login', [CustomerAccountController::class, 'createLogin'])->name('store.account.login');
Route::post('/account/login', [CustomerAccountController::class, 'storeLogin'])
    ->middleware('throttle:10,1')
    ->name('store.account.login.store');
Route::get('/account/register', [CustomerAccountController::class, 'createRegistration'])->name('store.account.register');
Route::post('/account/register', [CustomerAccountController::class, 'storeRegistration'])
    ->middleware('throttle:5,10')
    ->name('store.account.register.store');
Route::middleware('auth:customer')->prefix('account')->name('store.account.')->group(function (): void {
    Route::get('/', [CustomerProfileController::class, 'index'])->name('index');
    Route::patch('/profile', [CustomerProfileController::class, 'updateProfile'])->middleware('throttle:20,1')->name('profile.update');
    Route::get('/addresses/create', [CustomerProfileController::class, 'createAddress'])->name('addresses.create');
    Route::post('/addresses', [CustomerProfileController::class, 'storeAddress'])->middleware('throttle:20,1')->name('addresses.store');
    Route::get('/addresses/{address}/edit', [CustomerProfileController::class, 'editAddress'])->whereNumber('address')->name('addresses.edit');
    Route::put('/addresses/{address}', [CustomerProfileController::class, 'updateAddress'])->whereNumber('address')->middleware('throttle:20,1')->name('addresses.update');
    Route::delete('/addresses/{address}', [CustomerProfileController::class, 'archiveAddress'])->whereNumber('address')->middleware('throttle:20,1')->name('addresses.archive');
});
Route::post('/account/logout', [CustomerAccountController::class, 'destroy'])->name('store.account.logout');
Route::post('/product/{product:slug}/favorite', [ProductFavoriteController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('store.favorites.update');
Route::get('/about-us', AboutController::class)->name('store.about');
Route::get('/about-us-4.html', AboutController::class);

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

    Route::middleware(['auth', 'admin.permission:orders.manage'])->prefix('orders')->name('orders.')->group(function (): void {
        Route::get('/', [AdminOrderController::class, 'index'])->name('index');
        Route::get('/create-social', [AdminOrderController::class, 'createSocial'])->name('create-social');
        Route::post('/social', [AdminOrderController::class, 'storeSocial'])->middleware('throttle:20,1')->name('store-social');
        Route::post('/{order}/payment-proofs', [AdminOrderController::class, 'uploadPaymentProof'])
            ->whereNumber('order')->middleware('throttle:10,1')->name('payment-proofs.store');
        Route::get('/{order}/payment-proofs/{proof}', [AdminOrderController::class, 'showPaymentProof'])
            ->whereNumber(['order', 'proof'])->name('payment-proofs.show');
        Route::post('/{order}/payment-proofs/{proof}/review', [AdminOrderController::class, 'reviewPaymentProof'])
            ->whereNumber(['order', 'proof'])->middleware('throttle:20,1')->name('payment-proofs.review');
        Route::get('/{order}', [AdminOrderController::class, 'show'])->whereNumber('order')->name('show');
        Route::post('/{order}/events', [AdminOrderController::class, 'storeEvent'])->whereNumber('order')->name('events.store');
    });

    Route::middleware(['auth', 'admin.permission:returns.manage'])->prefix('returns')->name('returns.')->group(function (): void {
        Route::get('/', [ReturnCaseController::class, 'index'])->name('index');
        Route::get('/orders/{order}/create', [ReturnCaseController::class, 'create'])->whereNumber('order')->name('order-create');
        Route::get('/{return}', [ReturnCaseController::class, 'show'])->whereNumber('return')->name('show');
        Route::post('/orders/{order}', [ReturnCaseController::class, 'store'])->whereNumber('order')->name('store');
        Route::patch('/{return}/status', [ReturnCaseController::class, 'transition'])->whereNumber('return')->name('status');
        Route::post('/{return}/shipments', [ReturnCaseController::class, 'shipment'])->whereNumber('return')->middleware('throttle:20,1')->name('shipments.store');
        Route::post('/{return}/shipments/{shipment}/events', [ReturnCaseController::class, 'shipmentEvent'])->whereNumber(['return', 'shipment'])->middleware('throttle:30,1')->name('shipments.events.store');
        Route::post('/{return}/refunds', [ReturnCaseController::class, 'refund'])->whereNumber('return')->name('refunds.store');
        Route::patch('/{return}/refunds/{refund}/paid', [ReturnCaseController::class, 'markRefundPaid'])->whereNumber(['return', 'refund'])->name('refunds.paid');
        Route::patch('/{return}/refunds/{refund}/void', [ReturnCaseController::class, 'voidRefund'])->whereNumber(['return', 'refund'])->name('refunds.void');
    });

    Route::middleware(['auth', 'admin.permission:expenses.manage'])->post('/orders/{order}/costs', [ReturnCaseController::class, 'storeCost'])
        ->whereNumber('order')->name('orders.costs.store');
    Route::middleware(['auth', 'admin.permission:expenses.manage'])->post('/orders/{order}/costs/{entry}/reverse', [ReturnCaseController::class, 'reverseCost'])
        ->whereNumber(['order', 'entry'])->name('orders.costs.reverse');

    Route::middleware(['auth', 'admin.permission:shipments.manage'])->group(function (): void {
        Route::get('/shipping-companies', [ShippingCompanyController::class, 'index'])->name('shipping-companies.index');
        Route::post('/shipping-companies', [ShippingCompanyController::class, 'store'])->name('shipping-companies.store');
        Route::put('/shipping-companies/{company}', [ShippingCompanyController::class, 'update'])->whereNumber('company')->name('shipping-companies.update');
        Route::patch('/shipping-companies/{company}/active', [ShippingCompanyController::class, 'toggle'])->whereNumber('company')->name('shipping-companies.active');
        Route::post('/orders/{order}/shipments', [ShipmentController::class, 'store'])->whereNumber('order')->middleware('throttle:20,1')->name('orders.shipments.store');
        Route::post('/orders/{order}/shipments/{shipment}/events', [ShipmentController::class, 'storeEvent'])->whereNumber(['order', 'shipment'])->middleware('throttle:30,1')->name('orders.shipments.events.store');
    });

    Route::middleware(['auth', 'admin.permission:contact_messages.manage'])->prefix('contact-messages')->name('contact-messages.')->group(function (): void {
        Route::get('/', [AdminContactMessageController::class, 'index'])->name('index');
        Route::get('/{contactMessage}', [AdminContactMessageController::class, 'show'])->whereNumber('contactMessage')->name('show');
        Route::patch('/{contactMessage}/status', [AdminContactMessageController::class, 'updateStatus'])->whereNumber('contactMessage')->name('status');
    });

    Route::middleware(['auth', 'admin.permission:support_tickets.manage'])->prefix('support-tickets')->name('support-tickets.')->group(function (): void {
        Route::get('/', [AdminSupportTicketController::class, 'index'])->name('index');
        Route::get('/{ticket}', [AdminSupportTicketController::class, 'show'])->whereNumber('ticket')->name('show');
        Route::post('/from-contact/{contactMessage}', [AdminSupportTicketController::class, 'convertContact'])->middleware('admin.permission:contact_messages.manage')->whereNumber('contactMessage')->name('convert-contact');
        Route::patch('/{ticket}/assignment', [AdminSupportTicketController::class, 'assign'])->whereNumber('ticket')->name('assignment');
        Route::patch('/{ticket}/status', [AdminSupportTicketController::class, 'updateStatus'])->whereNumber('ticket')->name('status');
        Route::post('/{ticket}/messages', [AdminSupportTicketController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:20,1')->name('messages.store');
        Route::get('/{ticket}/attachments/{attachment}', [AdminSupportTicketController::class, 'attachment'])->whereNumber(['ticket', 'attachment'])->name('attachments.show');
    });
    Route::middleware(['auth', 'admin.permission:dashboard.access'])->prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [AdminNotificationController::class, 'index'])->name('index');
        Route::post('/{notification}/read', [AdminNotificationController::class, 'markRead'])->name('read');
    });

    Route::middleware(['auth', 'admin.permission:settings.manage'])->prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/shipping-rates', [ShippingRateController::class, 'edit'])->name('shipping-rates.edit');
        Route::put('/shipping-rates', [ShippingRateController::class, 'update'])->middleware('throttle:20,1')->name('shipping-rates.update');
    });

    Route::middleware(['auth', 'admin.permission:reports.view'])->get('/reports', [ReportController::class, 'index'])->name('reports.index');

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
