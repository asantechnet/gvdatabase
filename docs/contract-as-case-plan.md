# Contract as the case — applicant-case merge (bonus task)

**Status:** planned — 2026-10-07
**Parent plan:** `visa-firm-simplification-plan.md`
**Request:** merge the Applicant Visa Case feature into Contracts. A contract *is* an individual case. The name "applicant case" must not appear in the system anymore, and there is no applicant document selection (no per-applicant document picker / readiness gate).

---

## 1. Interpretation and acceptance criteria

| # | Request | Acceptance criteria |
|---|---|---|
| B1 | "take a contract as an individual case" | The contract detail screen is the case workspace: applicant data, service, fee/currency, payments + receipts, tasks, attachments, stage, notes, and the printed agreement live on one page reached from the contract. |
| B2 | "the name applicant case should not appear in my system no more" | No route, page title, nav label, permission label, translation string, search group, dashboard/report heading, or code identifier says "applicant case". The vocabulary is **contract**. |
| B3 | "I do not need any kind of document selection" | The applicant-document feature is removed: no `applicant_attachments` table, `ApplicantAttachment` model/controller/resource, `/applicants/{applicant}/attachments` routes, upload/list/rename/delete UI, document-labelled picker, or document-readiness gate. Task attachments are unaffected. |
| B4 | Contract lifecycle is the case lifecycle | Stage/status, cancellation, completion and the pipeline/kanban view are contract dimensions, not applicant dimensions. |

### What "document selection" means here

The system has two unrelated things that could be called documents:

1. **Applicant documents** — `applicant_attachments` + `ApplicantDocumentReadiness`, uploaded per applicant, labelled, gating case stage changes, service assignment and invoicing. **This is what is removed (B3).**
2. **Document designs / artwork** — `ContractDocumentDesign`, the per-contract-type cover and page artwork, plus the print pipeline. **This stays**: it is what makes the contract printable, and removing it would break document printing.

Flagging this explicitly because the two are easy to conflate. If the intent was (2), the change is much smaller and I will re-scope.

---

## 2. Target structure

**Stages move to contracts.** `applicant_stages` / `applicant_stage_history` become `contract_stages` / `contract_stage_history` keyed by `contract_id`. A contract carries `current_stage_id` and its immutable transition history; the applicant no longer has a stage.

**One case screen.** `resources/ts/pages/applicant-case.vue` is retired and replaced by `resources/ts/pages/contract/contract-case.vue` at `/contracts/:id`, showing:

| Panel | Content |
|---|---|
| Header | Contract number, built-in service, status, applicant, responsible employee, marketer |
| Applicant | Identity + the per-service detail fields (read + edit under `edit_applicants`) |
| Money | `applicant_contract` fee / currency / rate / discount / paid / remaining / payment status with the finance override action |
| Payments | Income receipts for that line with print action |
| Tasks | Tasks linked to the contract and to its `applicant_contract` line; complete/create inline |
| Documents | Uploaded contract files and the printable agreement (artwork stays) |
| Stage | Current stage + allowed next moves, with history |
| Notes | Contract notes |

**Pipeline becomes a contract board.** `applicant-pipeline.vue` becomes `contracts-pipeline.vue` at `/contracts/pipeline`, grouping contracts by stage across the three built-in services, with a service filter. Drag/drop and the stage-move menu keep their existing permission checks.

**Applicant pages become a party directory.** `applicants.vue` stays as the directory of paying parties (used to prefill contracts and to attach incomes/expenses), but is stripped of case vocabulary, stage columns, document columns and pipeline links.

---

## 3. Backend work

* Migration `2026_10_07_000006_promote_case_to_contract.php`:
  * `contracts`: add `current_stage_id` (nullable FK), `status` already exists.
  * rename `applicant_stages` → `contract_stages`, `applicant_stage_history` → `contract_stage_history`; history FK `applicant_id` → `contract_id`.
  * drop `applicants.current_stage_id`; drop `applicant_attachments`; drop `applicant_universities` FK block only if the scholarship detail table takes over university tracking (keep the table otherwise — it feeds the visa-outcomes report).
* `Contract` model: `currentStage()`, `stageHistory()`, `canTransitionTo($key)`, `changeStage()`.
* `ApplicantStageController` → `ContractStageController` (`/contract-stages`), same validation and transitions.
* `ApplicantController::changeStage` → `ContractController::changeStage` at `/contracts/{contract}/change-stage`.
* `ApplicantTaskService::ensureForStage` → `ContractTaskService::ensureForStage` (fires on contract stage change; the per-line contract task from the parent plan is a separate, adjacent behaviour).
* Delete `ApplicantAttachmentController`, `ApplicantAttachment` resource/model, `ApplicantDocumentReadiness`, their routes, the readiness calls in `ContractGenerationService` and the portals, and the audit registry entries.
* `ApplicantPortalController`: keep the portal (applicant's own view) but re-point it at the applicant's contracts; drop the attachment endpoints and the document labels.
* Search, dashboard, reports, audit presenter: rename case vocabulary to contract; search group "Applicant cases" → "Contracts".

## 4. Frontend work

* Delete `applicant-case.vue`, `applicant-stages.vue`, `applicant-pipeline.vue` (replaced by the three files below), `components/ApplicantDocuments*` if any, `api/applicantAttachments` usage.
* Add `pages/contract/contract-case.vue`, `pages/contract/contracts-pipeline.vue`, `pages/setup/contract-stages.vue`.
* Router: `/applicants/:id` → redirect to the applicant's first contract; `/applicants/pipeline` → `/contracts/pipeline`; `/applicant-stages` → `/setup/contract-stages`.
* Navigation: "Applicants → Pipeline" becomes "Contracts → Pipeline"; remove the "Applicant stages" entry, add it under Setup → Contracts.
* `api/applicants.ts` / `api/applicantPortal.ts` / `api/contracts.ts`: stage and attachment methods move to contracts.
* i18n: rename every `applicantCase`, `pipeline`, `stage`, `documents` string that refers to the case across en/fa-AF/ps-AF; no "applicant case" string survives.

## 5. RBAC, audits, tests

* Permissions: `manage_applicant_stages` → `manage_contract_stages`; `view_applicant_attachments` / document permissions removed; `change_applicant_stage` → `change_contract_stage`. `RolePermissionSeeder` updated.
* Audits: `ContractStageHistory` replaces `ApplicantStageHistory` in `AuditRegistry`/`AuditPresenter`; attachment entries removed.
* Tests: `ApplicantWorkflowTest`, `ApplicantPipelineTest`, `ApplicantCaseViewTest`, `ApplicantUniversityTest`, `ApplicantTaskIntegrationTest` retarget at contracts; attachment tests deleted; new test asserting a stage change writes history and creates the stage task on the contract.

## 6. Ordering

1. Stage tables and stage engine move to contracts (backend + tests green).
2. Case screen and pipeline move to contracts (frontend + router + nav + i18n).
3. Applicant documents removed (backend + frontend + permissions + translations).
4. Vocabulary sweep: grep for "case", "applicantCase", "applicant case", "applicant_stage", "applicant-stage" and clear every remaining hit.
5. Verification: `npm run typecheck`, focused feature suite, manual walk of the three contract pages → case screen → stage move → task.

## 7. Progress log

| Date | Progress |
|---|---|
| 2026-10-07 | Interpreted the request, identified the applicant-documents vs contract-artwork ambiguity (§1), mapped the stage/pipeline/screen/vocabulary surface, and wrote this plan. Not started: depends on the parent plan's Phase B/C data model. |
