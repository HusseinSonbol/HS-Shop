# HS Shop database design

**Status:** MVP database implementation in progress. This document records approved business requirements and the agreed schema; unresolved policies remain open and must not be inferred.

## Approved requirements (from the business brief)

MySQL; Laravel/Blade modular monolith; Arabic and English; EGP default; customer account required before order placement (guests may browse, cart and view checkout); Laravel web-session admin authentication; simple/variable products; multiple factory/showroom suppliers per product with per-order-line supplier selection; no procurement/inventory modules; manual shipping first with future integrations; DECIMAL financial values; complete historical snapshots; explicit customer approval for changed order prices/products/quantities; independent order/payment/shipment lifecycles; preserve financial and operational audit history; use transactions and row locks for concurrency; never hard-delete historical operational or financial records.

Tax/legal/accounting/refund/retention rules are not approved by these requirements. Defaults below are proposals only.

## Confirmed launch workflow (latest clarifications)

- Each product may link to multiple suppliers (factory/showroom); staff selects one for each order line. Product availability is manually set to available/unavailable, separate from inventory. Supplier selection does not create procurement or inventory records.
- Visitors may browse, add products/variant selections to a guest cart, and open checkout. Authentication/registration is required only before order placement; preserve guest cart and checkout choices through authentication, attach the cart to the customer account, and return to checkout.
- An authorized administrator controls regular and sale-price changes. Prices and optional discounts are set per sellable variant (including size); every change records actor/time and preserves the former version. The initial version may omit a reason; subsequent changes require one.
- Website checkout offers cash on delivery only. InstaPay is unavailable on the website; staff may select it when manually entering a Facebook/Instagram order. Keep the transfer image and verification status, and verify before shipment.
- Product reviews contain a rating and comment, wait for dashboard moderation, and are public only after approval.
- Customers can submit Contact Us messages and open support tickets; staff assignment, replies, internal notes and ticket status history are tracked, with public and internal messages distinguished.
- Provide weekly, monthly and annual sales/returns/damage/profit breakdowns from immutable order snapshots and financial records; historical reports never use current catalog prices.
- Profit percentage is automatically calculated as markup on the entered regular price against the sum of factory, inbound delivery, packaging and advertising per-unit costs, rounded half-up to four decimal places. The regular price itself remains manually set; customer shipping is separate. Preserve every price version and charge actual return-to-sender/failed-delivery carrier costs separately.
- Reporting calendar uses `Africa/Cairo`, months from day 1 and weeks Saturday through Friday. Dashboard statistics include today's orders and actionable delivery, payment, returns and support queues.
- Support ticket notifications are stored and shown inside the dashboard when opened; real-time push is not required.
- Dashboard staff manage homepage banners and predefined homepage sections, including visibility, order and translated headings/content. Admins separately moderate and control the visibility of general customer feedback and product ratings/reviews in the dashboard.
- Record who created each order and append an immutable order event for every status change, follow-up, assignment, closure and reopening. A closure records success/failure and a reason; defect exchanges are linked service cases rather than failed-delivery closures.
- Track order costs for product acquisition, shipping, packaging and advertising. Product cost remains separate from operating expenses. Do not spread general advertising spend across orders without an approved allocation rule.

## Repository baseline
- `composer.json` declares Laravel Framework `^12.0`, PHP `^8.2`; `php artisan --version` reports Laravel Framework `12.69.3`. Direct runtime packages are Laravel Framework and Tinker. No commerce, RBAC, payment or carrier package is declared.
- The configured Laravel connection reported MySQL server `8.0.30` on a read-only check; the selected database had no tables before schema implementation. `.env.example` now uses MySQL and leaves administrator credentials environment-only.
- Existing migrations provide `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, and `failed_jobs`. The sole model is a basic Laravel Authenticatable `User`; the only route returns the welcome view. No store schema exists.
- `database/database.sqlite` is an existing local SQLite file and is not the configured MySQL target. The implementation preserves the existing Laravel starter migrations/model as baseline.
- `Color_identity/` contains visual-reference HTML; it does not define data schema.

## Conventions and modules
Modular monolith: identity/access, catalog/localization and supplier directory, commerce, payments/fulfillment, finance, marketing/CMS, audit, Laravel infrastructure. Use InnoDB and utf8mb4. New IDs are BIGINT UNSIGNED with matching FKs; public identifiers may additionally use UUID/ULID. Instants are UTC DATETIME(6). Statuses are VARCHAR, not MySQL ENUM. Money is DECIMAL(19,4) + CHAR(3) currency; EGP default. Quantity is DECIMAL(18,3). Never use FLOAT/DOUBLE for money. Translation tables are unique per entity/locale (ar/en). Snapshot customer-facing details and assigned supplier identity on order revisions. Master data may be archived; operational and financial history is append-only/reversed, never hard-deleted.

See [ERD](ERD.md), [table dictionary](TABLES.md), [constraints/indexes](CONSTRAINTS_AND_INDEXES.md), [workflows](WORKFLOWS.md), and [open decisions](../OPEN_DECISIONS.md). Recommendations are not approved policies. Implementation is limited to the approved MVP slice.

For the selected eMarket page-by-page feature mapping, MVP/defer recommendations, and current repository schema status, see [eMarket feature review](EMARKET_FEATURE_REVIEW.md). Template controls are visual references, not automatically approved features.

## Suggested implementation boundary

### Approved storefront template selection
- Homepage: `emarket/index.html`
- Category listing: `emarket/category.html`
- Product detail: `emarket/product-v2.html`
- About page: `emarket/about-us-4.html`

These resolve the duplicated visual alternatives: use these choices for the homepage, category listing, product detail, and About page. The other relevant, non-duplicate pages in `emarket/` remain in scope for the storefront and account/order flows, except features explicitly deferred below. The template files remain static until deliberately converted into Blade views.

**MVP schema:** users/RBAC and customer profiles; hierarchical categories, products/variants, images/translations, manual availability, administrator-managed regular and optional sale prices per variant with history, curated product collections, and simple product Favorites; anonymous cart and checkout view with preserved guest choices, followed by required authentication before order placement (COD); staff-entered social orders (COD or verified InstaPay proof); order snapshots and approval revisions; supplier directory, product-supplier options and per-order-line supplier selection; manual shipment status; separate moderated product reviews and general customer feedback; dashboard control of homepage sections, banners, curated collections, and About FAQs; Contact Us and support tickets; direct per-order product and operating cost records; weekly/monthly/yearly sales, return, damage and order-cost reports. Supplier procurement and inventory are outside scope.

**Current scope:** supplier directory, multiple supplier options per product, per-order-line supplier selection, and manually controlled product availability. Supplier-product cost/availability options are historized, but there is no purchase-order or quote-comparison workflow. Supplier procurement, purchase orders, goods receipts, supplier-account reconciliation, warehouses and inventory are outside scope. Automated carrier integrations remain deferred. Returns/damage, shipment status/cost and order financial history remain recorded.

**Installed MVP schema:** three new migrations install identity/RBAC, customer profiles/addresses, catalog/variants/prices/suppliers, carts/favorites/orders/revisions/approvals, COD and social InstaPay proof, manual shipments, returns/refunds, expenses/order costs, homepage/CMS/FAQs, reviews/feedback, contact/tickets/notifications, and audit records. The configured MySQL database now has 78 tables including Laravel infrastructure. Carrier settlements/remittances, cash sessions, coupons, procurement, and inventory remain absent by design. The administrator seeder is ready but requires owner-provided `HS_SHOP_ADMIN_EMAIL` and `HS_SHOP_ADMIN_PASSWORD` values.

**Confirmed template exclusions for the MVP:** product comparison, newsletter signup, and blog. Also defer coupons, quick view, full multi-list Wishlist, arbitrary/free-form homepage page builder, advanced CMS, external/customer notification delivery, and online payment gateways. Simple product Favorites are in scope. In-dashboard stored notifications for support tickets remain in scope.

The storefront availability flag records a manual supplier check, not a stock quantity. The supplier assignment is catalog metadata; supplier ordering and fulfillment are managed outside the application.

## Review corrections and rationale

- Replaced an opaque revision JSON-only approach with normalized immutable revision lines and address snapshots, explicit revision states/timestamps, and decisions tied to the exact revision ID. Stable order-line identities avoid duplicating mutable product/price facts.
- Split customer shipping charges, carrier transport cost, expected/actual COD, fees/deductions, remitted amounts and explained/unexplained variances; added partial remittance allocation and append-only settlement corrections.
- Restricted payment methods by order channel: website COD only, staff-entered social InstaPay with proof and verification; added product-review moderation and manual product collections for “new” and “popular”.
- Added auditable order-cost entries for product, shipping, packaging and advertising, while keeping shared advertising unattributed until an allocation policy is approved.
- Added manual homepage banner management and an order-specific immutable event stream so staff can identify who created, followed, changed and closed each order with its reason.
- Documented cross-table rules, polymorphic references, indexes and unresolved policy choices in [constraints](CONSTRAINTS_AND_INDEXES.md) and [open decisions](../OPEN_DECISIONS.md).
