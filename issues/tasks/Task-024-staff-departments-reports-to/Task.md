# Task Task-024 — Make staff departments plural and Reports-to honest

## Metadata
- Task ID: `Task-024`
- Title: `Make staff departments plural and Reports-to honest`
- Initial status: `Ready`
- Type: `Frontend`
- Priority: `P1`
- Owner role: `Vue 3 and Vuetify implementer with HR UX review`
- Estimated size: `L`
- Source plan/version: `User request 2026-11-01: editable role seniority, Reports-to disclosure, multi-department employees; repository inspection 2026-11-01`
- Requirement / behavior IDs: `ORG-STAFF-001`, `ORG-STAFF-002`, `ORG-UX-002`

## Objective
Staff create/edit lets one employee belong to several departments, shows every department in the directory, and makes "Reports to" a deliberate choice with a short confirmation that states what the selected supervisor may see and what the change does not grant.

## Context and problem
The staff form has one "Department" select and a "Reports to" select. The department select cannot express the new multi-department rule. The supervisor options come from the current staff table page, so the list changes with pagination and can omit valid people or include people the server will reject. The backend rejects an invalid supervisor with `business.employee_supervisor_rank_invalid`, but the form has no explanation of the reporting line, so a user reads "Reports to" as daily reports, approvals or task assignment.

The term is also hidden logic. `employees.supervisor_id` is the direct manager in the HR reporting line. `EmployeeScope` uses that line, plus departments the user manages, to decide whose employee-owned records a holder of `view_subordinate_records` may see and whose records a holder of `manage_subordinate_records` may change. It is not task assignment, not a department manager appointment, and not a permission grant. The confirmation must say this plainly.

The fields are also guarded. `EmployeeController` silently unsets `supervisor_id`, `department_id` and the new membership fields when the caller lacks `assign_employee_supervisor`. The form currently still shows them, so a user can think a change saved when it was dropped. This task makes the fields read-only with a reason and omits them from the payload for that caller.

## Scope
### Included
- Extend the staff employee type with `departments`, `department_ids` and `primary_department_id`; keep `department` and `department_id` as the primary compatibility fields.
- Replace the single department select with a multi-select for `department_ids` and a primary-department select constrained to the selected ids; default the primary to the first selection when none is chosen.
- Show every department as chips in the directory table and include all department names in the printable staff report rows.
- Load supervisor options from the full active employee collection once, not from the current table page; exclude the employee being edited and show name, position and department in the option label.
- Add a Reports-to table column with the existing supervisor name or a clear "not assigned" state and a tooltip that describes the HR reporting line.
- On selecting, changing or clearing a supervisor, open a short confirmation dialog that names the supervisor and states what may be shared, what is not granted, and what clearing does; cancel restores the previous value and confirm applies it to the form.
- Show a short helper under the field for the rank rule: only an employee who outranks this employee and can supervise may be selected; map the server field error to the field with the specific reason.
- Disable and mark department and supervisor controls read-only when the user lacks `assign_employee_supervisor`, explain that an administrator must grant the permission, and omit those fields from the payload so the API does not silently drop them.
- Keep the department and supervisor fields usable when the user has the permission but the employee is the current user and the route permits self-edit; do not widen any backend rule.
- Preserve the existing position-change salary confirmation and all payroll behaviour.
- Verify 390, 768 and 1280 px, LTR and RTL, light and dark.

### Excluded
- Backend membership, validation and scope rules; Task-021 owns them.
- Department page member management; Task-023 owns it.
- Role seniority level editing; Task-025 owns it.
- Locale files; Task-026 owns them.
- Changing the definition of supervisor, adding a second reporting line, or turning Reports-to into task assignment.
- Editing the employee resource or controller; if a field is missing, record the contract gap instead of editing backend files here.
- Payroll formulas, attendance, leave or contract assignment.

## Preconditions and assumptions
- Task-021 returns `departments`, `department_ids`, `primary_department_id`, `primary_department_id` and the legacy primary fields, and accepts `department_ids`/`primary_department_id` on create/update.
- The existing employee resource returns the supervisor name or relation needed by the table. If it only returns an id, use the loaded employee options to resolve the label without a per-row request and record the gap.
- The employee options endpoint returns active employees with position and departments, or this task adds a full-list fetch with a documented bound. It must not silently show only the first page.
- The hierarchy-write permission is named `assign_employee_supervisor` in the current authorization layer. Confirm before coding; if the name differs, use the existing constant rather than hard-coding a second string.
- Assumption to validate: the confirmation is a pre-save disclosure, not a legal consent record. It explains HR scope but is not stored as an acceptance record. If the product needs an audited acknowledgement, stop and define that separately.
- The user may have `edit_employees` without `assign_employee_supervisor`; the form must still save the personal, contact, position and banking fields and must not send the hierarchy fields.

## Dependencies
- `Task-021` — multi-department employee API contract.
- `Task-026` — disclosure, field, tooltip and rank-rule copy.

## Contracts and boundaries
- Request: create/update sends `department_ids: number[]`, `primary_department_id: number | null`, and `supervisor_id: number | null` only when the user holds `assign_employee_supervisor`. The legacy `department_id` is not sent by the new form.
- Response/type: `Staff` includes `departments: {id, name, code}[]`, `department_ids: number[]`, `primary_department_id: number | null`, and the existing `department`/`department_id`; the table falls back to the legacy pair for rows returned by an older endpoint.
- UI state: the form's supervisor value is only committed after the confirmation; the dialog's cancel path restores the prior id, including null. The department primary select is cleared when its department is removed from the selected set.
- Error behaviour: `errors.supervisor_id` maps to the Reports-to field with the translated rank reason; `errors.department_ids` and `errors.primary_department_id` map to the department controls. Success uses one toast; API failure feedback stays with the global notifier.
- Permission/data scope: the fields are read-only without `assign_employee_supervisor`; the rest of the form follows the existing edit permission. No new permission and no relaxed backend guard.
- Disclosure content: the dialog states that the supervisor is the direct manager in the HR reporting line; that a supervisor whose role holds the subordinate-view permission may see the employee's profile/contact and the HR records that permission covers, including records beneath them in the line; that the change does not grant edit or approval rights, account access, task assignment or department membership; and that clearing removes future scope through this line while audit history remains.
- Integration seam: the staff page reads Task-021 fields and Task-026 keys. It does not write membership directly or duplicate the scope resolver.
- Files this task must not modify: `app/**`, `routes/api.php`, `resources/ts/pages/setup/departments.vue`, `resources/ts/pages/setup/roles.vue`, `resources/ts/plugins/i18n/locales/**`, `resources/ts/pages/payrolls.vue`.

## Exclusive file ownership
- `resources/ts/pages/staff_list.vue` — directory columns, form fields, payload construction, permission gating and print rows.
- `resources/ts/api/employee.ts` — multi-department fields, full-list option request and typed supervisor fields.
- `resources/ts/components/StaffSupervisorDisclosureDialog.vue` — Reports-to confirmation and removal variant.

## Acceptance criteria
- [ ] Given an employee in Operations and Finance, when the staff table renders, then both department names appear as separate chips and the print row contains both names joined readably; a single-department employee still renders one chip.
- [ ] Given the create form, when two departments are selected and Finance is chosen as primary, then the payload contains both ids and `primary_department_id: Finance`, and the saved row shows both departments.
- [ ] Given the edit form, when the user removes the primary department from the selection, then the primary control clears and the form requires another primary or no departments before save.
- [ ] Given the Reports-to field, when it opens, then the options include active employees beyond the current table page, exclude the employee being edited, and show name, position and department.
- [ ] Given the user selects a different supervisor, when the selection changes, then a confirmation opens naming the supervisor and stating the shared HR scope, the rights not granted, the separate department/task rules and the effect of clearing.
- [ ] Given the confirmation is cancelled, when the dialog closes, then the previous supervisor remains selected and no payload field changed; given it is confirmed, then the new supervisor is applied and saved.
- [ ] Given the current supervisor is cleared, when the removal confirmation is confirmed, then `supervisor_id: null` is sent and the table shows the not-assigned state; cancel restores the supervisor.
- [ ] Given a supervisor the employee cannot be assigned to, when the API returns a 422, then the Reports-to field shows the translated rank reason and no success toast is shown.
- [ ] Given a user without `assign_employee_supervisor`, when the form opens, then the department and supervisor controls are read-only with a reason, those fields are absent from the payload, and the other permitted fields still save.
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the form, chips, confirmation and table are used with the keyboard, then controls remain reachable, names are not mirrored, and no horizontal overflow hides the primary department or Reports-to actions.

## Verification plan
- Automated: `npm run typecheck`; `npm run i18n:check`; source checks that the payload omits `department_ids`, `primary_department_id` and `supervisor_id` without the permission and that no page-level API error toast was added.
- Manual: create an employee in two departments; edit the primary; remove the primary; select a valid supervisor and cancel/confirm; clear a supervisor and cancel/confirm; force an ineligible supervisor; open the form as a user without the hierarchy permission.
- API/DB: inspect the create/update payload and the stored pivot/primary fields; verify an ineligible supervisor leaves the employee unchanged; verify the legacy single-department row still displays.
- Visual: 390, 768 and 1280 px in LTR and RTL, light and dark; check chip wrapping, dialog scroll, focus return and the print staff report row.
- Regression: position-change salary confirmation, payroll display, attendance/leave links and the staff filters still work; no `@page` or global print selector changed.
- Evidence: payloads, before/after rows, screenshots or viewport notes, and the typecheck/locale results.

## Implementation notes
Use a single `department_ids` array as the source of truth and derive the primary choices from it; if the current primary is removed, clear it rather than silently picking a value the user did not see. Build the supervisor options once in `onMounted` from the full employee options call and cache them by id; do not reload on every keystroke. Keep `pendingSupervisorId` separate from the form model so cancel is a true revert. Use the existing money/position confirmation pattern for the dialog lifecycle. Omit guarded fields structurally, not by sending empty strings: build the payload object conditionally. Keep the disclosure to four short bullets and link the rank rule to the field hint, not a second dialog. Do not edit locale JSON here; use Task-026 keys and fail the task if a key is missing rather than hard-coding English.

## Risks and rollback
- Risk: the full employee option list is large and slows the form. Mitigation: one request, local search, a documented maximum, and a clear empty state; do not paginate silently.
- Risk: the confirmation is ignored and becomes noise. Mitigation: keep it short, only on an actual change, and name the specific supervisor and the specific consequence.
- Risk: read-only fields still get submitted by a shared payload builder. Mitigation: construct the payload from allowed fields and test the exact keys.
- Data migration: none. Rollback is code-only; the API still accepts legacy `department_id`.
- Stop condition: if the resource does not expose enough supervisor/department labels to render without per-row requests, stop and fix the contract in Task-021 rather than adding a fan-out from the page.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs and the `docs/facts` HR description are updated if behaviour changed.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
