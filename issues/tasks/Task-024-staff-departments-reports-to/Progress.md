# Progress — Task-024

## Status snapshot
- Status: `Review`
- Owner: `Buffi (implementation)`
- Last updated: `2026-11-02`
- Current focus: `Delivered; manual viewport and live save checks outstanding`
- Blocker: `None`
- Next action: `Visual/keyboard review at 390/768/1280 px and one live create/edit pass in the browser.`

## Acceptance criteria progress
- [x] Given an employee in Operations and Finance, when the staff table renders, then both department names appear as separate chips and the print row contains both names joined readably; a single-department employee still renders one chip. — Evidence: `#item.departments` slot uses `staffDepartments()` with a legacy `department_id`/`department_name` fallback; `staffReportRow` appends the joined names and the print dialog gained a Departments column.
- [x] Given the create form, when two departments are selected and Finance is chosen as primary, then the payload contains both ids and `primary_department_id: Finance`, and the saved row shows both departments. — Evidence: `hierarchyPayload()` sends `department_ids` + `primary_department_id`; the multi-select and primary select share `selectedStaff`.
- [x] Given the edit form, when the user removes the primary department from the selection, then the primary control clears and the form requires another primary or no departments before save. — Evidence: `syncPrimaryDepartment` clears a removed primary and defaults to the first selected department.
- [x] Given the Reports-to field, when it opens, then the options include active employees beyond the current table page, exclude the employee being edited, and show name, position and department. — Evidence: options come from one `EmployeeApi.getOptions()` call in `onMounted`, filtered to active, excluding `selectedStaff.id`, labelled with name/position/department.
- [x] Given the user selects a different supervisor, when the selection changes, then a confirmation opens naming the supervisor and stating the shared HR scope, the rights not granted, the separate department/task rules and the effect of clearing. — Evidence: `requestSupervisorChange` opens `StaffSupervisorDisclosureDialog`; all four statements are rendered from the Task-026 keys.
- [x] Given the confirmation is cancelled, when the dialog closes, then the previous supervisor remains selected and no payload field changed; given it is confirmed, then the new supervisor is applied and saved. — Evidence: the select is bound to `:model-value` and only `confirmSupervisorChange` writes `supervisor_id`.
- [x] Given the current supervisor is cleared, when the removal confirmation is confirmed, then `supervisor_id: null` is sent and the table shows the not-assigned state; cancel restores the supervisor. — Evidence: `requestSupervisorChange(null)` uses the clearing variant; `supervisor_name` is null in the directory.
- [x] Given a supervisor the employee cannot be assigned to, when the API returns a 422, then the Reports-to field shows the translated rank reason and no success toast is shown. — Evidence: `applyStaffApiErrors` maps `supervisor_id` into `formErrors.supervisor_id`; the select binds those messages. No page success toast is added on failure.
- [x] Given a user without `assign_employee_supervisor`, when the form opens, then the department and supervisor controls are read-only with a reason, those fields are absent from the payload, and the other permitted fields still save. — Evidence: controls are disabled with `supervisorDisclosurePermissionNote`; `hierarchyPayload()` returns `{}` so the keys are structurally omitted.
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the form, chips, confirmation and table are used with the keyboard, then controls remain reachable, names are not mirrored, and no horizontal overflow hides the primary department or Reports-to actions. — Evidence: layout uses wrapping `VCol`s and the existing table scroll shell; the actual viewport pass is not recorded.

## Work completed with evidence
- `resources/ts/api/employee.ts`: `departments`, `department_ids`, `primary_department_id` on the employee type and on create/update payloads.
- `resources/ts/pages/staff_list.vue`: multi-department autocomplete + primary select in both create and edit dialogs, department chips column, print column, full-list supervisor options, disclosure-driven change/clear, permission gating, conditional payload, legacy fallbacks, inline field errors.
- `resources/ts/components/StaffSupervisorDisclosureDialog.vue`: names the supervisor/employee and states shared scope, not-granted rights, separation from departments/tasks, and clearing effect.

## Files changed and why
- `resources/ts/api/employee.ts` — hierarchy fields.
- `resources/ts/pages/staff_list.vue` — form, table, print and payload.
- `resources/ts/components/StaffSupervisorDisclosureDialog.vue` — new confirmation dialog.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| 2026-11-02 | `npm run typecheck` | PASS | terminal | After the staff edits |
| 2026-11-02 | `npm run i18n:check` | PASS | terminal | New keys resolve |

## Decisions and deviations
- The legacy `department_id` is no longer sent by this form; it remains readable for older rows.
- The primary is defaulted to the first selected department when none is present, as the task allows.
- Post-save primary-outcome narration is provided before save; no per-employee outcome is invented from the department update response.

## Blockers and open questions
- The live browser pass (create/edit, cancel/confirm, permission-limited user) and viewport/RTL checks remain outstanding.

## Handoff notes
- Ready for review; all copy uses the frozen Task-026 keys.

## Activity log (append-only)
- `2026-11-01 00:00 UTC` Created the task contract; implementation had not started.
- `2026-11-02` Implemented multi-department fields, Reports-to disclosure, table/print chips and permission gating; typecheck and locale checks green. Status moved to `Review`.
