# HS Shop schema review against the selected eMarket pages

**Purpose:** compare the selected static eMarket templates with the confirmed HS Shop MVP. Template files are visual references; their links, buttons, sample products, and JavaScript calls are not evidence of implemented application behavior.

## Sources reviewed

- `emarket/index.html` — selected homepage.
- `emarket/category.html` — selected category/listing page.
- `emarket/product-v2.html` — selected product page.
- `emarket/about-us-4.html` — selected About page, including its FAQ block.
- `emarket/checkout.html`, `emarket/login.html`, and `emarket/register.html` — supporting pages reviewed for the guest-to-account checkout flow. They and other relevant non-duplicate template pages are in scope; the four owner selections resolve duplicated visual alternatives only.
- Existing database documentation: `README.md`, `ERD.md`, `TABLES.md`, `CONSTRAINTS_AND_INDEXES.md`, `WORKFLOWS.md`, and `../OPEN_DECISIONS.md`.
- Repository migrations include the approved MVP schema, and the database seeder defines an environment-driven super-administrator account; the account is not yet created because its credentials have not been supplied. Deferred procurement, inventory, carrier settlement reconciliation, and cash sessions remain excluded.

## Design-to-schema mapping

| Template/design feature | Schema coverage | MVP disposition |
|---|---|---|
| Home hero, promotional image tiles, and editable homepage sections | `banners`, `banner_translations`, `homepage_sections`, `homepage_section_translations` | Keep. Admin-managed images, visibility, ordering, localized headings, and safe target links match confirmed requirements. The supported slots should be limited to the selected home layout. |
| “New” and “popular” product rows | `product_collections`, translations, `product_collection_items` | Keep. Curated manually by staff as requested; do not infer “new” from creation date or “popular” from sales. |
| Product cards, images, names, regular price, discounted price, and category navigation | products/translations, variants, price history, images, category tree/translations, product-category links | Keep. The owner confirmed optional product discounts: store regular and sale prices per sellable variant, show both on the storefront when a sale price exists, and preserve both plus the charged amount in each immutable order revision. Line discount is the regular-to-charged price difference × quantity. This is a product discount, separate from coupons. Replace all demo content and `$` prices. |
| Category hierarchy, product listing, category sidebar, sort/filter controls | recursive `categories`, product-category links, variants and attributes | Keep the hierarchy, basic category listing, price ordering/filter, and available variant filters. Search/sort/filter values are request behavior and do not need dedicated persistence tables. Rating sort can be computed from approved, visible reviews. |
| Product detail gallery, color/size selectors, quantity, and add-to-cart | products, variants, variant attribute values/translations, images, carts/items | Keep. A selectable combination must resolve to a real active variant; quantity validation is application-level. “In Stock” in the demo must be replaced by the confirmed manually controlled available/unavailable status because HS Shop has no inventory. |
| Product review form and displayed ratings/comments | `product_reviews` with moderation and visibility fields | Keep. Only approved and visible reviews are public. Rating scale, verified-purchase eligibility, and edit/review limits remain open in `OPEN_DECISIONS.md`. |
| Cart and checkout | `carts`, `cart_items`, customers/addresses, orders, immutable revisions/items/address snapshots, approvals, payments | Keep. Anonymous browsing/cart may be supported; account is required when placing the web order. Website payment is COD only. InstaPay remains a staff-entered social-order path. |
| Guest checkout followed by registration/login | Guest-owned `carts`/`cart_items`, server-side session, then customer ownership after login/registration | Keep. The guest can open checkout and choose products/options; require authentication only at final order placement. Use the available checkout, login, and register templates where suitable; preserve cart/form choices through auth and resume checkout afterwards. |
| Account/order history | users, customers, addresses, orders, revisions, payments, shipments, returns, support tickets | Keep. Build the relevant account, order history, and order detail pages using available non-duplicate template pages where suitable; these are part of the website scope. |
| About Us and FAQ block | `cms_pages`, `cms_page_translations`, plus proposed `about_faqs` and localized FAQ rows | Keep. Owner confirmed the dashboard must let staff add/edit, show/hide and order FAQ questions/answers on About Us. |
| Product wishlist controls/header badge | `customer_favorites` | Keep the simple Favorites feature as approved: one heart toggle saves the product, not a selected variant. No named lists, quantities or sharing; full Wishlist is out of scope. |
| Compare controls/page | No compare tables are proposed | Defer/remove from the selected design. A transient comparison could be session-only if later requested; it does not currently justify persistent schema. |
| Quick view | No dedicated table required | Defer/remove. It is a presentation shortcut over the product page, not a separate business record. |
| Newsletter form | No subscriber table is proposed | Defer/remove until email marketing and consent/retention handling are approved. If retained, add a dedicated subscription/unsubscribe design rather than storing this as a customer feedback/contact message. |
| Blog/latest articles | No blog tables are proposed | Remove from the initial homepage unless publishing articles is approved; a blog would require separate content records/translations and publication workflow. |
| Product discounts / sale presentation | `product_variants` and `product_price_history` are updated to include regular and optional sale price; order revision items snapshot both and the charged price | Keep. Product sale prices are manually entered per variant; the storefront shows regular and discounted prices together when applicable. No schedule, auto-expiry, or coupon behavior is implied. |
| Footer Instagram gallery/social links | Static links/assets can be configured without commerce data; homepage banners may cover manually chosen images | Keep only required HS Shop links/images. Do not build social-feed integration for MVP. |
| Contact Us and support | `contact_messages`, support tickets/messages/events/attachments and notifications | Keep as already confirmed. The selected About template only shows footer navigation, not the full contact/ticket workflow. |

## Findings and proposed corrections

1. **The approved MVP schema is now installed in the configured MySQL database.** Three HS Shop migrations extend the Laravel starter schema. The separate local SQLite file remains an older development artifact and is not the configured target. Schema installation does not mean storefront/dashboard workflows are implemented.
2. **The selected template contains demo-only scope.** Comparison, newsletter, and blog are confirmed excluded for now. Quick view, social galleries, alternate duplicate home layouts, product reward points, and sample price/stock claims should also not be treated as approved requirements. Hide/remove these controls unless later requested. The owner selected among duplicate layout alternatives; relevant remaining pages are still in scope.
3. **Favorites is approved for the MVP.** It is one quick heart toggle per product and customer, represented by `customer_favorites`; it does not save a size/color selection, quantity, or named list. Full Wishlist behavior is deferred.
4. **FAQ editing is now a confirmed requirement.** The proposal adds ordered and localized FAQ records associated with the About page; the CMS page body remains for general page copy.
5. **Product discounts are now a confirmed requirement.** The proposal adds regular and optional sale prices per variant, history rows, and immutable order snapshots of both shown prices and the amount charged. A sale price must be lower than the regular price. Coupon codes remain separate and deferred.
6. **The broad proposed schema exceeds the fastest launch subset.** Carrier settlement/remittance ledgers and cash sessions are documented but explicitly deferred or open in `OPEN_DECISIONS.md`; they should not be pulled into the first implementation batch without a scope decision. Keep manual shipment status, actual shipment/failed-delivery costs, order cost entries, returns/refunds, and order history in the initial scope as already confirmed.
7. **Template copy is English and uses non-HS-shop product data.** Arabic/English translations are modeled, but actual translated HS Shop content and translated assets need owner-provided copy. Do not publish placeholder English copy as if approved brand content.

## MVP schema slice implemented

1. Identity, customer accounts, dashboard roles/permissions, and customer addresses.
2. Categories, brands if used, products, variants/attributes, images, manual availability, regular/sale price history, multiple supplier options/history, and manual new/popular collections.
3. Homepage banners/sections, About content and FAQ CRUD, carts, checkout, order/revision snapshots, customer approvals, order activity log, and website COD / staff-entered social InstaPay proof.
4. Manual shipments and events, returns/refunds, product cost and order operating-cost records, and report source facts.
5. Moderated product ratings, moderated customer feedback, Contact Us, support tickets, and in-dashboard notifications.

Defer compare, newsletter, blog, quick view, coupons, advanced CMS/page builder, carrier settlement reconciliation, cash sessions, procurement, and inventory. Product-level sale pricing and About FAQ management are now in scope. Supplier directory/selection is in scope, but it is not purchasing or stock management.

## Remaining workflow policy gates

The owner authorized implementation of the documented MVP schema. Favorites and per-variant discount entry are included; sale scheduling remains unresolved. Comparison, newsletter and blog are excluded. Review unresolved business policies in `../OPEN_DECISIONS.md`; recommendations there are not approvals. Preserve open tax, legal, accounting, refund, retention, and approval policies rather than assigning them by implication.

## Senior review readiness verdict

The MVP entities and relationships cover the approved storefront, order, supplier-selection, manual fulfillment, review moderation, homepage management, support, and reporting needs. The selected eMarket pages can be used as the visual base after removing the confirmed demo-only elements. No inventory/procurement feature is accidentally required for product availability or supplier selection.

The MVP schema migrations completed on MySQL 8.0.30. The markup-on-cost display rate and its four-decimal half-up percentage rounding are approved; the regular price remains manually entered and is not generated from the rate. Remaining workflow gates include order transitions, approval expiry/evidence, refund rules, and report date/KPI definitions before dependent workflows become authoritative. Administrator price editing and per-variant discount entry are approved. The admin seed itself requires owner-provided environment credentials.

No storefront or dashboard behavior is implemented by these migrations. The admin seeder is environment-driven; no password is hard-coded or included in `.env.example`.
