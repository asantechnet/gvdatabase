# ISSUE-18 — Service contract creation is blocked by a client validator requiring a server-prohibited `start_date`

- **Severity:** P1 (blocks a core revenue document)
- **Category:** Logic / validation contract
- **Pages:** Contracts → Create contract (`/contracts/scholarship`, `/contracts/test-offer`, `/contracts/academy`, `/contracts/private`)
- **Persona:** Counselor (reproduced); the failure applies to every role, including super administrator
- **Confidence:** High — reproduced in the live UI, root cause traced in source

## Expectation

With a compatible active template version selected and every required field filled, pressing **Save** creates the service contract. The start date is not user input for these documents: the server request class marks it `prohibited` and derives it.

## Observed

1. Signed in as `ahmad.karimi@example.test` (role `counselor`) and opened **Contracts → Scholarship → Create contract**. The dialog pre-filled the counselor as Consultant.
2. Selected version **Scholarship — English · v1 · Current**, filled applicant/father names, both phones, percentage `10`, Destination `Canada`, Language `English`, Education `Bachelor`, Subject `Computer Science`, Application fee `1000` AFN, rate `1`.
3. **Save became enabled.** Pressing it returned the toast and inline alert:

   > `Start Date: Required.`

4. The dialog stayed open. No contract was created — the list still contained only `CTR-26-002` and `CTR-26-001`.
5. The dialog offers **no start-date control at all**: 18 inputs, zero of type `date`, and no date input in the dialog template. The submitted payload contains no `start_date`.

Creating a new template version cannot resolve this: the field the validator demands is never collected or sent, regardless of which version is selected.

## Root cause (source trace)

The client-side request schema and the server request class contradict each other:

| Layer | Location | Rule |
|---|---|---|
| Client validator | `resources/ts/utils/apiValidation.ts:15-22` | `serviceContractRules` requires `start_date` for `POST /contracts/{scholarship,test_offer,academy,private}` |
| Request interceptor | `resources/ts/api/axios.ts:67-80` | `validateApiRequest(config)` rejects with `validation_failed` **before the request is sent** |
| Dialog payload | `resources/ts/pages/service-contracts.vue:480-505` | `save()` builds the payload without `start_date` |
| Dialog template | `resources/ts/pages/service-contracts.vue` (dialog section) | no date field is rendered |
| Server request | `app/Http/Requests/StoreServiceContractRequest.php:108` | `'start_date' => ['prohibited']` |
| Server derivation | `app/Services/Contracts/ContractGenerationService.php:104` | `'start_date' => now()->toDateString()` |

Because the rejection happens in the Axios request interceptor, the request never reaches Laravel. The message is produced from the stale client rule, which is why it names a field the form does not contain.

## Impact

- **Every** built-in service contract type is currently uncreatable from the UI, for every role including super admin.
- Blocks the revenue workflow downstream: contract records, receipts against contract lines, commissions and payroll linkage that depend on a new contract.
- The error is uncorrectable by the user — there is no field to fill — so it reads as a broken form rather than a validation failure.
- Selecting or creating a different template version does not help; the failure is independent of template data.

## Evidence

- Reproduction transcript, 2026-10-31 (Asia/Kabul), super admin/counselor UI; exact strings above.
- Source pointers in the table (paths and line numbers).
- Dialog layout: `review/evidence/contract-create.png`.
- No screenshot of the error toast was captured; the string was recorded from the live dialog in the same session.

## Recommendation (no code change made)

- Remove `start_date` from `serviceContractRules` so the client schema mirrors `StoreServiceContractRequest`; the server already derives the value.
- Add a regression check that a minimal valid service-contract payload passes client validation and reaches the server.
- Clear form-level API errors when the user edits the offending field, so stale validation text cannot persist.

## Standard comparison

A client-side request validator must mirror the server request class. It must never require a field the server prohibits and the UI never collects; when the two drift, every submission fails before the network call and the user is shown an error for a control that does not exist.
