# Service → Contract linkage: one service, one contract, one template

**Status:** in progress — plan written 2026-09-27
**Area:** service catalogue, contract generation fronts (educational workspace, applicant case,
contracts page, package issuance), contract templates, notifications / RBAC / audits / i18n.
**Related:** `contract-template-sanifier.md` (artwork scoping), `customer-applicant-merge.md`.

---

## 1. What was asked (intent → acceptance criteria)

| # | Request | Acceptance criteria |
|---|---------|---------------------|
| AC1 | **Every service has a contract template.** Assigning a service to a customer must create that service's contract automatically. | Each active service resolves to exactly one primary `ContractTemplate` (`service_id = service.id`); the assignment front generates a `Contract` from it (template content + version + placeholders rendered). |
| AC2 | **1:1 service ↔ contract.** One service per contract, one contract per service. | Enforced at the line level today (`contract_service`, `single_service_per_contract`). A contract also records the service it was generated for (`source_service_id`) so the "contract for this service" is directly retrievable. |
| AC3 | **Repeat sales allowed, related as extensions.** | A second sale of the same service to the same applicant is allowed and becomes an **extension contract** linked to the primary via `parent_contract_id` / `contract_role = 'extension'` (only when the primary is still live). |
| AC4 | **Price is editable per customer.** | The sale/line `fee` defaults from the service base price and stays editable; the general contract form also exposes it. |
| AC5 | **Other systems comply** — UI/UX, translations, notifications, permissions/RBAC, audits. | All fronts use one generator; permission checks reused/extended; a notification fires on generation; en/fa-AF/ps-AF keys added; audited models log changes. |
| AC6 | **No contract template ⇒ auto-create one.** | If a service has no template, a draft template is created from the service (name + default customer content) and used. |
| AC7 | **Service catalogue binds the template.** | The services page shows the bound template and can create/open it. |

**UI/UX requirement (explicit):** the assignment dialogue must stay fluent and usable — a user picks a
customer + service, sees the base price pre-filled (editable), sees which template/contract will be
produced, and is warned about (and can opt into) an extension when a primary contract already exists.

---

## 2. Current state audit (evidence)

### 2.1 What already holds
* **Contract ↔ service is 1 service per contract**: `database/migrations/2026_09_24_000001_single_service_per_contract.php`;
  `ContractController::validatedData()` caps `service_ids`/`service_lines` at `max:1` and
  `resolveContextEntity('customer')` aborts unless exactly one service is present.
* **`ContractTemplate` already has `service_id`** (`app/Models/ContractTemplate.php`) and customer templates
  already require a service (`ContractTemplateController::validated()` `after` hook). Templates are
  versioned per country/language (`ContractTemplateVersion`).
* **Price lives on the line**: `contract_service.fee` is editable through `ContractController::update`
  and `EducationalServiceController::update`.
* **Document artwork** already resolves per contract type/service (`ContractDocumentDesignService`).

### 2.2 The gap
* `EducationalServiceController::store()` creates a `Contract` with `content => ''`, `is_custom => true`,
  and never looks at the service's template → the generated contract has no document.
* `PackageIssuanceService::issue()` does the same per packaged service.
* There is no `source_service_id`, no primary/extension notion, and the service catalogue has no
  template binding UI.
* Every front duplicates the "create a contract for a service" logic.

### 2.3 Key files
* `app/Http/Controllers/EducationalServiceController.php` (workspace assignment front)
* `app/Services/PackageIssuanceService.php` (package issuance)
* `app/Http/Controllers/ContractController.php` (`createContract`, `resolveContextEntity`, `buildCounterparty`, `versionSnapshot`, `normalizeServiceLines`, `persistContractServices`)
* `app/Http/Controllers/ContractTemplateController.php`, `app/Models/ContractTemplate.php`, `ContractTemplateVersion.php`
* `app/Models/Service.php`, `Contract.php`, `ContractService.php`
* Frontend: `resources/ts/pages/educational-services.vue`, `resources/ts/pages/options/services.vue`,
  `resources/ts/components/ContractForm.vue`, `resources/ts/pages/applicant-case.vue`,
  `resources/ts/api/educationalServices.ts`, `resources/ts/api/services.ts`

---

## 3. Target behaviour

### 3.1 Service → template (1:1)
* `Service::primaryContractTemplate()` returns the newest **active** template whose `service_id` is the
  service (customer-type template).
* `ServiceContractGenerator::templateFor(Service)` returns it, or **creates** a draft template from the
  service (`"{service.name} Agreement"`, customer type, default content with placeholders, cover text).

### 3.2 Service assignment → contract
`ServiceContractGenerator::createFor(Service, Applicant, options)`:
1. resolve the service's template (auto-create if none);
2. pick the active template **version** for the applicant's country/language, else the latest active
   version, else the template's inline content;
3. render placeholders (reuse `PlaceholderResolver` + `PlaceholderRegistry` for the `customer` slug);
4. build counterparty + snapshot (`parties`, `entity`, `template_version`, `document_design`);
5. create the `Contract` (`source_service_id = service`, `contract_role = primary|extension`,
   `parent_contract_id`, template + version ids, rendered content, resolved type);
6. create the single `contract_service` line with the fee (default = service base price / package
   allocation) and attribution.

**Extension rule:** if a live primary exists for the same `(applicant, service)` the new contract is
created as an **extension** (`contract_role = 'extension'`, `parent_contract_id = primary.id`) unless the
caller explicitly blocks it. The primary is never modified.

### 3.3 Fronts wired to the generator
| Front | Change |
|---|---|
| Educational workspace (`EducationalServiceController::store`) | replace the manual contract creation with `createFor()`; surface template + contract number + extension notice in `present()` |
| Package issuance (`PackageIssuanceService::issue`) | each packaged service's contract is generated through `createFor()` with `customer_package_id` metadata |
| General contracts page (`ContractController::store`) | when no `template_id` is supplied but a service is, derive the template from the service before creating |
| Applicant case "New contract" (`ContractForm.vue`) | selecting a service auto-selects the service's template and pre-fills the fee |
| Service catalogue (`options/services.vue`) | show the bound template + an action to create/open it |

### 3.4 Compliance
* **Permissions/RBAC:** reuse `manage_services` for the catalogue binding, existing
  `manage_educational_services` / `create_contracts` for assignment. No new permission unless the
  binding needs its own gate (documented).
* **Notifications:** `EducationalWorkflowNotificationService::contractGenerated()` notifies the
  generating user; called from the generator fronts.
* **Audits:** `Contract`/`ContractService` already use `Auditable`; new columns are covered by existing
  `audit` scopes.
* **i18n:** new keys in `en.json`, `fa-AF.json`, `ps-AF.json`.
* **UI/UX:** fluent dialogs, inline template/extension status, non-blocking notices.

---

## 4. Data model changes

New migration `2026_09_27_000001_link_contracts_to_service_contracts.php`:

| column | table | purpose |
|---|---|---|
| `source_service_id` | `contracts` | the service the contract was generated for (nullable FK, nullOnDelete) |
| `contract_role` | `contracts` | `primary` (default) / `extension` |
| `parent_contract_id` | `contracts` | self FK for an extension → its primary (nullOnDelete) |

Indexes: `(applicant_id, source_service_id, contract_role)` for primary lookup, `parent_contract_id`.

`ContractTemplate.service_id` already exists; no schema change (a code-level helper picks the primary
template per service).

---

## 5. Implementation steps

### Backend
- [ ] **B1** Migration + `Contract` fillable/casts/relations (`sourceService`, `parentContract`,
  `extensions`, `isExtension()`), constants for roles.
- [ ] **B2** `Service` relations: `contractTemplates()`, `contractTemplate()` (latestOfMany),
  `primaryContractTemplate()`.
- [ ] **B3** `App\Services\Contracts\ServiceContractGenerator`:
  `templateFor()`, `createFor()`, `existingPrimaryFor()`, `nextRoleFor()`.
- [ ] **B4** Wire `EducationalServiceController::store` + `present()` (contract number, role, parent).
- [ ] **B5** Wire `PackageIssuanceService::issue` through the generator.
- [ ] **B6** `ContractController::store` derives the service's template when none is given.
- [ ] **B7** `ServiceController` binding endpoint + `ServiceResource` template info.
- [ ] **B8** Notification hook.

### Frontend
- [ ] **F1** `api/services.ts` + `Service` type: contract-template fields.
- [ ] **F2** `options/services.vue`: template column + create/open action.
- [ ] **F3** `api/educationalServices.ts`: contract number / role / template fields.
- [ ] **F4** `educational-services.vue`: template + pre-filled editable fee + extension notice.
- [ ] **F5** `ContractForm.vue`: service → template + fee auto-fill.
- [ ] **F6** `applicant-case.vue`: contract number + extension badge in the sold-services list.

### Compliance
- [ ] **C1** i18n keys (en / fa-AF / ps-AF).
- [ ] **C2** Notification + audit pass.
- [ ] **C3** Permissions review.

### Tests & verification
- [ ] **T1** Feature test: assignment generates a contract from the service template (auto-created when missing).
- [ ] **T2** Feature test: a repeat sale becomes an extension linked to the primary.
- [ ] **T3** Feature test: package issuance uses the generator.
- [ ] **V1** `vendor/bin/phpunit` for the touched suites; `npm run typecheck`; `npm run i18n:check`.

---

## 6. Constraints & risks
* The working tree carries a large uncommitted customer→applicant merge; edits stay surgical.
* `ContractController::createContract` stays the canonical creation path for template-driven creates;
  the generator mirrors its rendering rules for service-driven creates. Unifying the two into one
  factory is a documented follow-up (§4 `createContract` duplication).
* Auto-created templates are drafts; a human can refine them from the templates page.

---

## 7. Progress log

| step | result |
|---|---|
| Recon: service/contract/template models, assignment fronts, package issuance, UI | done — evidence in §2 |
| Plan written | done |
| **B1–B8 / F1–F6 / C1–C3 / T1–V1** | pending |
