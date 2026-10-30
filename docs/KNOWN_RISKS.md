# HS Shop known risks

This register reflects the current planning/repository state. Risks are not proof of an active vulnerability; they identify issues to resolve or control before the affected feature ships.

| Risk | Impact | Current status / mitigation |
|---|---|---|
| Target database is MySQL, but `.env.example` and the local starter database use SQLite. | MySQL-specific behavior, constraints, JSON, locking, decimal arithmetic, and migrations may differ; tests on SQLite alone can miss production defects. | Confirm local database strategy before schema work; verify important migrations and concurrency behavior on MySQL before release. |
| The database proposal is much broader than the fast MVP. | Implementing every documented module up front delays launch and increases migration complexity. | Follow `docs/IMPLEMENTATION_PLAN.md`; defer warehouses, full settlements/reconciliation, and shipping integrations. Keep only MVP-required records and extension points. |
| The per-unit advertising input may not reflect shared campaign spend. | A displayed product markup rate can be mistaken for realized or accounting profit when campaign costs are shared across products. | Markup basis and four-decimal half-up percentage rounding are approved; regular price remains manually set. Treat advertising input as a per-unit estimate and keep campaign allocation/reporting and accounting profit policy open. |
| Tax, refund eligibility, damaged-stock valuation, and accounting profit policy remain open. | Reports or refunds could be misleading or violate intended business policy. | Show component amounts separately; do not claim statutory/accounting net profit or automate unresolved refunds/write-offs. |
| Report event-date and KPI definitions remain open. | Orders, delivery, collections, refunds and returns can land in different periods; percentages can be misinterpreted. | Use confirmed Cairo calendar boundaries; label the date basis and show counts/amount denominators until definitions are approved. |
| Authentication, staff authorization, customer ownership checks and rate limits are not implemented. | Future storefront/dashboard endpoints could expose customer or operational data if added without server-side authorization. | Apply `AGENTS.md` security baseline; include authorization and abuse-case tests with each meaningful workflow. |
| Transfer proof images, review/support attachments and contact data are sensitive. | Publicly exposed uploads or over-retained personal data can harm customers. | Store private uploads outside public root, validate content/size, authorize downloads, minimize logs, and resolve retention policy before release. |
| `emarket/` is a static template, not a Blade app; no dashboard design was found. | Demo links, scripts, assets, or unused pages may be carried into production; dashboard UX has no selected template. | Convert only the four selected storefront pages, remove demo-only behavior, confirm template usage/license, and design a small purpose-fit dashboard. |
| Existing automated tests are framework examples only. | There is no regression coverage for HS Shop price, order, permission, return or reporting rules. | Add feature/unit coverage incrementally with implementation; never report example tests as business workflow coverage. |
| Exchange/refund/failed-delivery treatment is not fully specified. | Duplicate shipping charges, incorrect order closure, or double-counting losses are possible. | Keep delivery legs, exchange cases, refunds and costs as distinct records; approve rules before automating settlement or customer liability. |

## Release blockers by feature

- **Price and profitability reporting:** shared advertising attribution, event-date/KPI denominators, and accounting treatment remain open; distinguish displayed markup from realized/accounting profit.
- **Automatic refund/accounting results:** refund policy, tax treatment and accounting treatment.
- **Authoritative profit/KPI percentages:** event date, denominators, damaged-stock valuation, and shared-cost treatment.
- **Production data handling:** retention/anonymization and final database/deployment strategy.
- **Support operations:** staff visibility/permissions and data retention; notification delivery is confirmed in-dashboard and non-realtime.
