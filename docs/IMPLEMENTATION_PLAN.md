# HS Shop implementation plan

This document retains the lifecycle overview. The task-by-task two-sprint backlog and acceptance criteria are maintained in [SPRINT_PLAN.md](SPRINT_PLAN.md). Follow that plan for sequencing; this file gives the phase-level dependencies and release gates. Implement only the approved MVP subset. Supplier procurement, inventory and supplier-account reconciliation are outside the current scope; automated carrier integrations are deferred.

## Current execution state

The owner authorized schema implementation and an administrator seeder; the MVP schema is installed and one active seeded administrator exists. The first feature slice (admin login, dashboard home and logout) is implemented, with route registration and Blade compilation verified. Continue with the remaining MVP dashboard operations, keeping procurement, inventory, carrier settlement reconciliation, cash sessions, and other deferred modules excluded.

## Phase 0 — Confirm implementation gates

- MySQL is the approved target. The local app is configured for MySQL 8.0.30. `.env.example` now matches MySQL; deployment credentials remain environment-specific.
- The markup percentage basis (markup on cost), four-decimal half-up percentage rounding, and manual per-unit advertising input are approved. The administrator enters the regular and sale prices manually; the rate is informational and does not generate the regular price. Shared campaign-budget allocation remains open.
- Confirm minimum return/refund treatment and when orders count as completed in reporting. Leave tax/accounting policies unimplemented until approved.
- Define the minimum staff role/permission matrix and who may verify InstaPay or close/refund orders.

**Exit:** the affected business policies are documented as approved, or the dependent behavior is kept manual/disabled. MySQL configuration and server verification are already complete.

## Phase 1 — Application foundation and access

- Configure localization for Arabic/English and RTL/LTR; prepare safe localized validation and error handling.
- Add customer authentication and dashboard session authentication with server-side authorization and role/permission controls.
- **Complete:** the approved MVP schema is installed. Do not rerun destructive schema setup or add deferred modules. Build the application workflows on the installed schema; request a separate schema review if implementation exposes a missing field/table.
- Establish secure private storage for uploads and relevant audit logging.

**Exit:** a customer and authorized staff member can authenticate safely; existing migrations stay applied; access checks and error paths are verified.

## Phase 2 — Catalog and storefront

- **Superseded by owner direction:** the initial eMarket page mapping was replaced with the Rapidshop storefront direction. Active storefront pages use the shared Rapidshop Blade layout and Bootstrap 5; `emarket/` remains a static reference only. Remove old demo routes rather than exposing template sample content.
- Implement categories, products/variants, images, regular and optional sale prices with history, product-level available/unavailable status, supplier options and per-order supplier choice, simple product Favorites, curated new/popular lists, dashboard-managed homepage sections and imagery (visibility, order and localized headings), About page FAQs, moderated product reviews and general customer feedback.
- Implement product details, search/category navigation as needed, anonymous cart, and account registration/login.

**Exit:** owner can manage catalog content and customers can browse current available products in Arabic/English.

## Phase 3 — Checkout and order lifecycle

- Allow guests to browse, add to cart and view checkout; preserve cart/checkout choices through registration/login and require authentication only at final order placement. Place authenticated COD orders with immutable product, regular/sale price, charged amount, quantity, shipping and address snapshots.
- Show regular and optional sale prices in product listings/details, and snapshot both plus the charged price/discount in order revisions. Keep automatic sale scheduling off until its policy is approved.
- Add customer order history/details and explicit revision/approval handling for changed price/product/quantity.
- Provide permission-gated order search, detail and immutable event timeline; assignment, notes and follow-up are append-only events. Implement the owner-approved sequential order state machine, failure reasons, customer-receipt confirmation for success, and reopening to the pre-closure status. Payment and shipment states remain independent.
- **Implemented (S2-06):** Add staff-entered Facebook/Instagram orders with immutable order snapshots, COD or InstaPay, private transfer-proof upload/authorized viewing, accept/reject review, rejection reason, replacement proof after rejection and audit events. InstaPay remains unavailable on website checkout.

**Exit:** website and social orders can be created, followed and closed with durable audit history and no current-catalog mutation of old orders.

## Phase 4 — Manual fulfillment and financial facts

- **Implemented (S2-07):** Record manual carriers, shipment attempts and delivery events, actual carrier costs, customer shipping allocations, COD expected/actual collection, and shipment timestamps. Shipment events remain independent of order/payment state; carrier settlement and customer liability decisions are deferred.
- Track return/exchange/damage cases and related refunds or write-offs under approved policy.
- Record per-order product cost, inbound delivery, packaging, advertising and other approved direct costs; clearly distinguish estimates from actuals.

**Exit:** staff can complete the order-to-delivery/return process and reconcile visible cost facts manually.

## Phase 5 — Dashboard operations, support and reporting

- **Implemented (S2-10):** Cairo-day dashboard metrics and permission-scoped order, delivered, failed, return and ticket queues; weekly/monthly/yearly reports with explicit date bases and raw order, direct-cost, return, refund, COD and overhead amounts.
- **Implemented (S2-09):** Contact Us inbox conversion to support tickets, customer-owned ticket creation/history, public replies, staff-only notes and attachments, assignment/status audit events, and saved in-dashboard notifications. No email/SMS or realtime delivery is enabled.
- Reporting uses the applied order revision snapshot, separates actual and estimated direct costs, and labels contribution as a provisional estimate. Ratios and accounting profit remain blocked on owner/accountant decisions in `docs/OPEN_DECISIONS.md`.

**Exit:** owner can operate the MVP from the dashboard and inspect sales, returns, damage and cost components with traceable source records.

## Phase 6 — Release readiness

- **Source review (S2-11, 2026-11-06):** admin route groups are permission-gated server-side; customer account/order/address/ticket/notification reads are scoped to the authenticated customer; social payment proofs and support attachments use private storage and authorized download paths. CSRF is provided by Laravel's web middleware, sensitive forms have throttles, uploaded images/documents have MIME/size validation, and checkout/social order/return/cost workflows use transactions and idempotency/locking where implemented. Bilingual safe HTTP error pages and framework validation messages were added, and an unsafe external locale-switch return was rejected.
- **Still required before production:** run migrations and transactional/concurrency acceptance checks against MySQL, set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL` and `SESSION_SECURE_COOKIE=true`; verify proxy/TLS/security headers; configure database and private/public storage backups and test restore; complete staging acceptance on the owner-approved phone/browser matrix.
- **Feature blocker:** customers cannot currently submit product ratings/reviews; only staff moderation/visibility routes exist. `docs/OPEN_DECISIONS.md` intentionally blocks this endpoint until rating scale and purchase eligibility are approved. Complete customer acceptance coverage for cart, checkout, reviews and account journeys before marking release ready.
- Review open decisions and known risks; do not enable deferred integrations without a separate approved scope.

**Exit:** acceptance criteria and relevant checks pass; outstanding limitations are documented before production release.
