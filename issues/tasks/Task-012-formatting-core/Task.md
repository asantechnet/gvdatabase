# Task Task-012 — Create one money, date and number formatter

## Metadata
- Task ID: `Task-012`
- Title: `Create one money, date and number formatter`
- Initial status: `Ready`
- Type: `Frontend`
- Priority: `P2`
- Owner role: `Frontend platform implementer`
- Estimated size: `S`
- Source plan/version: `issues/review/issues/12-money-date-formatting/issue.md`; `issues/review/REPORT.md` §4 P2-5; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-12`

## Objective
Every screen formats money, dates and counts through one locale-aware utility, so the same amount never appears as `$45000.00`, `50000.00 AFN`, `60,000.00؋` and `؋0.00 AFN` in one product.

## Context and problem
Formatting is implemented per page. `staff_list.vue` hard-codes `$` and `.toFixed(2)`, payroll appends its symbol, bonuses combine symbol and code, contracts print raw ISO dates in the list, and the dashboard filter uses `mm/dd/yyyy`. `resources/ts/utils/localization.ts` already contains locale and number helpers, but money and display-date policy are not centralized, so each page invents its own.

## Scope
### Included
- Add one formatter module with explicit functions for money, dates and numbers, driven by the active locale and an explicit currency code.
- Money output includes the currency code exactly once and the correct decimal precision from the currency record; no bare `$` and no suffix symbol.
- Provide a documented display-date format set: localized short date for tables, localized long date/time for detail and timelines, ISO only for inputs and API payloads.
- Normalize digits within a locale so Persian/Dari screens do not mix Persian and Western digits in the same figure.
- Add unit-level tests for AFN and USD, zero and large values, RTL locale and date parsing.
- Document how page tasks adopt the utility, including the rule that pages must not call `Intl` directly for money.

### Excluded
- Rewriting every page's formatting call sites; Tasks 003, 004, 006, 007 and 011 adopt the utility.
- Currency conversion or exchange-rate logic.
- Translation key changes.
- Date-picker component changes beyond passing ISO values.

## Preconditions and assumptions
- `resources/ts/utils/localization.ts` is the existing locale source and must remain the single source for the active locale.
- Assumption to validate: currency records expose `code`, `symbol` and `decimal_places`. If they do not, the formatter falls back to two decimals and records the limitation.
- The project's test setup can run a TypeScript/Vitest unit test or an equivalent existing harness; if none exists, the verification uses a temporary script and records that limitation rather than adding a framework.

## Dependencies
- `None` — this is the shared foundation other tasks consume.

## Contracts and boundaries
- Input/output: exported functions accept a value, an optional currency object/code and an optional locale; they return a string and never mutate global state.
- Compatibility guarantee: `formatLocalizedDate` and `formatLocalizedNumber` keep their existing signatures and behaviour for current callers; new money helpers are additive.
- Error behavior: null/undefined returns a consistent em dash or empty string as documented; NaN returns the same placeholder rather than "NaN".
- Permission/data scope: none; formatting is presentation only.
- Integration seam: all page tasks import from this module; no page defines its own currency prefix or `toFixed(2)` money string.
- Files this task must not modify: any Vue page, `resources/ts/api/**`, `package.json`.

## Exclusive file ownership
- `resources/ts/utils/localization.ts` — preserve existing exports and add display policy helpers.
- `resources/ts/utils/money.ts` — new formatter module for money/date/number output.

## Acceptance criteria
- [ ] Given an AFN amount of 45000 and a currency record with code AFN and two decimals, when the money formatter runs, then it returns one string containing the formatted number and AFN exactly once.
- [ ] Given a USD amount and a locale with a different grouping convention, when the formatter runs, then grouping, decimal separator and code follow that locale without a hard-coded `$`.
- [ ] Given zero, a large value and a null value, when formatted, then zero is shown as zero, the large value is grouped, and null returns the documented placeholder without throwing.
- [ ] Given an ISO date string and a date-time string, when the display formatters run, then tables receive a localized short date, detail views receive a localized long date/time, and inputs keep the ISO value.
- [ ] Given the Dari locale, when a number or money value is formatted, then the output uses one digit set consistently within that value.
- [ ] Given a page that adopts the utility, when it renders money, then no `$` prefix, suffix symbol, duplicated symbol plus code or local `toFixed(2)` remains in the page source.

## Verification plan
- Automated: run the repository's existing frontend test command for the new unit tests; if none exists, run a temporary TypeScript script and record the command/output. Run `npm run typecheck`.
- Manual: render AFN and USD values in LTR, Dari and dark mode; compare the same amount on dashboard, payroll, staff, contracts and reports after those tasks adopt it.
- Fixtures: currency records for AFN and USD with different decimal places; ISO and date-time strings.
- Regression: confirm existing `formatLocalizedDate`/`formatLocalizedNumber` callers still render the same or an explicitly accepted improved format.
- Evidence: unit output, typecheck result, before/after page strings.

## Implementation notes
Prefer `Intl.NumberFormat` with an explicit `style: 'currency'` and `currencyDisplay: 'code'` where a currency code is required; do not concatenate a symbol and a code. Keep the currency precision explicit rather than defaulting to two decimals. For dates, expose named formats instead of letting pages pass arbitrary `Intl` options. Document that API payloads remain ISO.

## Risks and rollback
- Risk: changing date output affects tests that assert exact strings. Mitigation: keep existing helpers' defaults and add named formats for new usage.
- Risk: currency precision differs from legacy `.toFixed(2)`. Mitigation: compare totals and document intentional precision changes.
- Data migration: none. Rollback removes the new module and restores the previous page-level formatting.
- Stop condition: if the currency API does not expose a code/decimal precision, stop and record the contract gap instead of guessing from symbols.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
