# Task Task-014 — Make page headers, copy and counts consistent

## Metadata
- Task ID: `Task-014`
- Title: `Make page headers, copy and counts consistent`
- Initial status: `Ready`
- Type: `Frontend`
- Priority: `P3`
- Owner role: `Vue/Vuetify implementer with copywriting review`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/16-headers-placeholders/issue.md`; `issues/review/REPORT.md` §4 P3 observations; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-16`, `ISSUE-16-DARK-LOGO`

## Objective
The tasks and expenses list pages share one header and action hierarchy, contain no placeholder copy, and pluralize counts correctly, including a legible brand mark in dark theme.

## Context and problem
Payroll, tasks, expenses and several settings pages do not use the shared `ErpPageHeader`; some show the H1 only inside a card. Copy includes "1 candidates", "1 Active Filters" and duplicate stream labels. The tasks board has no page header and its fifth column clips. The shared `ErpPageHeader` exists but its action area is inconsistent across adopters.

The report also observed that the "Educational Visa" wordmark is unreadable in dark theme. The logo surface must be made theme-aware without replacing company branding.

## Scope
### Included
- Adopt `ErpPageHeader` with breadcrumb and H1 on tasks and expenses; remove the duplicate card title where it repeats the H1.
- Replace placeholder or unexplained copy on these pages and the adopted header pages with real descriptions.
- Pluralize counts through the i18n layer: 1 candidate, 2 candidates, 1 user, 2 users, 1 active filter and 2 active filters.
- Remove duplicate stream/ledger labels on expenses and keep one name per concept.
- Fix the tasks board header and ensure its columns fit or scroll with an affordance; no clipped fifth column at 1280 px.
- Give truncated card descriptions a tooltip or full-text expansion.
- Make the dark-theme brand mark legible by using a light logo variant or an appropriate surface; record the exact logo asset/component touched before editing.
- Standardize action hierarchy: primary Save/Submit, secondary Cancel, destructive Delete separated and labelled.

### Excluded
- Verbatim copy for payroll/contracts/roles/audits/account settings; those are acceptance criteria in Tasks 006, 003, 008, 010 and 009.
- New features, board column semantics or task workflow changes.
- Translation completeness for every locale; this task adds the keys it needs in the existing locale files.
- Shared table and icon accessibility primitives (Task-013).

## Preconditions and assumptions
- `resources/ts/components/erp/ErpPageHeader.vue` is the intended shared header and already supports a title, section breadcrumb and optional actions.
- Assumption to validate: the dark-theme logo is rendered from a theme/layout asset or component that can select a variant. If it is a single raster asset with no variant, a new light asset is required and must be documented.
- Count strings can use vue-i18n pluralization; existing keys may need restructuring.
- The tasks board columns are services, not statuses; this task does not rename that model, only the page copy so it does not claim to be a status Kanban.

## Dependencies
- `None`.

## Contracts and boundaries
- Input/output: no API changes; page routes and query parameters remain stable.
- Compatibility guarantee: existing translations keep working; new plural keys are additive and the English fallback is explicit.
- Error behavior: a missing translation falls back to readable English, never a raw key or `[missing: ...]` text.
- Permission/data scope: unchanged; header actions still respect the same permission checks.
- Integration seam: all adopted pages use `ErpPageHeader`; no page reimplements a breadcrumb/H1 block.
- Files this task must not modify: `resources/ts/pages/payrolls.vue`, `resources/ts/pages/bonuslist.vue`, `resources/ts/pages/income.vue`, `contract_list.vue`, `setup/roles.vue`, `audits.vue`, `views/pages/account-settings/**`, `resources/ts/components/responsive-data-table/**`.

## Exclusive file ownership
- `resources/ts/pages/tasks.vue` — header, board copy and column fit.
- `resources/ts/pages/expenses.vue` — header, stream copy and duplicate-label removal.
- `resources/ts/components/erp/ErpPageHeader.vue` — consistent header/action layout.

## Acceptance criteria
- [ ] Given the tasks and expenses pages, when each opens, then it shows a breadcrumb and H1 through `ErpPageHeader`, and no card repeats the same H1 as a second title.
- [ ] Given a page with a count, when the count is 1 or greater than 1, then the string is pluralized correctly and no "1 candidates", "1 users" or "1 Active Filters" text remains on the owned pages.
- [ ] Given the tasks board at 1280 px, when it renders, then the header states that columns are services, the fifth column is reachable without clipping, and each card exposes its due date and assignee in text.
- [ ] Given expenses, when it renders, then each concept has one label and no duplicate stream/ledger naming remains on the page.
- [ ] Given a truncated card description on an owned page, when the user hovers or focuses it, then the full text is available without navigating away.
- [ ] Given dark theme, when the header/brand area renders, then the company brand mark is legible and the exact asset/component used is recorded in `Progress.md`.
- [ ] Given a destructive action and a save action on the same dialog, when they render, then the primary action is visually dominant, cancel is secondary, and delete is separated and clearly destructive.

## Verification plan
- Automated: `npm run typecheck`; run the i18n missing-key check if the repository has one, or search the page sources for raw `[missing:` output.
- Manual: open the four pages at 1280, 768 and 390 px in LTR/RTL and light/dark; inspect the tasks board fifth column; verify plural forms with 1 and 2; check the dark logo.
- Fixtures: at least one candidate, one user and two cases to exercise plurals; no production data.
- Regression: confirm the shared header still renders correctly on pages that already use it; confirm board drag/drop and task actions are unaffected.
- Evidence: before/after screenshots, plural strings, dark-logo asset path, typecheck result.

## Implementation notes
Keep the header API backward compatible: adding optional slots/props is fine; renaming existing props requires updating all adopters in this task only. For plurals, prefer `$t('key', count, { count })` with vue-i18n plural rules over string concatenation. For the dark logo, do not recolor the company brand arbitrarily; use an approved light-on-dark variant or a neutral container with sufficient contrast.

## Risks and rollback
- Risk: changing shared header props breaks pages outside this task's ownership. Mitigation: additive props only, and run a source search for every adopter.
- Risk: plural keys differ between locales. Mitigation: add English and the supported locale forms together and verify missing-key warnings.
- Risk: a light logo variant does not exist. Mitigation: record the missing asset as a blocker for the design owner rather than shipping an illegible mark.
- Data migration: none. Rollback is code-only.
- Stop condition: if the brand logo has no approved dark variant, stop the logo slice and complete the rest of the task.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
