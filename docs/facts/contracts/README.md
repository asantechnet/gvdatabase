# Contracts

Current behavior reviewed 2026-11-11. Source owners are `ContractController`, `ContractGenerationService`, `ActiveContractService`, `ContractTaskService`, `ContractExpiryNotificationService`, `ContractTemplateController`, `ContractTemplateVersionController`, and the contract routes in `routes/api.php`.

## Built-in services and case ownership

The firm sells four fixed built-in services (`BuiltInService::ALL`):

| Service | Case-bearing | Contract permission |
| --- | --- | --- |
| `scholarship` | Yes | `view_scholarship_contracts` |
| `test_offer` | Yes | `view_test_offer_contracts` |
| `private` | Yes | `view_private_contracts` |
| `academy` | No (direct agreement) | `view_academy_contracts` |

Services are stored as strings, not catalogue rows. The removed `services` table and invoice/payment-request/counterparty/commission-ledger stack must not return.

A case-bearing contract creates an applicant case and a `new` stage per service line. Academy is the only case-free service: it creates an Academy enrollment, not an applicant stage. Test offer owns a stage workflow like the admission services.

## Contract and service-line model

`contracts` / `Contract`:

- Statuses: `draft`, `active`, `completed`, `terminated`, `cancelled`.
- Roles: `primary` and `extension` (`contract_role`). An extension must name a parent; a primary must not.
- Links: template, template version, contract type, country, language, selling employee, applicant, parent contract, snapshot data.
- Document number: `CTR-{yy}-{000}`.
- Cancellation: `is_cancelled` plus `cancelled_at`; a cancelled or terminated contract stops being active/earning.
- `snapshot_data` stores the issued applicant identity, service details, print details, template version and document design. Do not rewrite an issued snapshot when the applicant/catalogue changes.

`applicant_contract` / `ApplicantContract` is the service line and owns the commercial terms:

- `fee`, `discount`, `currency_id`, `currency_rate`, optional application/visa/booking/service/remote fees.
- `amount_paid`, `payment_status`, `amount_paid_overridden`, `payment_status_overridden`.
- `current_stage_id` for case-bearing services; `sold_by_employee_id`; notes.
- `payableTotal = max(0, fee − discount)`; `remainingTotal = max(0, payable − amount_paid)`.
- Payment state is recalculated from approved linked incomes. Explicit overrides are sticky; see [Finance](../finance/README.md).
- A service line's stage must match its own service/context, not another line's service.

## Creating a contract

`POST /contracts/{service}` requires `create_contracts` and an enabled built-in service. `ContractGenerationService::create()` runs inside a transaction:

1. For a new sale, create the applicant identity and the service-specific detail row (`scholarshipDetail`, `privateDetail`, `testDetail`, or `academyDetail`). For an extension, use the parent applicant instead.
2. Resolve the built-in contract template. The template's contract type must be active and have the `customer` slug.
3. Save the supplied currency rate through `CurrencyRateService`; an invalid rate aborts.
4. Create the `Contract` with the active template version, applicant/counterparty identity, seller and signatory.
5. Create the `ApplicantContract` line with fee, discount, currency/rate, fee components, seller and the initial stage for case-bearing services.
6. For Academy, create the enrollment (class, student, term, capacity, contract end date) through `AcademyEnrollmentService`. An Academy sale has no stage.
7. For Academy with `paid_fee > 0`, create an approved income receipt linked to the line; this requires `create_incomes`.
8. Render and sanitize the template content with PlaceholderResolver/ContentSanitizer. Unresolved placeholders block creation.
9. Store the immutable snapshot (parties, applicant, service line, Academy enrollment if any, template version, document design).
10. Create one delivery task per service line through `ContractTaskService` (idempotent per open line).
11. Optionally create the applicant portal account with the pre-generated credentials when `create_portal_user` is true; requires `invite_applicant_portal`.
12. Deliver assignment and contract-generated notifications.

Applicants are created with a `new` stage for case-bearing services and an initial stage history row. A legacy singular responsible employee and the plural `applicant_responsible_employees` set are supported; clearing the plural set retains the singular owner as the fallback.

## Editing a live contract

`PUT /contracts/{service}/{id}` requires `edit_contracts` and the service access permission.

- It updates the live applicant identity, service details, line fees/currency/rate, and counterparty fields.
- It updates the Academy enrollment when the service is Academy.
- It does **not** rewrite the issued contract snapshot or its contract number.
- A line that already has linked incomes cannot change currency (`business.contract_paid_line_immutable`).
- The applicant, line and contract must all belong to the same service; editing across services is refused.
- Responsible-employee assignment changes are authorized separately (`assign_applicant_employee`/`assign_responsible_employees`).

## Extensions and renewals

- An extension must name a parent contract of the same service. The parent must be `primary`, not cancelled, and `active` or `completed`; the applicant must use the same service.
- An extension reuses the parent applicant. Academy extensions may edit the shared applicant identity but must not overwrite the parent's per-case detail; the new enrollment carries its own term.
- A parent can only have one live extension (`draft`/`active`). The parent/role invariant is enforced at the model level.
- The extension gets its own contract number, line and case/enrollment. It does not create a second portal user for an existing applicant.
- `update` refuses changing `parent_contract_id` and a role that would break the primary/extension invariant.

## Cancellation, active-contract state and expiry

- `PUT /contracts/{id}/cancel` requires `cancel_contracts`. Cancellation sets the cancelled flag/date and status; do not delete a contract to end it.
- `ActiveContractService` defines an active contract as `status=active`, `is_cancelled=false`. Applicant-specific expense drafts require at least one active contract for the applicant.
- `contracts:send-expiry-alerts` warns when a non-cancelled `active` or `completed` contract ends inside the 30-day window. Recipients are the contract employee, seller and applicant's responsible employee, with a `view_contracts` fallback. See [Schedules](../schedules/README.md).
- Contract status changes re-sync commission. A cancelled/terminated contract voids unsettled accruals; settled money is not reversed.

## Templates, versions and document design

- `contract-templates` CRUD requires `view_contract_templates`/`manage_contract_templates`.
- Built-in templates can be ensured with `POST /contract-templates/built-in/{service}` under `manage_contract_templates`; the service gate must be enabled.
- Versions are separate rows. Reads use `view_contract_templates`; draft edits use `edit_draft_contract_template_versions`; activation uses `activate_contract_template_versions`; archiving uses `archive_contract_template_versions`; creation uses `create_contract_template_versions`.
- A non-Academy issuance may intentionally use an active historical version, but it must belong to the same template, language and applicant country/test scope. Academy resolves the current active version itself and has no language/version choice in the form.
- Placeholder definitions are exposed to the editor through `GET /contract-placeholders` (`view_contract_templates` or `view_contracts`).
- Rendered content is sanitized before storage. Unresolved placeholders abort with `business.contract_template_unresolved_placeholders`.
- Contract document designs are stored/snapshotted per contract type and service; `contract-document-designs` reads need `view_contract_types`, writes need `manage_contract_types`.
- Responsible-employee eligibility is edited through `contract-settings/responsible-roles` (`view_contract_templates`/`manage_contract_templates`), not through HR positions.

## Printing and documents

- Contracts use A4 sizing. Receipts, payslips and Academy slips use A5.
- Printing uses `resources/ts/utils/printDocument.ts` with a feature body class and the document's `@page` rule injected last.
- Never leave `@page` in a route/template stylesheet. Prefix global print selectors with the feature body class. See [Printing](../printing.md).
- Contract content is rendered from the stored snapshot/template version, not from the current applicant or catalogue, so a reprint remains historically accurate.
- Preserve issued contract numbers and snapshots. The Academy slip is a separate A5 document and uses its own endpoint.

## Tasks and notifications

- Each service line gets one open delivery task, bound to the applicant and line, on the service board. Re-saving a contract does not duplicate an open task for the same line/service.
- Moving a case into a terminal stage completes its open delivery tasks; moving back into the pipeline re-creates the task if needed.
- Contract creation notifies the applicant's responsible employees and the seller; assignment notifications go to the selected responsible employees and their managers.
- Commission accruals are created by the case completion, not by the contract being saved. Academy class completion is separate.
- Contract tasks are not a second source of financial truth; the contract line remains the source for fee, currency and payment state.

## UI surfaces and permissions

| Page | Route | Purpose |
| --- | --- | --- |
| Service contracts | `/contracts/{service}` | Create/edit/preview/print a built-in service contract. |
| Contract list | `/contracts` | Contract register; redirects to the default service. |
| Other contracts | `/other-contracts` | Non-service contract register. |
| Employee contracts | `/employee-contracts` | Employment contract register. |
| Contract options | `/options_contract` | Templates, types, policies, designs, responsible roles. |

- Contract reads: `view_contracts` plus the service permission for service rows.
- Create/edit/delete: `create_contracts`, `edit_contracts`, `delete_contracts`; cancel: `cancel_contracts`.
- Templates/types/policies/designs: the `view_*`/`manage_*` grants listed above.
- Portal: `invite_applicant_portal`; paid-at-signing receipts: `create_incomes`.
- Responsible assignment: `assign_applicant_employee`, `assign_responsible_employees`.

## Source map

- `routes/api.php` (contract, template, version, type, policy, design and responsible-role routes)
- `app/Http/Controllers/ContractController.php`
- `app/Http/Controllers/ContractTemplateController.php`
- `app/Http/Controllers/ContractTemplateVersionController.php`
- `app/Http/Controllers/ContractTypeController.php`
- `app/Http/Controllers/ContractPolicyController.php`
- `app/Http/Controllers/ContractDocumentDesignController.php`
- `app/Services/Contracts/ContractGenerationService.php`
- `app/Services/Contracts/PlaceholderResolver.php`
- `app/Services/Contracts/ContentSanitizer.php`
- `app/Services/Contracts/BuiltInContractTemplateService.php`
- `app/Services/ContractTaskService.php`
- `app/Services/ContractExpiryNotificationService.php`
- `app/Services/ActiveContractService.php`
- `app/Support/BuiltInService.php`, `ContractAccess.php`
- `app/Models/Contract.php`, `ApplicantContract.php`, `ContractTemplate.php`, `ContractTemplateVersion.php`, `ContractDocumentDesign.php`
- `resources/ts/pages/service-contracts.vue`, `contract_list.vue`, `other_contracts.vue`, `employee_contracts.vue`
