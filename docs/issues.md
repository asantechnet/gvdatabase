# issues.md — Cross-Agent Issue Tracker (Educational Visa Restructure)

> **Purpose**: Single coordination file for structural/logic defects found by the Phase 0 review agent. Other agents: read this, pick an issue, flip its **Status**, tick acceptance boxes when done. Do not delete issues — set status.
> **Rules**: Write access to this file is shared; keep edits surgical (your issue block only). Status values: `OPEN → IN_PROGRESS → FIXED (needs re-verify) → VERIFIED` or `WON'T FIX (reason)`.
> **Baseline reviewed**: commit `6d99824` (branch `main`), 2026-09-17. All file:line references below were re-checked against this HEAD.
> **Environment note**: local MySQL was not reachable during review (`php artisan test` → connection refused, DB `laravel_test`). Fixers must run suites with the DB up before flipping to VERIFIED.

---

## Status dashboard

| ID | Title | Sev | Status | Area files |
|---|---|---|---|---|
| LEGAL-01 | Educational contract types are blocked at every gate | P0 | OPEN | `ContractController.php` |
| LEGAL-02 | `applicant_id` never accepted on contract creation | P0 | OPEN | `ContractController.php` |
| LEGAL-03 | Draft edit re-renders from legacy template content, bypassing version | P1 | OPEN | `ContractController.php` |
| LEGAL-04 | Version content skips sanitizer + placeholder validation | P1 | OPEN | `ContractTemplateVersionController.php` |
| PIPE-01 | Cancelled/Rejected/Withdrawn stages unreachable; no back-one transition | P0 | OPEN | migration `2026_09_17_000000`, seme migration, `applicant-case.vue` |
| PIPE-02 | Applicant delete silently erases sales/bonus attribution | P1 | OPEN | `ApplicantController.php` |
| PIPE-03 | Stage dropdown offers all stages, then 422s | P2 | OPEN | `applicant-case.vue` |
| ACCESS-01 | Manager/Staff roles have zero permissions to the new domain | P0 | OPEN | `RolePermissionSeeder.php` |
| ACCESS-02 | No object-aware policies (assigned-only edit) | P1 | OPEN | new policies needed |
| DATA-01 | UNIQUE + soft-deletes collision on master data | P1 | OPEN | migration `2026_09_17_000000`, `EducationalMasterDataController.php` |
| FLOW-01 | Sale (scholarship/test offer) creates no money trail | P1 | OPEN | `EducationalServiceController.php` phases 5/7 |
| FLOW-02 | Sales list: no pagination, no update/destroy, free-text status | P2 | OPEN | `EducationalServiceController.php` |
| FLOW-03 | Stage-tasks land on arbitrary first board / silently skip when no board | P2 | OPEN | `ApplicantTaskService.php` |
| UI-01 | No way to create a case from the UI | P1 | OPEN | `applicants.vue` |
| UI-02 | Sale form asks users for raw numeric FKs | P1 | OPEN | `educational-services.vue` |
| UI-03 | Case page has no Finance / Notes sections (plan §10) | P2 | OPEN | `applicant-case.vue` |
| UI-04 | Timeline newest-first; plan wants chronological | P3 | OPEN | `Applicant.php` / case page |
| I18N-01 | New pages hard-coded English, no Dari/RTL | P2 | OPEN | all new pages |
| TST-01 | Every new test authenticates as `super_admin` — role blindness | P1 | OPEN | `tests/Feature/*` |

Phase compliance snapshot (against the Implementation Plan):

| Plan phase | Claimed | Actual |
|---|---|---|
| 1 Master data | ✅ | ✅ mostly (schema/API/UI exist) — DATA-01 caveat |
| 2–3 Versioned contracts + generation | ✅ | ⚠️ works for legacy types only — **LEGAL-01/02/03/04** |
| 4 Applicant case mgmt | ✅ | ⚠️ engine works, exceptions impossible — **PIPE-01**, UI gaps |
| 5 Scholarships/tests + bonus | ✅ | ⚠️ records exist, money severed — **FLOW-01/o2** |
| 6 Pending transactions | ✅ | ✅ fixed during review (see Fixed log); role grants still missing — ACCESS-01 |
| 12 Tasks integration | ✅ | ✅ good (see Fixed log) — FLOW-03 polish |
| 13 Pipeline UI | ✅ | ✅ board landed — depends on PIPE-01 for real data |

---

## P0 — Business-blocking

### LEGAL-01 — Educational contracts can never be generated
**Status:** OPEN · **Severity:** P0 · **Owner area:** `app/Http/Controllers/ContractController.php`
**Real-world scenario:** A student's father signs the agency agreement. Staff opens Contracts → cannot select the "Educational Service" template at all; if created any other way, creation 422s and lists hide it. The firm's core legal document is impossible.
**Root cause (current HEAD):** hard legacy slug gate in four places —
- `ContractController.php` L41–42 (`index()` filters to `employment|customer|company`)
- L164 (`update()` gate), L578 + L587 (`resolveTemplateAndType()` hard `abort_unless`)
- L383 (`templates()` lists only legacy templates for the creation UI)
- No educational `contract_types` rows exist anywhere (nothing seeds them).
**Required behavior:**
1. Central supported-type helper (one source of truth) allowing: `employment`, `customer`, `company` **+** `educational_service`, `scholarship_service`, `test_service`, `visa_service` (slugs; confirm names with business).
2. Educational slugs resolve customer context (like `customer`) **plus optional applicant**.
3. `index()` gains `audience=educational`; educational contracts also appear under customers audience (they *are* customer contracts for this firm).
4. Migration seeds the 4 contract types (idempotent, reversible).
**Acceptance:**
- [ ] Migration seeds 4 educational contract types
- [ ] API test: create template of type Educational Service → generate contract EN v1 → contract 201 with correct version id
- [ ] API test: educational contract absent from `audience=employees|companies` and legacy lists unchanged ↔ `audience=educational` shows only educational
- [ ] `templates()` returns educational templates
- [ ] `php artisan test --filter=Contract` green

### LEGAL-02 — Contracts can never be linked to an applicant
**Status:** OPEN · **Severity:** P0 · **Owner area:** `ContractController.php`
**Real-world scenario:** Manager opens the case page to see "what did this student sign" → Contracts tab is empty forever. Plan §14 reverse navigation (Applicant→Contract→Payment) has no first link.
**Root cause:** `contracts.applicant_id` exists (migration `2026_09_17_000000` L106) and is fillable on the model, but `ContractController::validatedData()` never lists `applicant_id` (zero matches in file) → silently dropped on create/update.
**Required behavior:** accept `applicant_id` nullable; **must** validate `applicant.customer_id === contract.customer_id` — copy the exact pattern already written in `PendingTransactionController::validated()` (applicant-must-belong-to-customer check). Case page Contracts tab then populates.
**Acceptance:**
- [ ] Create contract JSON with `applicant_id` → persisted, visible via `GET /applicants/{id}` contracts
- [ ] Mismatched customer/applicant pair → 422
- [ ] Legacy contract tests still pass

### PIPE-01 — Cancelled / Rejected / Withdrawn cases are impossible; no rework transitions
**Status:** OPEN · **Severity:** P0 · **Owner area:** data migration + `applicant_stages` rows
**Real-world scenario:** (a) Embassy refuses the visa at *Visa Processing* after months of fees — staff cannot mark it Rejected; case rots in "Visa Processing" forever. (b) Embassy returns documents for correction — cannot go back to *Documents Required*. (c) Student quits — cannot Cancel. The new pipeline board even ships a **Cancelled board filter** (`ApplicantController::applyFilters()` L81) that will return zero rows forever.
**Root cause:** seeded `allowed_next_keys` (migration `2026_09_17_000000` L120–136) is a strictly linear chain; no stage lists `cancelled/rejected/withdrawn` or the previous stage; `ApplicantStage::allowsTransitionTo()` enforces it (`app/Models/ApplicantStage.php` L16–19).
**Business decision (owner confirmed 2026-09-17):** from any non-terminal stage, allow: **forward-one, back-one, and → Cancelled / Rejected / Withdrawn**. Terminals stay locked (reopen = new case or admin-only). Skip-ahead stays blocked.
**Required behavior:** data-only migration rewriting `allowed_next_keys` per that rule (match on `key`, not id). Controller needs no logic change. Keep `changeStage` refusing moves **from** terminal stages (already does).
**Acceptance:**
- [ ] API test: Visa Processing → Rejected with note → history row recorded, status closed
- [ ] API test: Documents Complete → Documents Required (back-one) allowed
- [ ] API test: New → University Application still blocked
- [ ] API test: move from Completed → rejected
- [ ] Cancelled filter on board/list returns the cancelled case

### ACCESS-01 — No seeded role can actually operate the visa workflow
**Status:** OPEN · **Severity:** P0 · **Owner area:** `database/seeders/RolePermissionSeeder.php`
**Real-world scenario:** The firm's manager logs in → cannot open Applicants, cannot approve a transaction (Scenario C needs two different people: creator ≠ approver), cannot move a case. A staff agent → sees nothing; can't create a case or record a sale. Today the entire educational module is admin-only, which also *breaks* the segregation-of-duties the Phase 6 code carefully enforces (only admin can approve ⇒ admin must approve their own creation, which `approve()` blocks → deadlock, or bypass).
**Root cause:** `RolePermissionSeeder.php` `manager` and `staff` grant arrays contain **none** of: `view/create/edit/delete_applicants`, `change_applicant_stage`, master-data perms, `view/create/approve/post_pending_transactions`, `*_contract_template_versions`, `view_services`-for-sales paths, etc. (catalog has them; grants don't).
**Required behavior (proposed grants — business to confirm):**
- `manager`: all applicant perms + master-data view/create/edit + universities + template versions create/activate + approve/post pending transactions + view/edit official finance.
- `staff`: `view/create/edit_applicants`, `change_applicant_stage`, `view/create_pending_transactions`, master-data **view only**, sale-recording perms, `view_contract_template_versions`.
  Use the exact permission names already catalogued in `database/seeders/PermissionSeeder.php` (L219–247) — copy strings from there, do not invent.
- Keep admin as-is; document a manager user + two staff users in a seeder so Scenario C (creator ≠ approver) is testable out of the box.
**Acceptance:**
- [ ] `php artisan db:seed` (roles) idempotent, grants present
- [ ] New tests: manager token approves a pending transaction created by staff (200); staff token approving own creation 422; staff token creates case + changes stage (200); viewer token writes 403
- [ ] Scenario C from the plan passes end-to-end with **non-admin** tokens

---

## P1 — Logic incomplete vs plan / dangerous in production

### LEGAL-03 — Editing a draft contract re-renders from legacy template content
**Status:** OPEN · **Severity:** P1 · **Owner area:** `ContractController.php`
**Real-world scenario:** Versioned v2 is activated with corrected refund wording; an existing draft is edited and its body silently reverts to the template's old base `content` — the signed-text-of-record stops matching the version the firm believes governs it.
**Root cause:** `update()` L209: `$content = $template?->content ?? ...` — never uses `$contract->contract_template_version_id` when set, while creation path correctly renders from the chosen version.
**Required behavior:** when `contract_template_version_id` is set, re-render from that version's content (same resolver used by store); block re-render of non-draft contracts per existing rules; keep `snapshot_data.template_version` in sync.
**Acceptance:**
- [ ] Test: draft created from EN v1 → v2 activated → draft update still uses v1 content (version pinned) unless explicitly moved to v2
- [ ] No path renders content from `$template->content` when a version id exists

### LEGAL-04 — Version content bypasses sanitizer and placeholder validation
**Status:** OPEN · **Severity:** P1 · **Owner area:** `ContractTemplateVersionController.php`
**Real-world scenario:** Legal drafts a version with a typo'd placeholder `{{contsract.total}}` or pasted `<script>`/malformed HTML from Word → the generated customer contract ships it broken or unsafe.
**Root cause:** `store()` saves `content` raw. The legacy `ContractTemplateController::sanitizeContent()` (L175–195) + `PlaceholderResolver`/`PlaceholderRegistry` validation exist but are not applied to versioned content.
**Required behavior:** extract the sanitizer to a shared service (or reuse `ContentSanitizer`) and validate placeholders against `PlaceholderRegistry` for the template's contract type before saving a version; reject unknown placeholders with a clear error listing them.
**Acceptance:**
- [ ] Version create with unknown placeholder → 422 naming it
- [ ] Unsafe HTML stripped exactly as legacy path does
- [ ] Existing version tests still pass

### PIPE-02 — Deleting an applicant silently erases sales/bonus attribution
**Status:** OPEN · **Severity:** P1 · **Owner area:** `ApplicantController.php` L45
**Real-world scenario:** Staff misclicks delete on a case that has a recorded scholarship sale and an employee bonus. Contracts block is checked, but `scholarships`, `test_offers`, `educational_bonus_sources` rows are soft/FK `nullOnDelete` → the money records survive pointing at *nothing*. Payroll later pays against orphaned bonus rows; reports lose the case dimension.
**Required behavior:** block destroy when scholarships/test-offers/tasks reference the applicant (409 with the blocking count, same shape as contracts); if business wants deletion anyway, require a manager permission and hard-block when any finance linkage exists.
**Acceptance:**
- [ ] Delete case with a scholarship sale → 409
- [ ] Delete case with tasks → 409
- [ ] No orphan rows possible after these paths

### ACCESS-02 — No object-aware authorization (Phase 33)
**Status:** OPEN · **Severity:** P1 · **Owner area:** new Policies + `Auth::guessPolicyName` wiring
**Real-world scenario:** Two case officers both hold `edit_applicants`; either can rewrite the other's cases (and in a firm paying commission on sales, tamper with attribution). The plan explicitly requires assigned-only editing for non-managers.
**Root cause:** authorization is route-middleware `permission:<name>` only — global, never object-scoped; no policies for `Applicant`, `Scholarship`, `TestOffer`, `PendingTransaction`.
**Required behavior:** policies where non-managers may update only records they created or are responsible for (`responsible_employee_id → user_id`); managers/admins bypass. Reuse existing `EditWindow`/audit plumbing for overrides — it is good.
**Acceptance:**
- [ ] Staff A updates Staff B's case → 403; responsible officer → 200
- [ ] Manager updates any case → 200
- [ ] Policy tests for the 4 models

### DATA-01 — UNIQUE + soft-deletes: deleted codes can never be reused
**Status:** OPEN · **Severity:** P1 · **Owner area:** migration `2026_09_17_000000` + `EducationalMasterDataController` validation
**Real-world scenario:** Staff deletes country "Afghanistan (old)" or master list entry with code `AF`, re-adds the same code a week later → raw SQLSTATE[23000] duplicate-key 500, not a friendly validation error, because MySQL unique indexes ignore `deleted_at`. Validation (`Rule::unique(...)`, no `whereNull('deleted_at')`) also wrongly blocks re-adding a soft-deleted name *before* the query ever hits the index.
**Required behavior:** either migrate UNIQUEs to composite `(code, deleted_at)`-style / plain indexes with PHP-level "active uniqueness", or hard-delete master rows when unused. The `isInUse()` guards are correct — keep them. Also fix universities composite uniqueness (country + name) with the same posture.
**Acceptance:**
- [ ] Soft-delete a master row → recreate same code/name succeeds via API
- [ ] Two *active* rows with same code still rejected with 422 (not 500)
- [ ] Universities: same name in two countries allowed; duplicate in one country rejected

### FLOW-01 — A sale records no money (bonus hardcoded 0, no revenue link)
**Status:** OPEN · **Severity:** P1 · **Owner area:** `EducationalServiceController.php` + Phase 7 rule engine
**Real-world scenario:** The firm's actual month: agent closes a scholarship service, firm invoices the family the service fee, agent gets a bonus rule percentage. Today `store()` writes `bonus_amount = 0`, `rule_source = 'manual_rule_pending'` and **zero** financial records — no invoice, no income, no pending transaction. The bonus must be retyped by hand into a pending transaction with the same customer/applicant, with no cross-check that they match (unlike PendingTransactionController which *does* cross-check applicant↔customer — reuse that pattern on store()).
**Required behavior (minimum for this phase):** validate `applicant_id` belongs to `customer_id` on sale store; then (Phase 7) drive bonus from a rule source (extend the *existing* commission ledger which supports multiple recipients — plan says extend, don't fork) and open the revenue record or pending transaction from the sale.
**Acceptance:**
- [ ] Sale store with mismatched applicant/customer → 422
- [ ] Bonus no longer accepts a hardcoded 0 without a rule marker when a rule exists
- [ ] Phase 7 ticket: sale → draft pending-transaction prefilled (customer, applicant, currency, amount)

### UI-01 — A case cannot be created from the UI
**Status:** OPEN · **Severity:** P1 · **Owner area:** `resources/ts/pages/applicants.vue`
**Real-world scenario:** Walk-in student's brother comes to the office; the front desk cannot open a case — `applicants.vue` has search, board tabs, pagination and a pipeline link, but **no create action**. API exists (`POST /api/applicants`); UI never calls it. The firm's day-1 flow (Scenario A) is UI-broken.
**Required behavior:** create dialog → customer searchable-picker (name/contact, existing `customers` API; option to quick-create), responsible employee, country/university education level/language pickers from the master-data endpoints, application date, notes. Localized labels.
**Acceptance:**
- [ ] Front desk creates a case end-to-end without touching an ID
- [ ] New case appears in default board column with correct count

### UI-02 — Sale form demands raw numeric PKs
**Status:** OPEN · **Severity:** P1 · **Owner area:** `resources/ts/pages/educational-services.vue`
**Real-world scenario:** Recording a scholarship sale today means knowing the Customer **ID**, Employee **ID**, Currency **ID**, Package type **ID** (VTextField `type="number"` for each; list renders `#7`, `#3`...). Staff will key the wrong customer on a 200,000-AFN sale.
**Required behavior:** searchable selects fed by existing endpoints (customers, employees, currencies, package types, applicant-by-customer); table shows names + formatted fee with currency code; status as localized chip.
**Acceptance:**
- [ ] Zero raw-ID inputs on the sale dialog
- [ ] List shows customer/employee names, fee + currency

### TST-01 — Role-blind test suite
**Status:** OPEN · **Severity:** P1 · **Owner area:** `tests/Feature/*`
**Evidence:** `VersionedContractGenerationTest` setUp attaches `super_admin`; `ApplicantWorkflowTest`, `ApplicantPipelineTest`, `ApplicantTaskIntegrationTest`, `PendingTransactionTest` likewise — green tests have never exercised the permission middleware paths or educational contract types (the version test uses legacy `customer` slug). ACCESS-01 would have been caught by one manager-token test.
**Required behavior:** for each new domain, at least one happy path as `manager`, one as `staff`, one 403 as `viewer`; contracts tests add an educational-type case (blocked until LEGAL-01).
**Acceptance:**
- [ ] Role matrix tests exist and pass (needs ACCESS-01 first)

---

## P2 / P3 — Workflow polish and hygiene

### PIPE-03 — Stage dropdown offers illegal transitions, then 422s (P2, `applicant-case.vue` ~L123)
The stages `VSelect` maps *all* active stages; the backend already ships `allowed_next_keys` via `GET /applicants/stages` and `pipeline()`. Disable (or hide) illegal options for the current stage, and confirm-before-moving onto a terminal stage. *Also: switching `customer_id` on `update()` (ApplicantController::save L60) silently orphans existing contracts/sales under the old customer — warn or block when contracts exist.* (tracked here as 02b)

### FLOW-02 — Sales API is create-only and unpaginated (P2)
`EducationalServiceController::index()` returns `->get()` (grows forever); no `update`/`destroy` routes → a mis-keyed sale is immortal (Phase 21 requires full CRUD + EditWindow). Status is free text (`sometimes|string`) — Phase 31 wants a controlled vocabulary (chip vs free-entry).

### FLOW-03 — Stage-tasks land on the arbitrary first board (P2)
`ApplicantTaskService::ensureForStage()` L47: `Board::query()->orderBy('order')->orderBy('id')->first()` — "Documents Required" work lands on whichever board sorts first (could be *Outsourcing*), and when no board exists the service returns `[]` **silently** — the stage move succeeds and staff never learn no work was created. Seed/configure a dedicated "Applicant Cases" board; if missing, log a warning (or flag in the response).

### UI-03 — Case page missing Finance / Documents / Notes sections (P2)
Plan §10 requires them; `applicant-case.vue` has header/tasks/stage timeline only. Pending transactions carry `applicant_id` (queryable today); official Income/Expense have no applicant dimension yet — document the gap when wiring the Finance tab.

### I18N-01 — New pages are hard-coded English (P2)
`applicants.vue`, `applicant-case.vue`, `applicant-pipeline.vue`, `pending-transactions.vue`, `educational-services.vue` all bypass vue-i18n and RTL. The firm's staff work in Dari — every new string needs locale keys + RTL layout check.

### UI-04 — Timeline renders newest-first (P3)
Plan wants chronological case narrative; `show()` loads `stageHistory` unordered and the page renders reversed. Order by `changed_at asc`.

### MINOR (backlog)
- `Applicant.status` (`active|closed`) is stored truth derived from stage terminality — drift risk; derive via accessor or guard updates.
- New controllers return raw Eloquent models; app convention is API Resources (leaks fillables, breaks shapes on schema change).
- Legacy software-solutions modules (support tickets, outsourcing projects, etc.) still routed/nav-visible — Phase 17 should hide via navigation registry, **not** delete (historical data references them).
- `ApplicantController::applyFilters()` search over `notes LIKE %..%` — fine now; revisit with scout later.

---

## Fixed log (verified during this review — do not re-report)

| Was | Fixed by | What is now true |
|---|---|---|
| Pending-transaction `approve()` could resurrect rejected records; creator could self-approve; `post()` accepted unapproved txns; posting lost linkage | `9284011` | `approve()` blocks creator (`requested_by === actor` → 422 `business.pending_transaction_self_approval`) and `transition()` locks + enforces from-state (draft/submitted/under_review only); `post()` requires APPROVED, is idempotent via `posted_at` under `lockForUpdate`, guards currency rate, writes `pending_transaction_id` onto Income/Expense/Salary; `validated()` enforces salary⇒employee and applicant⇔customer consistency |
| Contract creation ignored `language_id` (always one version) | landed in `ContractController` create path | `language_id` accepted; current version resolved **per language**; mismatch aborts; `snapshot_data.template_version` records the used version |
| No pipeline board / no stage task automation *(earlier review said "missing")* | `9cd894e`, `6d99824` | Working kanban (`GET /applicants/pipeline`, board filters) + idempotent `ApplicantTaskService` stage→task creation with responsible-party assignment |
| Pending approvals had no workspace | `9dfb16b` | `pending-transactions.vue` workspace exists (see UI/Open issues for what it still lacks) |

## Reviewer notes / not verified
- **Could not execute tests here** — MySQL down. Static review only; every "green" claim above is from reading code, not running it. Fixers run suites before marking VERIFIED.
- Business confirmations captured 2026-09-17: (1) forward-one + back-one + terminate-to-cancelled/rejected/withdrawn; (2) seed exactly the four educational contract types.
- Coordination: parallel agents actively commit — re-check `git log` before starting an issue; if its file was rewritten since `6d99824`, re-verify the finding first and note the commit hash in the issue block.