# Task Task-008 — Decide the Academy counselor commission path

## Metadata
- Task ID: `Task-008`
- Title: `Decide the Academy counselor commission path`
- Initial status: `Ready`
- Type: `Discovery`
- Priority: `P0`
- Owner role: `Product owner with finance and Academy leads`
- Estimated size: `S`
- Source plan/version: `commission-issues/LOGIC-20261110-004/issue.md`, 2026-11-10 review
- Requirement / behavior IDs: `LOGIC-20261110-004`

## Objective
Choose and document how an Academy enrollment can earn counselor commission, or explicitly retire that commission path. The decision must be precise enough for Task-009 to implement without inventing fee, ownership, or backfill rules.

## Context and problem
The only normal Academy enrollment path, Academy Students → Add student, creates an `academy_enrollments` row with null `contract_id` and null `applicant_contract_id`. The commission engine needs a contract line to value an Academy accrual, so the configured counselor Academy rate is unreachable. The teacher class commission still works because it is keyed to the completed class. The issue is explicitly marked as a product decision, not a mechanical bug.

## Scope
### Included
- Choose one path: create or link a contract line for direct Academy enrollment, use another durable commission source, or retire Academy counselor commission.
- Define the fee and currency source, the selling/owning employee, and how the line relates to the enrollment.
- Define cancellation, withdrawal, refund, and completion treatment.
- Define how existing enrollments with null contracts are handled: backfill, leave uncommissioned, or another explicit rule.
- Record the decision, rationale, approver, and date in `decision.md`.

### Excluded
- Any application source, migration, test, or UI change.
- Teacher class commission, which is already keyed to the completed class and must remain separate.
- The edit-window policy, contract extension integrity, and responsible-employee access tasks.

## Preconditions and assumptions
- The current Academy enrollment is the live record and the slip is its document; the decision must say whether that remains true.
- The configured Academy commission rate is not evidence that the business wants to pay it; the product owner must decide.
- Existing enrollment rows and their null contract ids are known evidence, not something this task may silently rewrite.

## Dependencies
- `None` — this decision has no upstream task.
- External blocker: product-owner approval is required before the downstream Academy implementation task can start.

## Contracts and boundaries
- Deliverable contract: `Tasks/Task-008-decide-academy-commission-path/decision.md`.
- The decision must name the enrollment-to-money link, the owner field, the fee basis, the currency/rate behavior, and the lifecycle states that earn or void commission.
- It must state whether existing null-contract enrollments are backfilled, left alone, or handled by a one-time reconciliation.
- No API shape, schema, permission, or UI change is approved by this task.
- Files/modules this task must not modify: all application source, tests, migrations, seeders, routes, configuration, and translations.

## Exclusive file ownership
- `Tasks/Task-008-decide-academy-commission-path/decision.md` — sole written decision record for this policy.

## Acceptance criteria
- [ ] Given the Academy enrollment evidence, when the decision is recorded, then it chooses one path: create or link a contract line, use another durable commission source, or retire Academy counselor commission.
- [ ] Given the chosen path, when the decision is recorded, then it defines fee, currency, seller or owner, cancellation and refund treatment, and existing-enrollment handling.
- [ ] Given the decision, when Task-009 reads it, then the scope is explicit enough to implement without inventing a commission policy.

## Verification plan
- Automated checks: none; this is a decision-only task and must not change product code.
- Manual review: compare the decision against the LOGIC-20261110-004 evidence and the current CommissionService Academy rule.
- Required evidence: `decision.md` with approver, date, chosen path, fee/currency/owner rules, lifecycle treatment, and existing-enrollment plan.
- Downstream check: Task-009 must cite this decision and must not introduce an unapproved accrual or backfill.

## Implementation notes
- Prefer the smallest path that matches the actual sales workflow: if Academy is sold through a live agreement, say where that agreement is created; if it is not commissionable, remove the unused expectation rather than inventing a contract solely to satisfy the engine.
- Name the teacher commission explicitly as a separate, already-working path so it is not accidentally changed.
- If the decision requires a data backfill, specify how to identify the seller and fee for existing enrollments rather than guessing from the class fee.

## Risks and rollback
- Risk: an ambiguous decision could lead to duplicate counselor and teacher accruals.
- Risk: backfilling old enrollments could create money for sales that were never commissioned; the decision must address this explicitly.
- Rollback: no code or data changes, so a revised decision only requires updating `decision.md` and notifying Task-009.

## Handoff checklist
- [ ] Chosen Academy commission path is explicit.
- [ ] Fee, currency, owner, lifecycle, and existing-enrollment rules are recorded.
- [ ] Teacher class commission is explicitly kept separate.
- [ ] No product files were changed.
