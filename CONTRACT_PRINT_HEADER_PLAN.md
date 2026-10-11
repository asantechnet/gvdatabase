# Contract Print Header + Editor Tables — Plan & Progress

Status: **in progress** (2026-10-05)

## Goal

Add an **optional** "contract details header" to contract printing. When the user
ticks a checkbox in the print-options dialog, the printed A4 document shows a
polished header block at the top of the first content page containing:

1. **Contract title** — written separately on top.
2. **Contract date** (start date) — written separately, next to the title.
3. **A counterparty data section** with the fields relevant to the contract type.

The header is opt-in and never changes an already issued contract's stored
content; it is assembled at print time from the contract + counterparty record.

### Employment contracts header fields
employee name, education, phone, emergency contact, NID, position, salary,
date of contract, contract duration, paid leave period.

### Customer contracts header fields
applicant name, father name, education level, phone, semester, education score,
amount payable (sum of the related contract service fees + currency).

### Bonus
Allow inserting tables in the contract template **content editor** and the
**policy editor** (they share `ContractContentEditor.vue`).

## Data reality check

| Field | Where it lives today | Action |
| --- | --- | --- |
| employee name / NID / phone / position / salary / contract duration | `employees` (`name`, `id_number`, `contact`, `position`, `salary`, `contract_duration`) | reuse |
| employee education | `employees.education_lvl` | reuse (expose in snapshot/resource) |
| employee emergency contact | **missing** | add `employees.emergency_contact_name` + `emergency_contact_phone` |
| employee paid leave period | **missing** | add `employees.paid_leave_days` |
| contract date | `contracts.start_date` | reuse |
| applicant name / father name / phone / education score | `applicants` (`name`, `father_name`, `contact`, `percentage`) | reuse |
| applicant education level / semester | `education_levels` / `semesters` relations | eager-load + expose |
| amount payable | `contract_service.fee` + currency | sum in the header builder |

No new **applicant** columns were required — the applicant record already owns
every requested customer field.

## Plan

### Backend
1. Migration adding `emergency_contact_name`, `emergency_contact_phone`,
   `paid_leave_days` to `employees` (nullable, non-destructive).
2. `Employee` model: fillable + casts.
3. `EmployeeController` store/update validation for the new fields.
4. `EmployeeResource`: expose the new fields.
5. `ContractController`: eager-load `applicant.educationLevel`, `applicant.semester`,
   `applicant.country`; extend `employeeSnapshot()` with education / emergency
   contact / paid leave / duration; extend the customer snapshot with father
   name, percentage, education level and semester names.

### Frontend
6. `resources/ts/utils/contractPrintHeader.ts` — typed builder returning
   `{ title, date, meta[], sections[] }` per contract type.
7. `resources/ts/components/contract/ContractPrintHeader.vue` — polished,
   RTL/LTR safe, print-safe header block.
8. `ContractPageTemplate.vue` — optional `headerDetails` prop rendered above the
   contract body on the first page.
9. Wire an `includeContractHeader` checkbox into **both** print paths
   (`components/contract/ContractDocumentPrinter.vue` and
   `pages/contract_list.vue`) and pass the built header to page 1 only.
10. i18n keys (`en`, `fa-AF`, `ps-AF`).

### Bonus
11. Add TipTap table extensions + toolbar controls to `ContractContentEditor.vue`
    (shared by the main template editor and the policy editor).
12. Confirm the backend `ContentSanitizer` keeps table markup (verified: it only
    strips script/iframe/object/embed/form/link/meta + event handlers).

## Progress log
- [x] Reconnaissance of print paths, page template, party details, models.
- [x] Plan document created.
- [x] Backend migration/model/resource/validation.
- [x] Contract snapshot + eager loads.
- [x] Header utility + component.
- [x] Print-path wiring + checkbox.
- [x] i18n.
- [x] Optional contract end date (migration/model/resource/validation/form).
- [x] Employee form fields for emergency contact + paid leave (staff_list).
- [x] Editor tables (bonus).
- [x] Typecheck + verification.

## Follow-up added mid-task
- Optional `contracts.end_date` (nullable). When set, the printed header derives
  the contract duration from `end_date - start_date` and also shows the contract
  period as a start–end range. Falls back to the employee's free-text
  `contract_duration` when no end date exists.

## Files touched
Backend: migration `2026_10_05_000001_add_contract_header_fields_to_people.php`,
`Employee`, `Contract`, `EmployeeResource`, `ContractResource`,
`EmployeeController`, `ContractController`.
Frontend: `composables/useContractPrintHeader.ts`,
`components/contract/ContractPrintHeader.vue`,
`components/contract/ContractDocumentPrinter.vue`, `pages/contract_list.vue`,
`pages/templates/contract_page_template.vue`, `components/ContractForm.vue`,
`components/contract/ContractContentEditor.vue`, `pages/staff_list.vue`,
`api/contracts.ts`, `api/employee.ts`, `utils/apiValidation.ts`, i18n locales
(`en`, `fa-AF`, `ps-AF`).

## Follow-up fixes (2026-10-05)
- The header toggle looked missing because `pages.contracts.shared.header` was
  declared twice in each locale JSON: the pre-existing minimal block
  (`contractDate`, `contractDetails`) came **after** the new full block, so JSON
  last-wins shadowed every new key and left the checkbox label/hint blank.
  Removed the trailing duplicate in `en`, `fa-AF`, `ps-AF`; the full block now
  resolves (30 keys).
- `CounterpartyCard.vue` now hosts optional, editable contract **start/end date
  selectors** (plus a read-only fallback) and is wired from `ContractForm.vue`,
  which no longer renders the two separate date columns.

## Header availability
- The toggle is now available for **every** contract (`canPrintContractHeader`
  just requires a contract). Employment/customer contracts keep their typed
  sections; all other types (company / other / custom) get a generic
  counterparty section built from the frozen snapshot or the free-text
  counterparty fields, plus the shared title / dates / meta.
- New header i18n keys: `counterpartyDetails`, `counterpartyName`, `company`,
  `representative`, `email`, `address`.

## Verification
- `npm run typecheck` — no new errors in any touched file; the single remaining
  `ContractForm.vue(506,36)` error is pre-existing and unrelated.
- `php -l` — all touched PHP files pass.
- Locale JSON files parse.

## Notes
- A4 stays the page size; the header is part of page 1, so pagination counts do
  not change (unlike the existing separate "contract parties" page).
- The two print paths duplicate the dialog + hidden print body; both must be
  updated in lockstep.
- Keep `@page` rules in the existing print paths; the header adds no `@page`.
