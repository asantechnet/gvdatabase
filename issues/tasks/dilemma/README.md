# Dilemma — one service case per service vs. complete the current service cases

Created `2026-11-05`. This folder holds two mutually exclusive task contracts. Only one should be implemented.

## The two alternatives

| Plan | Task | What it does | What it keeps |
|---|---|---|---|
| **A — service case only** | [`Task-030`](Plan-A-service-case-only/Task-030-service-case-only/Task.md) | Removes the applicant wrapper case. Applications -> Applications lists one row per contracted service; the service case owns the wrapper's working capabilities; creating an applicant creates no case; contract creation ensures the party and line. | The party record, portal, documents, notes, CRM/contact history, finance and line isolation. |
| **B — complete the current structure** | [`Task-031`](Plan-B-service-case-operations/Task-031-service-case-operations/Task.md) | Keeps the wrapper and Applications rows. Adds the genuinely missing line-scoped task creation/completion and a shortcut to the existing per-line finance flow. | The wrapper, Applications applicant rows, stage writers, pipeline and current case isolation. |

The two tasks are alternatives, not a queue. Do not run both. They intentionally touch some of the same files, so each plan has its own `INDEX.md` and is validated as its own task set.

## What was verified in the current code

- **The wrapper is created on applicant creation.** `ApplicantController::save()` assigns the service's `new` stage and writes an `ApplicantStageHistory` row with the note `Case created`. The Applications list (`ApplicantController::index()`) returns one row per applicant, not per service.
- **Contract creation always creates a new applicant** except for an extension. `StoreServiceContractRequest` prohibits `applicant_id`; `ContractGenerationService::create()` calls `createApplicant()`, which assigns a stage and writes `Contract created`. The requested "reuse the applicant if the person already exists" is not implemented. Only extensions reuse the parent applicant.
- **The service case currently lacks the wrapper's working surface.** `/service-cases/{line}` has the line stage/history/tasks/finance, but no calling/email actions, CRM details, portal controls, profile editing, profile documents, notes, university applications, pending financial requests or task creation/completion. Those remain on `/applicants/{id}`.
- **Manual task creation for a service does not exist.** `TaskController::store()` validates `applicant_id` but not `applicant_contract_id`; `applicant-case.vue::createTask()` sends only `applicant_id`; only `ContractTaskService` creates line-linked tasks automatically. This is a real gap, not something the user missed.
- **Finance recording for a service already exists.** `IncomeController::store()` accepts `applicant_contract_id`, `IncomeController::applicantContracts()` lists an applicant's lines, `/income` has a line selector, and `ServicePaymentsDialog.vue` submits one income per selected line through `createIncome()`. The service case only lacks a shortcut. Plan B must not add a second finance endpoint, table or form.
- **The wrapper stage chip can disagree with the line stages.** The applicant-level `current_stage_id` is a mirror updated by line moves. On a lined applicant it can show a different stage from the actual service cases. Plan B leaves this as-is because it keeps the wrapper; Plan A removes the wrapper's stage meaning.

## The blocking decision in Plan A

`applicants.service` stores one service and one per-service detail relation. The current model does not support one party owning a scholarship line and an academy line through the same applicant record. Plan A therefore supports explicit reuse of an existing applicant **of the same service** only. Cross-service reuse needs a product/schema decision (one party with multiple service profiles vs. one applicant per service). Do not solve it by merging parties or creating duplicates silently.

Plan A also leaves legacy applicant-level stage history untouched. Ambiguous historical rows are not reassigned to a line; a separate audited backfill would be needed if that history must become case history.

## Recommendation

- If the immediate pain is "I cannot create a task or record money from the service case," choose **Plan B**. It is the smallest change, fixes the real task gap, and reuses finance.
- If the product model must be "one service case per service, no wrapper," choose **Plan A**, but first approve the cross-service applicant policy and the party-level data boundary. It is the larger, riskier change and should not be started as a quick edit.
- A low-risk sequence is Plan B first (so the current service cases become operable), then Plan A after the identity decision. That is a product sequencing choice, not a dependency between the two task contracts.

## Validation

Each plan has its own `INDEX.md` because the two alternatives claim overlapping files by design. Validate separately:

```bash
python3 issues/tasks/scripts/validate_tasks.py issues/tasks/dilemma/Plan-A-service-case-only
python3 issues/tasks/scripts/validate_tasks.py issues/tasks/dilemma/Plan-B-service-case-operations
```

No application source was changed while creating these tasks.
