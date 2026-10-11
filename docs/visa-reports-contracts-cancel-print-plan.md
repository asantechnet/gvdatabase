# Plan — Visa Reports printing, contract extension/cancellation, popup footer and report dates

Source comments: `docs/Educational New Comments++.md` (round 2 of that file).

This document is the single source of truth for the work. The **Progress log** at the
bottom records what landed and how it was verified.

---

## 1. Requested changes (as written)

1. **Visa Reports page** — "print template looks very bad, get inspired by other
   templates, including the overall reports printing and fix this printing page. the
   current template appears depending on theme, in dark theme it appears dark in final
   print!"
2. **View popup component, footer** — remove the text
   "Showing business-friendly details. Internal database references are hidden."
3. **Employment Contracts need to be extendable just like academy contracts.**
4. **Cancelled contracts page** — rename the `client` column to `counterparty`.
5. **Service contracts need a cancel action button, as do all of them.** Other contracts
   need it as well. The required intent is the same: contracts move to the cancelled
   contracts page while all of their related data is retained and viewable from that page.
6. **All contracts, all pages** — cancelled contracts still exist in the contract pages
   they belong to. They only need a **cancelled label**, and the **print action button
   must not be available** for them; only **View** stays available.
7. **Finance → Reports, General Reports page** — the print template's generated-at date is
   not normalized, human readable or localized. Fix it.

---

## 2. Diagnosis (verified in code before the changes)

| # | Surface | State found |
|---|---------|-------------|
| 1 | `resources/ts/pages/educational-reports.vue` (route `/visa-reports`, name `visa-reports`, permission `view_visa_reports`) | Printed the **on-screen Vuetify table** (`printDocument({ bodyClass: 'printing-education-reports', pageStyle: 'size: A4 landscape; margin: 12mm;' })`) with ~90 lines of page-level `@media print` CSS. Theme variables (`--v-theme-surface` / `--v-theme-on-surface`) decided the colours, so a dark theme printed dark. No document header, no filter/period summary, no generated-at footer. |
| 2 | `resources/ts/components/FullDetailsButton.vue:583` | `.detail-viewer__footer` held a `detail-viewer__footer-note` (shield icon + sentence) beside the Close button, resolved from `components.fullDetails.footer`. |
| 3 | `resources/ts/pages/service-contracts.vue:280` | Extend existed only for academy. `contract_list.vue` (employee + other contracts) had no Extend action, and backend extension support lived only in `ContractGenerationService::create()`, which requires a built-in `service` and an applicant — employment/other contracts (service `null`) could not use it. `ContractController@validatedData` had no `parent_contract_id` rule at all. |
| 4 | `resources/ts/pages/cancelled_contracts.vue` | Column titled `common.messages.client` ("Client") bound to `counterparty`, filter labelled `…filterByClient`, locals `filterClient`/`clients`. |
| 5 | `service-contracts.vue` | Actions were print (non-academy), academy slip (academy), extend (academy). No cancel, no View. `ContractController@cancel` (`PUT /contracts/{id}/cancel`, middleware `permission:cancel_contracts`) already sets `is_cancelled`, `cancelled_at` (UTC) and `status='cancelled'` for `draft`/`active` contracts. |
| 6 | `contract_list.vue`; `service-contracts.vue` | `contract_list.vue` showed the print button on **every** row, cancelled included; service-contracts rows kept print/slip/extend after cancellation. `FullDetailsButton` (the only "view" affordance) was missing from `service-contracts.vue` entirely. |
| 7 | `resources/views/reports/print.blade.php:95` + `ReportController@export` | Footer rendered `__('reports.print.generated_at', ['date' => $report['meta']['generated_at']])` with `generated_at` an ISO-8601 UTC string, and the period/meta lines printed raw `Y-m-d` values. `reports.vue`'s own studio view already localized its copy, so only the server-rendered print and the CSV were wrong. |

**Environment facts that shaped the fix**

- `ext-intl` is **not** installed, so PHP-side localization uses Carbon. Carbon ships
  `fa_AF.php` / `ps_AF.php`, and `config/localization.php` maps `en`, `fa-AF → fa`,
  `ps-AF → ps`.
- The house print contract (`resources/ts/utils/printDocument.ts`) clones an element into
  `#app-print-host`, adds `printing-isolated-document`, injects the `@page` rule last and
  hides every other body child. `PrintableListReport.vue` is the reference
  theme-independent print document.

---

## 3. Decisions (confirmed with the requester)

| Topic | Decision |
|-------|----------|
| Visa Reports print | Dedicated **theme-independent print template** printing the **rows currently loaded** on the tab. |
| Employment extend | Creates a **new draft** contract, prefilled from the parent and fully editable, linked as an extension. |
| Extend availability | **Employment + Other** contracts (the two audiences of `contract_list.vue`). |
| Cancel | Added to **service contract pages** (all built-in services) and kept on `contract_list.vue`; cancelled records stay in their own list with a cancelled label, lose their print action, and stay viewable. |
| Generated-at | Fixed on **all three** surfaces: blade print report, the new Visa Reports print, and the CSV exports. |
| Popup footer | Removed the **entire footer note** (icon + sentence); the Close button remains. |
| Cancelled-contracts rename | Reused `pages.contracts.allContracts.allContracts.counterparty` / `.filterByCounterparty`, and dropped the dead `filterByClient` key. |
| CSV generated-at | Kept the machine-readable `generated_at_utc` line **and** added a localized `generated_at` line, so no CSV consumer breaks. |
| Server-side locale | The report export/print requests now carry the SPA locale explicitly (`?locale=fa-AF`), resolved through the shared `AcceptedLocale` helper. This is required because the global `SetLocaleFromRequest` middleware does not actually run (see §7); the helper still honours `Accept-Language` as a fallback. |

---

## 4. Implementation

### Step 1 — Visa Reports print template (comment 1) — done

- [x] 1.1 Added `resources/ts/components/educational/EducationalReportPrint.vue`: plain HTML
  table with brand header (logo + company from `useBranding()`), a facts/reporting-period/
  generated-at block, the table, and an RTL-aware footer. Hidden on screen
  (`.education-print-report { display: none }`) and revealed only under
  `body.printing-isolated-document` / `body.printing-education-reports`; forces
  `color: #1f2233`/`background: #fff` with `print-color-adjust: exact`, Persian-script
  font + RTL hooks, `thead { display: table-header-group }`, `tr { break-inside: avoid }`.
  Contains **no** theme variables and no `@page`.
- [x] 1.2 Reworked `educational-reports.vue`: on-screen table untouched; the ~90-line print
  block reduced to a namespaced native-print fallback that hides `.education-page-header` +
  `.education-panel` and reveals the print document; `printPage()` now calls
  `printDocument({ ..., element })`; `printRows`/`printColumns`/`printFacts`/`printPeriod`/
  `printTotalLabel`/`printGeneratedAt` feed the document.
- [x] 1.3 i18n: added `educational.printPeriod` and `educational.printGeneratedAt`
  (en/fa-AF/ps-AF); reused `educational.*` filter labels and `common.crmUi.printGenerated` /
  `printNoRecords`.

### Step 2 — View-popup footer (comment 2) — done

- [x] 2.1 Removed the `.detail-viewer__footer-note` block (icon + sentence) and its CSS;
  `.detail-viewer__footer` is right-aligned.
- [x] 2.2 Deleted `components.fullDetails.footer` from en/fa-AF/ps-AF.

### Step 3 — Employment + Other contract extension (comment 3) — done

- [x] 3.1 `ContractController@validatedData` accepts `parent_contract_id`; `store()` gained
  `extensionParent()`, forcing `contract_role = extension`, `parent_contract_id`,
  `status = 'draft'` and `snapshot_data.extension_of` while leaving the parent untouched.
  Rejected with `business.contract_extension_invalid` (422) for cancelled, already-extended,
  wrong-contract-type or unauthorized parents.
- [x] 3.2 `resources/ts/api/contracts.ts`: `parent_contract_id` added to `ContractCreateData`.
- [x] 3.3 `ContractForm.vue`: shared `ContractFormSource` type, new `parentContract` /
  `extensionMode` props, prefill from the parent, renewal start date (day after the parent's
  end date, else today), draft status locked, extension notice.
- [x] 3.4 `contract_list.vue`: `canExtend()` gating (permission, not cancelled, primary,
  active/completed), Extend row action, `:key` per dialog open, payload carries
  `parent_contract_id` + draft status, "extension draft created" toast.
- [x] 3.5 i18n: `pages.contracts.allContracts.allContracts.extendContract` /
  `.extendedDraftCreated` / `.cancelledContractPrintBlocked` and
  `pages.contracts.contractform.contractform.extendingNotice` / `.extensionStatusHint`.

### Step 4 — Cancelled-contracts page rename (comment 4) — done

- [x] 4.1 Column/filter switched to the Counterparty copy; locals renamed
  (`filterCounterparty`, `counterparties`).
- [x] 4.2 Added a localized **Cancelled at** column, and the printable list now derives its
  rows from the columns so they cannot drift apart.
- [x] 4.3 Added `common.fields.cancelledAt` (3 locales); removed the dead `filterByClient`.

### Step 5 — Cancel action + cancelled print/view matrix (comments 5 + 6) — done

- [x] 5.1 `service-contracts.vue`: View (`FullDetailsButton`) on every row, Cancel with a
  confirmation dialog gated by `cancel_contracts` + `!is_cancelled` + `draft|active`, list
  reload + toast; print/slip/extend moved behind `v-if="!item.is_cancelled"`; the status chip
  already reads "Cancelled".
- [x] 5.2 `contract_list.vue`: print hidden for cancelled rows, the `?action=print` deep link
  refused with a warning, cancel gated by the same permission.
- [x] 5.3 `cancelled_contracts.vue` stays view-only and now surfaces `cancelled_at`.

### Step 6 — Localized generated-at (comment 7) — done

- [x] 6.1 `app/Support/ReportDateFormatter.php`: `human()` (report timezone, locale-aware
  format, explicit GMT offset) and `day()` for period lines, using Carbon's `fa_AF`/`ps_AF`
  packs with an English fallback and no hard dependency on `ext-intl`.
- [x] 6.2 `app/Support/AcceptedLocale.php` + `SetLocaleFromRequest` delegation: one place maps
  `fa-AF → fa`; `ReportController@export` and `EducationalReportController@export` resolve the
  request locale before rendering.
- [x] 6.3 `resources/views/reports/print.blade.php`: `@use`s the formatter and localizes the
  footer, the header meta line and the summary period.
- [x] 6.4 Both CSV exports append `generated_at,<localized>` beside `generated_at_utc`; the SPA
  sends `locale: currentLocale()` from `api/report.ts` and `api/educationalReports.ts`.
- [x] 6.5 The Visa Reports print document shows the same localized stamp.

### Step 7 — Tests — done

- [x] 7.1 `scripts/contract-workflow-regression.mjs` — extension wiring (frontend + backend),
  the cancelled print/view matrix, the counterparty rename, the removed popup footer, and
  i18n parity for every key touched across en/fa-AF/ps-AF.
- [x] 7.2 `scripts/report-print-locale-regression.mjs` — formatter/locale helpers, blade +
  CSV localization, the locale request parameter, the print document being theme-independent,
  every print selector namespaced, and an SSR render of the document (content, empty state).
- [x] 7.3 `tests/Feature/ContractExtensionTest.php` — 7 tests: draft extension with parent
  link + `extension_of` snapshot, cancelled/extension/mismatched parents rejected, cancelled
  records retained and listed, blade print dates normalized (and localized for `fa-AF` with
  fallback for unsupported tags), and a readable visa CSV `generated_at`.

### Step 8 — Verification — done

- [x] 8.1 `npm run typecheck`
- [x] 8.2 `npm run i18n:check`, `npm run i18n:test`
- [x] 8.3 `php -l` on every changed PHP file
- [x] 8.4 `DB_CONNECTION=sqlite DB_DATABASE=":memory:" php artisan test --filter=…` (41 tests,
  324 assertions across the contract/report suites)
- [x] 8.5 Node regressions: new scripts plus `contract-print`, `receipt-print`,
  `service-contract`, `educational-ui`, `loan-receipt-print`, `academy-slip-print`
- [ ] Physical browser/PDF verification of the new Visa Reports print (needs a manual print
  dialog; the layout rules are covered by the SSR + CSS assertions above)

---

## 5. Cross-cutting notes

- **Print rules (AGENTS.md)** — every feature `@media print` selector is prefixed by its
  print-mode body class; `@page` stays out of route stylesheets and travels with
  `printDocument`; the standalone blade report legitimately owns its own `@page`.
- **RTL/LTR + light/dark** — the new print document is plain HTML with forced light colours
  and Persian-script font/RTL hooks; on-screen tables keep Vuetify theming.
- **API layer** — all contract/report calls stay in `resources/ts/api/*` (double `data`
  unwrap unchanged).
- **Permissions** — Cancel is gated client-side by `cancel_contracts` (matching
  `routes/api.php`) and server-side by the same middleware; Extend by `create_contracts`.
- **Not touched** — the removal of invoices/counterparty companies/commission ledgers stands;
  no compatibility aliases were restored, and academy's existing extension behaviour is
  unchanged.

## 6. Risks / follow-ups

- The Visa Reports print covers the **rows currently loaded** for the tab (an explicit
  choice). "Print all rows" would need a page loop (`per_page=500`).
- Extensions are drafts by design, so `ActiveContractService` and expiry notifications keep
  ignoring them until they are activated.
- Pashto month names come from Carbon's `ps_AF` pack; if a name were missing Carbon falls
  back to English month names, which is still human readable.

## 7. Findings discovered while implementing (not part of the request)

1. **The application's global middleware never runs.** `bootstrap/app.php` calls
   `withMiddleware()` twice — the second call is an empty closure, and because each call
   registers its own `afterResolving(HttpKernel::class)` callback that calls
   `setGlobalMiddleware()`, the second (empty) configuration overwrites the first.
   `SetLocaleFromRequest`, `LocalizeApiSuccessResponse`, `NormalizeApiErrorResponse` and
   `SecurityHeaders` are therefore dead code in every environment (verified: responses carry
   neither `Content-Language` nor the security headers, and `Kernel::getGlobalMiddleware()`
   holds only framework defaults). **Not changed here** because re-enabling them alters every
   API success/error payload and adds new response headers — a separate, deliberate change.
   The report exports resolve the locale themselves (see decision table) so comment 7 is
   fully addressed without it.
2. **`common.messages.noRecordsFound` was missing from all three locales** while
   `staff_salary.vue` still renders it (a regression from the earlier catalog cleanup). It was
   restored with its original translations and is now covered by both the i18n audit and
   `contract-workflow-regression.mjs`.
3. `scripts/service-contract-regression.mjs` still asserted two `components.dataTablePageKpis`
   keys that no component renders any more; the assertions were updated to the live key.

---

## 8. Progress log

| Date | Step | Status | Notes |
|------|------|--------|-------|
| 2026-10-11 | Recon | Done | All seven surfaces located; confirmed `intl` absent, Carbon `fa_AF`/`ps_AF` present, print house style, cancel route/permission, `contract_role`/`parent_contract_id` support. |
| 2026-10-11 | Decisions | Done | Q&A settled print scope, draft extensions, Employment + Other, and the three generated-at surfaces. |
| 2026-10-11 | Steps 1–2 | Done | New `EducationalReportPrint.vue` + rewired page; popup footer note and its i18n key removed. |
| 2026-10-11 | Steps 3–5 | Done | Backend + form + list extension flow; cancelled-contracts counterparty rename and cancelled-at column; service-contract Cancel/View actions and the cancelled print matrix. |
| 2026-10-11 | Step 6 | Done | `ReportDateFormatter` + `AcceptedLocale`, blade and CSV localization, locale parameter from the SPA. |
| 2026-10-11 | Step 7 | Done | Two Node regression scripts and `tests/Feature/ContractExtensionTest.php` added. |
| 2026-10-11 | Step 8 | Done | typecheck, i18n check/runtime, php -l, 41 PHPUnit tests (324 assertions) and seven Node regressions all pass. Physical PDF layout still to be eyeballed. |
| 2026-10-11 | Remaining tasks | Noted | Open items consolidated into §9 (R1 manual PDF check, R2 dead global middleware, R3 print scope, R4 draft extensions, R5 Pashto month fallback, R6 no local MySQL). |

---

## 9. Remaining tasks (open items as of 2026-10-11)

Nothing from the seven requested comments is outstanding; these are the items explicitly
left open above.

| # | Task | Where it comes from | Why it is not done |
|---|------|---------------------|--------------------|
| R1 | **Physical browser/PDF check of the new Visa Reports print.** Open the print dialog on Visa Reports in both light and dark themes, on A4 landscape, and confirm the header/facts/table/footer and pagination. | Step 8.5 (`[ ]`) | Needs a manual print dialogue; the CDP print scripts cover contract/receipt/academy-slip prints only. Layout is already covered by the SSR render + the CSS assertions in `report-print-locale-regression.mjs`. Optionally close this by adding a CDP `Page.printToPDF` regression for the educational report, like the contract one. |
| R2 | **The application's global middleware never runs.** `bootstrap/app.php` calls `withMiddleware()` twice; the second (comment-only, empty) closure overwrites the first via its own `afterResolving(HttpKernel::class)` → `setGlobalMiddleware()` callback. As a result `SetLocaleFromRequest`, `LocalizeApiSuccessResponse`, `NormalizeApiErrorResponse` and `SecurityHeaders` are dead in every environment. | §7 finding 1 | Deliberately left alone: re-enabling them changes **every** API success/error payload shape and adds response headers (and re-enables request-locale middleware globally). It is a separate, deliberate change with its own test sweep. Comment 7 is fully satisfied without it, because the report exports resolve the locale through `AcceptedLocale`. |
| R3 | **Visa Reports prints only the rows currently loaded for the tab.** | §6 risk; a decision made with the requester | The requested fix was the bad/dark template, not print scope. "Print all rows" needs a page loop (`per_page=500`) in `educational-reports.vue`. |
| R4 | **Extensions stay drafts**, so `ActiveContractService` and the expiry notifications keep ignoring them until activated. | §6 risk | By design (decision table). No action needed unless renewal drafts should alert before activation. |
| R5 | **Pashto month names depend on Carbon's `ps_AF` pack**; a missing name falls back to the English month. | §6 risk | Acceptable (still human readable). Only revisit if a Pashto name is reported wrong. |
| R6 | **Environment limitation:** no local MySQL, so `php artisan route:list` and any DB-backed artisan command fail here. PHP tests must run with `DB_CONNECTION=sqlite DB_DATABASE=":memory:"`. | Tooling notes | Not a product issue; recorded so the next session does not re-investigate. |

Incidental fixes already landed during this work (kept for the record, not open):
`common.messages.noRecordsFound` restored in all three locales, and
`scripts/service-contract-regression.mjs` assertions updated to the live
`components.dataTablePageKpis.scrollHint` key (§7 findings 2–3).

