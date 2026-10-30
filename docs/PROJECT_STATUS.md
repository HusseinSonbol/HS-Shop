# HS Shop project status

**Last reviewed:** 2026-10-29  
**Current phase:** Sprint 1 implementation  
**Implementation state:** MVP database migrations, bilingual admin login/home/logout, S1-02 staff/role management, S1-03 customer/address management, S1-04 category management, S1-05 supplier/offer management, S1-06 product/variant/image/availability management, and S1-07 manual price versioning are implemented. Storefront and remaining dashboard workflows are unimplemented.

## Verified repository baseline

- PHP constraint: `^8.2`; Laravel Framework constraint: `^12.0` (`composer.json`). `php artisan --version` reports Laravel Framework `12.69.3` in this environment. Runtime packages declared: Laravel Framework and Tinker. Development packages include Faker, Laravel Pail/Pint/Sail, Mockery and PHPUnit. No shop, RBAC, payment or shipping integration package is declared.
- The frontend uses npm-managed Bootstrap 5.3.8 and Popper 2.11.8; the admin loads the matching Bootstrap RTL build for Arabic and the standard build for English. Vite builds the local assets; `emarket/` itself contains legacy Bootstrap 3.3.5 and is treated as a visual template only. The dashboard includes permission-scoped quick summaries and links for customers, suppliers and visible categories.
- The target architecture is a Laravel/Blade modular monolith with MySQL, Arabic/English and EGP. The configured application connection was read-only checked and reports MySQL server 8.0.30. The selected database had no tables before implementation began. `.env.example` now selects MySQL.
- Current app retains the Laravel starter landing route/view and infrastructure migrations. Three new migrations install HS Shop identity/catalog/supplier directory, commerce/order/payment/manual shipment/returns/costs, and content/reviews/support/audit tables. `User` includes soft deletion, locale, active state, phone and permission lookup. Admin login, the initial dashboard, authorization middleware, locale switch and safe logout are implemented; no catalog/order/support workflows exist yet.
- `tests/Feature/AdminAccessTest.php` verifies guest redirection, active/inactive and permission checks, bilingual RTL/LTR rendering, and POST-only logout. It passes (5 tests, 38 assertions) using the configured in-memory SQLite test connection.
- `tests/Feature/AdminStaffManagementTest.php` verifies permission-gated staff routes, list/forms, create/update/role assignment, activation/deactivation/archive, immediate session rejection after account deactivation, protection of the last active super administrator, the seeded permission matrix, and bilingual locale switching that preserves the current admin page. Staff changes are recorded in `activity_logs` without storing passwords or contact data in the audit payload.
- `tests/Feature/AdminCustomerManagementTest.php` verifies customer authorization, search and CRUD, Arabic-digit phone normalization, address validation and customer scoping, default-address replacement, archival/restoration, and order-history preservation. Full `php artisan test` passes (20 tests, 158 assertions); Pint passes on the files changed for these stories.
- `tests/Feature/AdminCategoryManagementTest.php` verifies permission gating, bilingual hierarchical CRUD/search, event audit logging, self/descendant cycle rejection, active-parent validation, child-first hiding/archival, restore, Bootstrap 5 form classes, and archived-category exclusion from future selection queries.
- `tests/Feature/AdminSupplierManagementTest.php` verifies supplier permission gating, supplier CRUD/archive/restore, product-to-multiple-supplier links, precise decimal offer history with actor/time/reason, no-op history behavior, duplicate protection, and offer-date validation. `tests/Feature/AdminProductManagementTest.php` verifies catalog authorization, simple/variable products, generated group and variant SKUs/slugs, manual per-variant prices and immutable price versions, cost input capture and calculated markup percentage, zero-cost validation, image uploads and primary-image selection, translated attributes, nested category links, unique variant combinations, availability history, archival/restoration and validation. Full `php artisan test` passes (41 tests, 364 assertions); Pint passes on changed PHP files; JavaScript syntax check and Vite production build succeed.
- `emarket/` contains static HTML/CSS/JavaScript. Approved design starting points are `index.html`, `category.html`, `product-v2.html` and `about-us-4.html`; these have not been converted to Blade. There is no prebuilt admin theme; the first HS Shop admin shell is implemented.
- `Color_identity/` and `logos/` contain visual assets/references.

## Requirements and decisions already confirmed

- Public storefront browsing and anonymous cart; customer account required to place a website order; website checkout payment is COD.
- Staff can enter Facebook/Instagram orders and record InstaPay evidence/status for those orders only.
- Products may have multiple factory/showroom supplier options; staff selects one per order line. Product availability is manually set to available/unavailable. Procurement and inventory are outside scope.
- Product family SKU is distinct from each automatically generated sellable variant SKU. Catalog regular/sale prices are entered manually per variant. S1-07 calculates markup on cost to four decimal percentage places from the regular price and the four unit costs; this percentage does not calculate the regular price. The first price version may omit a reason; later changes require one.
- Manual categories/products, manually curated new/popular selections, dashboard control of homepage sections and images, admin-controlled product rating visibility and separately moderated/visible customer feedback, Contact Us and support tickets are required.
- Product variants have a regular price and optional discounted sale price, with both preserved in price history/order snapshots; staff manage About Us FAQ questions/answers; simple product Favorites use a heart toggle. Product comparison, newsletter signup and blog are excluded from the MVP.
- Order creator and order lifecycle events must be auditable; preserve immutable order/product/price snapshots. Track returns, damage and shipping costs manually.
- Pricing inputs are factory, inbound delivery, packaging and advertising per-unit costs; calculate the markup percentage from the manually entered regular price and cost total. Customer shipping is separate. Preserve each price version.
- Reporting timezone is `Africa/Cairo`; month starts day 1; week runs Saturday-Friday. Dashboard ticket notifications are stored/in-app, without realtime requirement.
- Supplier procurement, inventory and supplier-account reconciliation are outside current scope. Automated carrier integrations are deferred.

## Current deliverables

- Root `AGENTS.md` project build/security/testing instructions.
- Database design reference in `docs/database/` and unresolved policies in `docs/OPEN_DECISIONS.md`.
- This status file, implementation plan, and known risk register.

## Not complete

Storefront Blade conversion, customer authentication/checkout, content CRUD, order operations, reports, and production deployment remain unimplemented. The current admin login/overview is still a starting shell; staff, customer/address, category, supplier/offer and product/variant management are implemented dashboard modules.

## Current next step

S1-01 through S1-07 are implemented. Categories support a manually ordered tree, bilingual names, visibility, soft archival/restoration and cycle prevention. Suppliers support factory/showroom records, separate owner/contact details, manually entered address fields, and multiple product offers with versioned cost, availability and validity history. Products support family SKU, automatically generated sellable variant SKUs, bilingual details, size/color attributes, images, per-variant manually entered regular/sale prices, publication state, manually checked availability history, and immutable price versions with four cost inputs and a calculated markup rate plus actor/reason/time. The first price-version reason is optional; later versions require a reason. Storefront display of both prices is part of S1-10. No automatic regular-price calculation is enabled. Supplier procurement, purchasing, receipt, and inventory remain out of scope. The updated `DatabaseSeeder` ran successfully in the configured local environment and installed the approved `manager` and `operator` roles and permission assignments. Migrations completed successfully, creating 78 tables total including Laravel infrastructure. Never store the administrator password in source control or expose it in logs.
