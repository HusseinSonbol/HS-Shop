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

- Convert the selected `emarket/index.html`, `category.html`, `product-v2.html`, and `about-us-4.html` as the chosen alternatives for those page types. Build the other relevant, non-duplicate storefront and account pages available in `emarket/` (including cart, checkout, login/register, account, and order history/details) as needed for approved workflows. Exclude only explicitly deferred features and demo-only controls.
- Implement categories, products/variants, images, regular and optional sale prices with history, product-level available/unavailable status, supplier options and per-order supplier choice, simple product Favorites, curated new/popular lists, dashboard-managed homepage sections and imagery (visibility, order and localized headings), About page FAQs, moderated product reviews and general customer feedback.
- Implement product details, search/category navigation as needed, anonymous cart, and account registration/login.

**Exit:** owner can manage catalog content and customers can browse current available products in Arabic/English.

## Phase 3 — Checkout and order lifecycle

- Allow guests to browse, add to cart and view checkout; preserve cart/checkout choices through registration/login and require authentication only at final order placement. Place authenticated COD orders with immutable product, regular/sale price, charged amount, quantity, shipping and address snapshots.
- Show regular and optional sale prices in product listings/details, and snapshot both plus the charged price/discount in order revisions. Keep automatic sale scheduling off until its policy is approved.
- Add customer order history/details and explicit revision/approval handling for changed price/product/quantity.
- Add order activity events for creation, status changes, follow-up, closure/reopening and reasons.
- Add manual supplier availability checks, staff-entered social orders, and InstaPay proof capture/review for social channels only.

**Exit:** website and social orders can be created, followed and closed with durable audit history and no current-catalog mutation of old orders.

## Phase 4 — Manual fulfillment and financial facts

- Record manual shipment/delivery events and actual carrier costs, including undelivered/return-to-sender legs.
- Track return/exchange/damage cases and related refunds or write-offs under approved policy.
- Record per-order product cost, inbound delivery, packaging, advertising and other approved direct costs; clearly distinguish estimates from actuals.

**Exit:** staff can complete the order-to-delivery/return process and reconcile visible cost facts manually.

## Phase 5 — Dashboard operations, support and reporting

- Build dashboard statistics and drill-down lists for today's orders and actionable order/payment/shipping/return/ticket queues.
- Add separate admin approval/rejection and visibility controls for product ratings/reviews and general customer feedback; add homepage section/banner controls, Contact Us inbox, support tickets, customer-visible replies/internal notes, and stored in-dashboard notifications.
- Add weekly/monthly/yearly reports using Cairo timezone, day-1 months and Saturday-Friday weeks; label date basis and estimates explicitly.

**Exit:** owner can operate the MVP from the dashboard and inspect sales, returns, damage and cost components with traceable source records.

## Phase 6 — Release readiness

- Complete workflow/security regression coverage, review upload/download authorization and private storage, and inspect logs for sensitive data exposure.
- Verify production environment configuration, backups, deployment settings, HTTPS/session settings, and recovery procedures.
- Review open decisions and known risks; do not enable deferred integrations without a separate approved scope.

**Exit:** acceptance criteria and relevant checks pass; outstanding limitations are documented before production release.
