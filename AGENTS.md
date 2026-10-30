# HS Shop build and coding rules

These rules apply to all future application work in this repository. Keep the Laravel/Blade modular-monolith approach and consult `docs/database/` and `docs/OPEN_DECISIONS.md` before changing business behavior or schema. Do not silently turn an open decision into a requirement.

## Verified project baseline

- The repository declares Laravel Framework `^12.0` and PHP `^8.2`; verify installed/runtime versions before relying on version-specific behavior.
- Runtime dependencies declared by the project are Laravel Framework and Tinker. Check `composer.json`/`composer.lock` before adding packages; ask before adding significant dependencies or external services.
- Blade interface styling uses Bootstrap 5 (`bootstrap` npm package) with its matching RTL stylesheet for Arabic. `emarket/` is a Bootstrap 3 reference template; reuse its chosen page structure and assets selectively, but do not bring its Bootstrap 3 CSS/JS into new Laravel views.
- Blade interface styling uses Bootstrap 5 (`bootstrap` npm package) with its matching RTL stylesheet for Arabic. `emarket/` is a Bootstrap 3 reference template; reuse its chosen page structure and assets selectively, but do not bring its Bootstrap 3 CSS/JS into new Laravel views.
- MySQL is the approved target and `.env.example` now selects MySQL. The configured local server was verified as MySQL 8.0.30; deployment credentials remain environment-specific.
- This is a modular monolith using Laravel and Blade. `emarket/` is a static HTML design template, not an implemented storefront or admin dashboard.

## Code quality and structure

- Write clear, small, single-purpose classes and methods. Use descriptive names and avoid duplicated business rules, magic values, oversized controllers, and unnecessary abstraction.
- Use Bootstrap 5 components, utilities, grid, forms, tables, and pagination as the default for Laravel/Blade UI. Keep small custom CSS for the HS Shop brand and layout details; do not reintroduce Bootstrap 3 from the static `emarket/` reference assets. Use Bootstrap 5's RTL stylesheet for Arabic and the standard stylesheet for English.
- Keep primary dashboard actions visually consistent with the customer-management pattern: use `btn btn-primary primary-button` for add/save/primary search actions, with shared layout helpers for sizing. Apply the same pattern to new admin screens; use secondary/danger styles only for non-primary actions.
- Keep controllers focused on HTTP input/output. Use Form Requests for validation and authorization, Policies/Gates for access checks, and move reusable domain workflows into focused services/actions when that makes them easier to understand or reuse.
- Use Eloquent relationships and explicit query scopes; avoid N+1 queries and unsafe raw SQL. Protect mass assignment with `$fillable` or `$guarded` and validate every request on the server.
- Keep storefront, customer account, and dashboard routes/controllers/views organized separately. Enforce dashboard permissions on the server for every protected operation; hiding a button is not authorization.
- Use database transactions for multi-record business operations. Lock rows when concurrent updates could oversell, duplicate a payment, apply a revision twice, or post duplicate financial/inventory records. Preserve append-only financial, order, and inventory history and use reversal/correction records instead of deleting posted history.
- Use named constants/enums or centralized state definitions where useful, but preserve the documented MySQL convention of VARCHAR state fields unless a schema decision changes it.

## Return paths, errors, and user messages

- For every action, handle its expected outcomes explicitly: success, validation failure, unauthenticated access, unauthorized access, missing/deleted records, invalid state transitions, conflicts, and unexpected failures where applicable. Do not leave callers with ambiguous or empty responses.
- For Blade form flows, redirect with clear success/error flash messages and preserve safe input where appropriate. Render the correct HTTP error/status page for forbidden, missing, throttled, expired-session, and server-error cases. For any future JSON endpoint, return a consistent documented response shape and suitable HTTP status codes.
- Put all customer/staff-facing text, validation messages, and error messages in Laravel localization files for both Arabic (`ar`) and English (`en`). Use the active locale consistently; do not hard-code one language in controllers or views. Ensure Arabic text renders correctly and layouts support RTL/LTR.
- Validation failures should identify the affected field in the active language. Unexpected errors must be logged with enough context/correlation to investigate, while the user sees a safe localized message. Never expose stack traces, SQL, filesystem paths, credentials, tokens, or internal exception details to users.
- Do not catch and silently ignore exceptions. Catch only when the code can recover, translate to a meaningful domain/HTTP result, or add useful context before rethrowing. Keep error logging free of passwords, payment proofs, session identifiers, and unnecessary personal data.

## Security baseline

- Follow Laravel security defaults: CSRF protection for state-changing web forms, output escaping in Blade, secure password hashing/authentication, session regeneration on login, and safe logout/session invalidation.
- Validate and authorize on the server. Apply least privilege through roles/permissions and object-level checks so customers can access only their own profile, addresses, carts, orders, reviews, and tickets; staff can perform only permitted dashboard actions.
- Protect against mass assignment, SQL injection, XSS, CSRF, IDOR, brute force, and unsafe redirects. Use parameterized Eloquent/query-builder operations, escape rendered content, rate-limit sensitive/public endpoints, and validate redirect destinations.
- Validate file uploads by size, extension and detected MIME type; use generated storage names, store private evidence/attachments outside the public web root, and authorize every download. Do not trust a client-provided file name or path.
- Keep secrets out of source control, logs, database settings, and user responses. Use environment/config secrets and encryption for sensitive integration credentials. Do not store raw payment credentials or unnecessary sensitive data.
- Use HTTPS in deployment, secure/HTTP-only/SameSite cookies, production-safe debug settings, and restrictive CORS/security headers where applicable. Avoid collecting or retaining personal data beyond the documented business need and approved retention policy.

## Business and data integrity

- Keep Arabic/English content in translation fields and use EGP as the configured default; represent money with DECIMAL, never FLOAT/DOUBLE.
- Snapshot order and supplier terms at the time they are agreed. Later catalog, supplier offer, or price changes must never rewrite historical orders or reports.
- Keep order, payment, sourcing, shipment, return, and support-ticket lifecycles independent. Require the documented customer approval before applying changes to order price, products, or quantities.
- Record actors and timestamps for meaningful dashboard/order changes. Use idempotency and transaction boundaries for checkout, payment proof decisions, order revisions, supplier receipt, returns/refunds, and status events.
- Keep manual MVP processes simple; do not add advanced warehouse, accounting settlement, or shipping-provider integrations until explicitly brought back into scope.

## Database change rules

- Follow the approved documentation and actual configured database engine. Make migrations explicit, reviewable, reversible where safe, and define foreign keys, uniqueness, indexes, and deletion rules.
- Do not make destructive schema/data changes without an approved migration and preservation plan. Never assume application validation alone prevents concurrent duplicates or race conditions.
- Consider pagination, eager loading, indexes, and expected data growth. Keep posted financial and operational history auditable through reversals/voids rather than deletion.

## Strict database design approval gate

- During database design/review, inspect the actual repository and applicable documentation, update schema proposal documents only, identify inconsistencies and classify approved requirements vs. technical proposals vs. owner decisions vs. blockers.
- In the design/review phase, do not create or execute migrations/seeders, write application code, run database commands, or alter any database.
- Present the complete, internally consistent schema and its remaining blockers/decisions to the owner, then stop for review.
- Schema documentation approval or general encouragement is not implementation authorization. Do not create or run schema migrations until the owner explicitly approves the reviewed schema for implementation in the conversation.
- After explicit schema approval, compare the approved docs with the repository, plan dependency-safe migration order, and identify deviations. Ask for approval before introducing any schema or business-rule deviation. Preserve existing migrations/data; never reset or destructively rebuild a database without separate explicit authorization.
- After implementation, report actual migration/test checks, compare the resulting schema with the approved design, and document any approved deviations. Never claim checks were run unless they were.

## Testing and verification

- Every meaningful implementation change includes appropriate automated tests: feature tests for workflows and authorization, unit tests for isolated business rules where useful, and regression tests for fixes.
- Cover success, validation, authorization, edge/failure cases, and relevant idempotency, rollback, rounding, or concurrency behavior for finance/inventory work where feasible.
- Run relevant tests, formatters, static analysis, builds, migrations, or security checks that are available and appropriate. Report actual commands/results; never claim checks passed if they were not run successfully.
- If verification is blocked by an unavailable service/tool/configuration, state the specific limitation. Do not weaken or remove tests to obtain a pass.

## Requirements, approvals, and workflow

- Classify findings as blocker, needs approval, safe default, or follow-up. Ask concise questions only when a missing decision can change money, customer commitments, security/privacy, inventory, refunds, accounting, or irreversible data behavior.
- Ordinary reversible technical choices may follow Laravel conventions. Ask before changing the approved architecture/schema, adding significant dependencies/services, changing core business policies, destructive data work, or removing functionality.
- Before implementation, inspect relevant files/instructions, summarize acceptance criteria and affected areas, and identify blockers/risks. Work in small steps, avoid unrelated refactoring, preserve user work, and keep documentation aligned.
- Do not start a feature when the owner explicitly asks for documentation/planning only or says to wait for approval.
- For significant project work, maintain `docs/PROJECT_STATUS.md`, `docs/IMPLEMENTATION_PLAN.md`, `docs/KNOWN_RISKS.md`, and `docs/OPEN_DECISIONS.md` when useful. Update equivalent existing documents instead of duplicating them. Do not create a parallel decision log when decisions are already captured in design docs.
- Do not mark a feature complete from generated code alone. Completion requires acceptance criteria and required verification. Report changed files, actual checks, security/data-integrity implications, remaining blockers, approvals needed, and the best next step.
