# Applicant cases and pipeline

Current behavior reviewed 2026-11-11. Source owners are `ApplicantController`, `ApplicantStageController`, `ApplicantContractStageController`, `ApplicantStageContext`, `ApplicantStageTransitionGuard`, `CaseWinService`, `CommissionService`, `ApplicantServiceDetails`, `ApplicantPortalController`, and the routes in `routes/api.php` / `routes/crm.php`.

## Case identity

- `applicants` holds shared identity: name, father/guardian details, contact, email, address, notes, portal user, responsible employee(s), current stage mirror, and status.
- Each built-in service has its own 1:1 detail row: `scholarship_applicant_details`, `private_applicant_details`, `test_applicant_details`, or `academy_applicant_details`. Multi-valued fields (favourite subjects, skills, teachers) live in pivots.
- Money is not stored on the applicant. Fee, currency, rate, discount, payment state and stage belong to the `applicant_contract` service line.
- One applicant may own several service lines. Each line is its own case and moves independently. The applicant's `current_stage_id` is a mirror of the most recently changed line for aggregate screens; it is not the editable stage of every case.
- Academy is case-free and has no applicant stage. The case-bearing services are `scholarship`, `test_offer`, and `private`.
- Applicant status is `active` while the latest line is in the pipeline and `closed` when a terminal stage is reached.

## Stages and transitions

`applicant_stages` rows define the workflow:

- `service` scopes the stage to one built-in service.
- `country_id` / `university_id` scope admission stages for scholarship/private.
- `test_id` scopes test-offer stages; null is shared across tests.
- `sort_order` controls the picker order; `is_active` hides retired stages.
- `allowed_next_keys` defines legal next stage keys.
- `is_terminal` and `is_locked` are system-owned and not fillable. Terminal stages are created by migrations/seeds, not by request bodies. Locked seeded stages cannot be updated or deleted.

`ApplicantStageContext` is the single rule for whether a stage applies:

- Reject if the service is invalid or case-free.
- Reject a stage scoped to a different service.
- For test offer, allow a shared stage or one matching the applicant's test.
- For scholarship/private, allow an unscoped stage, a country-scoped stage matching the detail country, or a university-scoped stage matching the detail university.
- Academy always returns false because it has no case.

`ApplicantContractStageController` reads/moves one line:

- `GET /applicant-contracts/{line}` requires `view_applicants`.
- `POST /applicant-contracts/{line}/change-stage` requires `change_applicant_stage`.
- The line's service is used for context, never a sibling line's service.
- The current stage's `allowed_next_keys` must contain the next key. A missing/retired current stage falls back to the service's active stage list.
- A terminal transition requires a non-empty note (`business.terminal_stage_note_required`). A document is never a stage prerequisite.
- The move writes an `applicant_stage_histories` row and updates the applicant mirror/status inside one transaction.
- Moving to a terminal stage completes open delivery tasks. Moving back into the pipeline re-creates the line's delivery task if needed.

## Completion, wins, and commission

- `completed` is the terminal success key. `ApplicantStage::COMPLETED_KEY` is the point where the case is finished and commission is earned.
- `visa_approved` is a separate success stage. Reaching it records a `case_wins` row through `CaseWinService`.
- `CaseWinService` requires a responsible employee. Without one, the transition is refused.
- Exit keys `cancelled`, `rejected`, and `withdrawn` close a case without commission.
- Commission is not earned at `visa_approved`; it is earned when the case reaches `completed`. `CommissionService::syncForApplicant()` rebuilds unsettled accruals for eligible case-bearing lines.
- Eligible earners are the contract/line seller and every responsible employee on the case, but only employees marked `is_commission_based` with a positive service percentage. The base is the line's `fee − discount` converted to base currency.
- A case that leaves `completed` (reopened, cancelled, rejected, withdrawn) voids unsettled accruals. Money already attached to a settlement is immutable and is not reversed.
- Academy contract lines never earn counselor commission; only completed Academy classes earn teacher commission. See [Academy](../academy/README.md) and [Finance](../finance/README.md).
- Multiple service lines on one applicant each earn their own commission when their own case completes.

## Tasks and reminders

- Each contracted service line gets one open delivery task bound to the line. The task is assigned to the case's responsible employee(s), with the actor as fallback.
- Task creation is idempotent per open line/service; re-saving a contract must not spawn duplicates.
- Terminal stages complete the line's open tasks. Reopening the case restores the delivery task.
- `applicants:send-reminders` uses the latest stage-history entry for active applicants and warns at 30, 21, or 14 days in the same stage.
- Reminder recipients are the responsible employee and department managers with `view_subordinate_records`; if none, holders of `view_applicants`.
- Stage-change notifications go to the responsible employee and managers, and separately to the applicant portal user when one exists.
- Case-win notifications go to `grant_bonuses` holders and the winning employee.
- All notification producers go through `Notification::deliverTo()` or `EducationalWorkflowNotificationService::notifyOnce`, with idempotent keys.

## Intake, CRM, portal and reports

- Visitors can be converted to applicants (`POST /visitors/{visitor}/convert-to-applicant`, `create_applicants`). Visitors have no lead stage; the pipeline begins with the applicant case.
- `GET /applicants-pipeline` requires `view_applicants`. Applicant CRUD uses `view_applicants`, `create_applicants`, `edit_applicants`, `delete_applicants`; consultant review uses `review_visitors`.
- Applicant attachments: view/download with `view_applicants`, upload/edit/delete with `edit_applicants`.
- University applications are applicant-scoped: view with `view_applicants`, write with `edit_applicants`.
- CRM interactions use `/crm/{type}/{id}/interactions` and notes for visitors/applicants; global/case university-country relationships live in `routes/crm.php` (`/applicants/{applicant}/relationships`, `/crm-relationships/{type}/{id}`).
- Applicant portal routes require `view_own_applicant_portal` / `edit_own_applicant_profile`; staff invitation/access management requires `invite_applicant_portal`.
- `GET /educational-dashboard` requires `view_agency_overview`; agency reports are type-authorized in `EducationalReportController`.
- `GET /applicants/{applicant}/relationships` is the case-scoped relationship aggregate; keep the literal route before resource bindings.

## Case UI surfaces

| Page | Route | Purpose |
| --- | --- | --- |
| Applications | `/applicants` | Applicant register/pipeline entry; redirects to the applications view. |
| Pipeline | `/applicant-pipeline` | Stage board and case movement. |
| Applicant case | `/applicants/{applicant}` (applicant-case page) | Identity, documents, services, relationships, tasks. |
| Service case | `/service-case` | Per-service case workspace. |
| Agency overview | `/agency-overview` | Read-only operational dashboard. |
| Visitors | `/visitors` | Visitor intake and conversion. |
| Applicant portal | `/my-case` / applicant-portal page | Applicant-facing case, documents and profile. |

## Permissions

- Case reads: `view_applicants`, `view_all_applicants`, or employee-scope access to the responsible employee.
- Case writes: `create_applicants`, `edit_applicants`, `delete_applicants`, `change_applicant_stage`.
- Stage catalogue: `view_applicant_stages`, `manage_applicant_stages`.
- Responsible assignment: `assign_applicant_employee`, `assign_responsible_employees`.
- Documents/attachments: `view_attachments`, `upload_attachments`, etc., plus applicant permissions.
- CRM: `view_visitors`, `create_visitors`, `edit_visitors`, `review_visitors`.
- Portal: `view_own_applicant_portal`, `edit_own_applicant_profile`, `invite_applicant_portal`.
- Agency overview/reports: `view_agency_overview`; report type permissions apply separately.

## Invariants and known limits

- One line = one case. Never mix a scholarship stage into a test-offer line or use the applicant mirror as the editable stage.
- Terminal stages require a written reason; do not add a document prerequisite to stage movement.
- A case cannot be marked won without a responsible employee.
- Commission is earned at `completed`, not at contract creation or `visa_approved`; Academy is separate.
- Settled commission is never reversed by reopening/cancelling a case.
- Stage rows marked terminal/locked are system-owned. Do not let the API or a seeder request body change them.
- Case reminders are threshold-based and idempotent; they are not a general overdue-task scheduler.

## Source map

- `routes/api.php` (applicant, stage, pipeline, portal, visitor, CRM and report routes)
- `routes/crm.php`
- `app/Http/Controllers/ApplicantController.php`
- `app/Http/Controllers/ApplicantStageController.php`
- `app/Http/Controllers/ApplicantContractStageController.php`
- `app/Http/Controllers/ApplicantPortalController.php`
- `app/Http/Controllers/VisitorController.php`
- `app/Http/Controllers/CrmInteractionController.php`
- `app/Http/Controllers/CrmRelationshipController.php`
- `app/Services/ApplicantStageContext.php`
- `app/Services/ApplicantStageTransitionGuard.php`
- `app/Services/CaseWinService.php`
- `app/Services/CommissionService.php`
- `app/Services/ApplicantServiceDetails.php`
- `app/Services/EducationalWorkflowNotificationService.php`
- `app/Models/Applicant.php`, `ApplicantStage.php`, `ApplicantStageHistory.php`, `ApplicantContract.php`, `CaseWin.php`
- `resources/ts/pages/applicants.vue`, `applicant-pipeline.vue`, `applicant-case.vue`, `service-case.vue`, `visitor.vue`, `educational-dashboard.vue`
