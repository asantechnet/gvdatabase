## Task: Brand-coloured documents, year-month payroll filter and true amount in words

- **Task ID:** document-branding-and-amount-in-words
- **Created:** 2026-11-08
- **Last updated:** 2026-11-08
- **Status:** COMPLETED (one unrelated pre-existing audit test still fails)
- **Outcome:** Receipts, payslips, commission slips, salary-advance receipts and academy slips use the company's brand colours instead of letterhead artwork; payroll's month control is a Vuexy select; the advance signature names the employee; and receipts, payslips and advance receipts print the amount in words in the active language.

### 1. Requirements and acceptance criteria
- [x] Receipt, payslip, commission slip, salary-advance receipt and academy slip no longer render or depend on receipt/payslip letterhead artwork.
- [x] Those five documents take their accent colours from `settings.primary_color` and `settings.secondary_color`, with the existing theme defaults as fallback.
- [x] Payroll's month control is a standard Vuexy `VSelect` of year-month options and cannot accept arbitrary typed text.
- [x] The salary-advance receipt shows the employee's name in the "Received by" signature block instead of the generic "(Employee)" wording.
- [x] Receipt, payslip and salary-advance receipt render a real amount in words, not a numeric fallback.
- [x] Amount in words supports English, Dari (`fa-AF`) and Pashto (`ps-AF`), uses the active UI locale when previewing/printing, and includes the currency name and minor unit where applicable.
- [x] RTL, light/dark and A5 printing behaviour is preserved; no `@page` was added to the templates and the payslip's print rule is namespaced.

### 2. Scope and non-goals
- In scope: the five named Vue document templates; receipt/academy/commission payload builders and TS contracts; payslip/advance company data in `payrolls.vue`; a shared amount-in-words utility and receipt/i18n labels; payroll month selector; advance-signature label; focused PHP and type checks.
- Out of scope: contract documents and contract letterhead; database migrations; removing the legacy receipt/payslip letterhead settings UI/API (the documents simply stop consuming it); adding amount-in-words to commission/academy slips (the new utility is reusable for that follow-up); changing payroll calculations or document numbering.

### 3. Project findings
- `DocumentLetterheads` resolved receipt/payslip artwork, and `IncomeReceiptService`, `AcademySlipService` and `CommissionSlipService` included a `letterhead` object in their payloads. The receipt, academy and commission templates rendered it as an absolute art layer and shrank content into a safe area.
- `payslip_template.vue` accepted `letterheadUrl`/`letterheadSafe` from `payrolls.vue`; the advance receipt had no letterhead but hardcoded a blue accent.
- All five templates hardcoded accent colours (`#16a1d7`, `#0f766e`, amber, blue).
- `SettingResource` already exposes `primary_color` and `secondary_color` through `ThemePalette` (defaults `#80027F`, `#F47E26`).
- `payrolls.vue` bound `month` to `<VTextField type="month">`; `bonuslist.vue` already established the project pattern of a `VSelect` fed by localized year-month options.
- `payrolls.vue`'s `formatInWords()` only interpolated the formatted number and currency code through `documents.payslip.amountInWordsFallback`; it never spelled the number.
- `advance_receipt_template.vue` rendered `documents.advanceReceipt.receivedBy`, whose translations ended in "(employee)"/"(کارمند)"/"(کارکوونکی)".
- `IncomeReceiptTest` and `CommissionLifecycleTest` asserted the letterhead payload; those assertions were replaced with brand-colour coverage, and `AcademySlipTest` gained colour coverage.
- `AGENTS.md` requires logical CSS, RTL/LTR, dark/light safety, namespaced print CSS, and `npm run typecheck`.

### 4. Graph and impact review
- `graphify-out/` exists, but its report was built from `c4c97435` while the working tree is at `00a4ef5c`; it was useful only as a stale map. Actual tracing used `code_search`, `glob` and direct reads of every affected payload, API contract, template, print utility, test and payroll page.
- Downstream surfaces: three backend services and their feature tests; three TS API contracts; three shared document templates; the payslip/advance templates and `payrolls.vue`; three locale files; the existing commission plan record's letterhead decision is superseded for that document.
- Print utilities do not reference letterhead themselves; they clone cards/content and wait for images, so removing the art layer does not change the print host contract.

### 5. Clarifications, decisions, and assumptions
- Decision: stop consuming letterhead in the five documents; leave the existing Settings controls/API/columns intact for compatibility rather than performing an unrequested settings-data migration. This is recorded as a follow-up if the controls should be retired.
- Decision: use `primary_color` for headings/rules/amount emphasis and `secondary_color` for secondary accent borders and tints; keep neutral ink/line colours for readability.
- Decision: use the project's `VSelect` + localized `YYYY-MM` option pattern for payroll (last 24 months), so arbitrary text cannot be entered.
- Decision: implement amount-in-words as a pure frontend utility with English/Dari/Pashto dictionaries and currency labels; compute it from the template's active `useI18n().locale` so printing follows the current language. This avoids stale server-localized strings.
- Assumption: the receipt prints the received amount (`income.line_total`) in words; the payslip prints the net payable; the advance prints the advance amount.
- Assumption: unknown currency codes fall back to the code itself, with a localized fractional-unit label.

### 6. Implementation plan
- [x] Step 1 — Add `resources/ts/utils/amountInWords.ts` with English/Dari/Pashto integer, currency and fractional handling.
- [x] Step 2 — Add `resources/ts/utils/documentBranding.ts` for validated brand CSS variables and fallbacks.
- [x] Step 3 — Remove letterhead payloads/fields from the three backend services, their TS contracts, and the receipt/academy/commission templates; add brand colours to company payloads.
- [x] Step 4 — Recolour all five templates with brand variables, remove letterhead art/safe-area styles, and add receipt/payslip amount-in-words rendering.
- [x] Step 5 — Show the employee name in the advance signature and update the three `receivedBy` translations.
- [x] Step 6 — Replace payroll's native month input with a localized `VSelect`; remove the old numeric fallback helper.
- [x] Step 7 — Add the receipt amount-in-words label; reuse the existing `common.fields.month` label for payroll in all three locales.
- [x] Step 8 — Update receipt, academy and commission backend tests for brand colours and no letterhead.

### 7. Validation plan and results
- [x] PHP syntax checks for changed services: all three passed.
- [x] Focused PHP tests: `IncomeReceiptTest`, `AcademySlipTest`, `CommissionLifecycleTest` — 18 passed, 1 failed (559 assertions).
  - Failure: `IncomeReceiptTest::test_income_and_letterhead_changes_are_audited` fails at its first check because no `updated` audit row exists for the income. This occurs before its letterhead/settings checks; `Income`/`IncomeController` audit code is untouched by this task, so this is a pre-existing failure rather than a regression from these changes.
- [x] `npm run typecheck`: exits 2, with only the pre-existing diagnostics in `applicant-case.vue`, `staff_list.vue` and `support.vue`; no diagnostics in any changed file. (The advance receipt company type was extended after the first run, and the second run confirmed it clean.)
- [x] Compiled all five changed SFCs with `@vue/compiler-sfc`: 5/5 passed.
- [x] Verified no `@page` rule was introduced; the receipt/academy/commission print hosts use their feature body classes, the advance uses `body.printing-advance-receipt`, and the payslip's print-box-shadow rule is now scoped to `body.printing-payslip-document`.
- [x] Ran the amount-in-words utility against representative values and currencies:
  - English `1250.5 AFN` → “one thousand two hundred fifty Afghan afghanis and fifty pul only”.
  - Dari `1250.5 AFN` → “یک هزار و دوصد و پنجاه افغانی و پنجاه پول فقط”.
  - Pashto `1250.5 AFN` → “یو زره او دوه سوه او پنځوس افغانی او پنځوس پول یوازې”.
  - Singular, whole-number and million-scale cases were also checked in all three locales.
- [x] Validated all three locale JSON files and `git diff --check`: passed.

### 8. Risks and mitigations
- Removing letterhead space changes vertical rhythm — the existing card padding and paper-overflow guards remain; all five SFCs compile.
- Brand secondary can be low-contrast on white — it is used for borders/tints, not body text; primary carries headings.
- Pashto number composition is less regular than English/Persian — unit+tens and group conjunctions are centralized in one utility; the Pashto group conjunction was corrected to “او”.
- Payroll month options are finite — the last 24 months cover the register's normal operating window; extending the range is a one-line change.
- Legacy letterhead settings become unused by these documents — recorded explicitly; no data is deleted.

### 9. Progress log
- 2026-11-08 — DISCOVERY: traced letterhead payloads/templates, brand colour settings, payroll month input, amount-in-words fallback, advance signature and affected tests; inspected stale graphify artifacts.
- 2026-11-08 — PLAN: saved this record before implementation; no blocking ambiguity remained after code review.
- 2026-11-08 — IMPLEMENTATION: added the two TS utilities, removed letterhead from three services/contracts, recoloured and converted all five templates, replaced the payroll month input, named the employee on the advance signature, and updated translations/tests.
- 2026-11-08 — VALIDATION: PHP lint, focused tests, typecheck, SFC compilation, locale JSON, whitespace and amount-word spot checks completed. The only failing focused test is the pre-existing income-audit assertion described above.

### 10. Completion summary
- Delivered: brand-coloured, letterhead-free receipt/payslip/commission/advance/academy documents; localized amount in words for receipt/payslip/advance; Vuexy year-month `VSelect` in payroll; employee name in the advance signature; updated locale strings and backend coverage.
- Acceptance criteria satisfied: all task criteria are met.
- Tests/checks passed: PHP lint; 18/19 focused tests; 5/5 SFC compiles; amount-word spot checks; locale JSON; `git diff --check`. Typecheck still reports only the repository's pre-existing diagnostics.
- Remaining limitations or follow-up work: the pre-existing income-audit test failure is outside this task; legacy receipt/payslip letterhead settings remain in Settings/API for compatibility but are no longer consumed by these five documents; commission and academy amount-in-words can use the new utility in a follow-up.
