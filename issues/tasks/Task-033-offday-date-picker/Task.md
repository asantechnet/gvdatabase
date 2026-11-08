# Task Task-033 — Make the self-service off-day date range selectable

## Metadata
- Task ID: `Task-033`
- Title: `Make the self-service off-day date range selectable`
- Initial status: `Ready`
- Type: `UI`
- Priority: `P1`
- Owner role: `HR frontend owner`
- Estimated size: `S`
- Source plan/version: `User UI review 2026-11-07; live My off days dialog and /tasks employee session`
- Requirement / behavior IDs: `HR-LEAVE-DATE-RANGE`

## Objective
An employee can choose From and To dates in the My off days dialog, see the selection in the inputs and submit a range request instead of being limited to the default date.

## Context and problem
In the live employee session, opening the profile menu and **My off days** showed the balance and request form. Clicking the **From** input opened a Flatpickr calendar detached from the field: it appeared near the top-left of the viewport, overlapping the dialog header and balance cards, while the From input was near the bottom of the dialog. Clicking November 9 in the visible calendar did not change the input, which remained `2026-11-07`; a second hidden Flatpickr instance produced duplicate day cells with one disabled copy. Typing into the input was ignored. The shared dialog convention in `AGENTS.md` requires the picker to stay body-level, to apply the zero-offset transform after `onOpen` and to keep the calendar above dialog content; this dialog passes only `dateFormat`.

Evidence: `evidence/calendar-overlaps-dialog.png` and `evidence/calendar-detached-from-input.png`.

## Scope
### Included
- Configure both date pickers in the My off days dialog according to the documented Vuetify-dialog Flatpickr convention.
- Ensure the calendar is anchored to its From or To input, above dialog content, and that choosing a weekday updates the bound form value and closes the popup.
- Ensure the requested-day count, range validation and Request button react to the chosen From and To values.

### Excluded
- Changing backend leave rules, the approval flow covered by Task-032, or other date pickers outside this dialog.
- Changing the shared `AppDateTimePicker` unless a separate shared defect is proven.
- Redesigning the My off days balance or request history.

## Preconditions and assumptions
- The dialog convention in `AGENTS.md` under Vuexy Quirks is the authoritative implementation rule for this screen.
- Friday remains the weekly day off; the test range uses weekdays inside 2026.
- The seeded employee has a 20-day allowance and a pending request; the dialog is opened from the profile menu.

## Dependencies
- `None` — independent UI task; the approval page is a separate surface from this dialog.

## Contracts and boundaries
- Input/output: the form still submits `POST /my-leave-requests` with `start_date`, `end_date`, optional `duration_days` and `reason`; no API change.
- Compatibility guarantee: the default date, half-day select, balance cards and request history keep their current behavior outside the picker.
- Error and failure behavior: an invalid or reversed range keeps the existing warning/disabled submit behavior; closing the popup must not submit the form.
- Permission/data-scope expectations: unchanged `request_own_leave` self-service scope.
- Integration seams: use the existing `AppDateTimePicker` and `printDocument`-unrelated dialog structure; do not create a second calendar component.
- Files/modules this task must not modify: `resources/ts/pages/leave_requests.vue`, `resources/ts/pages/staff_list.vue`, `resources/ts/components/AppDateTimePicker.vue` unless a separate task is approved.

## Exclusive file ownership
- `resources/ts/components/paid-time-off/MyPaidTimeOffDialog.vue` — date-picker configuration, dialog layout and form reactivity.

## Acceptance criteria
- [ ] Given My off days is open, when the employee clicks From, then the calendar is anchored to the From input with the documented zero-offset transform and does not cover the dialog header or balance cards.
- [ ] Given the From calendar is open, when a weekday is clicked, then From shows that date, the calendar closes and the requested-day count updates.
- [ ] Given From is set, when the employee picks a later weekday in To, then To updates and the Request button state reflects the chosen range and reason.
- [ ] Given the dialog at desktop and 390 px in LTR/RTL and light/dark, when the pickers are used, then the calendar remains visible above the dialog and the form is usable.

## Verification plan
- Automated: `npm run typecheck`; run any existing component test for the paid-time-off dialog if one exists.
- Manual/UI: sign in as the seeded employee, open My off days, choose a two-weekday range, add a reason, submit, and confirm the pending history row; repeat with a reversed range and with only Friday.
- Data fixtures / seed and cleanup: seeded employee with an allowance; delete or keep the synthetic request according to the local demo policy.
- Expected commands/results: typecheck exit 0; request response 201; two weekday rows written.
- Regression, permissions, accessibility: check keyboard selection, focus return, mobile width, RTL and dark theme; ensure the dialog's scroll and close button still work.
- Required evidence/artifacts: before/after screenshots of the anchored calendar and the submitted pending row.

## Implementation notes
Follow the documented dialog picker pattern: keep the calendar body-level, apply `transform: translateY(0) scale(0.86)` with `transform-origin: top left` after open, and keep a high z-index. Do not set `static: true` in the dialog. Verify that each picker has a single live calendar instance and that the selected value is emitted to `form.start_date` and `form.end_date`, not only displayed by Flatpickr.

## Risks and rollback
- Risks / failure modes: a global picker change could affect other dialogs; duplicate Flatpickr instances can keep hidden calendars in the DOM.
- Data migration/backfill/compatibility: none.
- Rollback: revert the dialog component; no schema or API change.
- Stop conditions / human approval needed: none beyond the demo-data policy.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely "code complete."
