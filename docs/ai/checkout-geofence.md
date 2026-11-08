## Task: Geofence check-out exactly like check-in

- **Task ID:** checkout-geofence
- **Created:** 2026-11-08
- **Last updated:** 2026-11-08
- **Status:** COMPLETED
- **Outcome:** When a workday was started from inside an active office geofence, `End my day` verifies a fresh office location before it records the checkout, stores the checkout coordinates, and refuses an outside, stale, inaccurate or uncertain position with the same messages check-in uses.

### 1. Requirements and acceptance criteria

- [x] `POST /api/workday/end` requires `latitude`, `longitude`, `accuracy_meters` and `observed_at` when the workday's check-in was geofenced and the office fence is still active.
- [x] The same geometry rules apply as check-in: accuracy at most 100 m, the whole uncertainty circle must fit inside the radius, and the observation must be within 60 seconds of server time.
- [x] Outside → `business.attendance_outside_office`; inaccurate/boundary → `business.attendance_location_uncertain`; stale → `business.attendance_location_stale`.
- [x] A successful checkout stores `check_out_latitude`, `check_out_longitude`, `check_out_accuracy_meters`, `check_out_distance_meters`.
- [x] Workdays that were not geofenced at check-in (fence disabled, no office fence, or automatic attendance turned off during the shift) still end without any location, so nobody is trapped.
- [x] The SPA asks for the location only after the employee presses the end button, and reports an unavailable or unsupported geolocation through a translated message.
- [x] No schema change: the `attendances.check_out_*` columns already exist.

### 2. Scope and non-goals

- In scope: `WorkdayController::end`, `OfficeGeofenceService` (a guard-free evaluation helper), the workday API contract, the end-of-day dialog, tests and the geofence docs.
- Out of scope: restoring automatic background exit detection (removed by the 8 October audit), forced checkout after leaving the radius, payroll/timesheet changes, and HR correction behaviour.

### 3. Project findings

- `docs/geofencing.md` still describes automatic checkout after two outside observations two minutes apart. That tracker was deliberately removed in `docs/attendance-audit.md`; `OfficeGeofenceService::result()` now always reports `exit_confirmation_seconds_remaining: 0` and `attendance: null`.
- Check-in is fully geofenced in `WorkdayController::start`: it validates the four location fields, checks staleness, calls `OfficeGeofenceService::observe()` and aborts unless the action is `inside`.
- Check-out (`WorkdayController::end`) validates only the report fields and writes `check_out_time`, `checked_out_at`, `check_out_source = end_my_day`. It accepts no location at all, so the checkout columns are never populated and the fence can be left freely.
- `attendance.check_out_*` columns, casts and fillable entries already exist.
- Frontend: `WorkdayApi.start()` acquires a high-accuracy position only when the office fence is enabled and the employee explicitly asks to start; `WorkdayApi.end()` posts the report only. `EndDayDialog` loads `WorkdayApi.status()` on open and submits the report.

### 4. Graph and impact review

- No graph skill or fresh artifact; the trace used repository search plus direct reads of `routes/api.php`, `WorkdayController`, `OfficeGeofenceService`, `Attendance`, `api/workday.ts`, `components/workday/*`, `layouts/components/UserProfile.vue`, `utils/apiAuthorization.ts` and `utils/apiValidation.ts`.
- Callers of `POST /workday/end`: `EndDayDialog` only.
- Affected tests: `OfficeGeofenceAttendanceTest` (one existing case ends a geofenced workday without a position and must be updated), `PhaseSixWorkdayTest` and the client regression script cover non-geofenced ends.
- Downstream: the workday status payload gains a field consumed by the dialog; no permission or route change.

### 5. Clarifications, decisions, and assumptions

- User decision: "implement geo-fencing for the check-out time just like check-in time."
- Decision: require a location only when the check-in itself was geofenced (`attendances.check_in_latitude` is present) and the office fence is still active. This mirrors check-in and avoids trapping workdays that started before the fence was configured — the same principle as "existing workdays can still be ended when automatic attendance is off".
- Decision: evaluate against the office stored on the attendance, falling back to the employee's current office. A later office reassignment must not invalidate a shift that started somewhere else.
- Decision: the observation timestamp and accuracy rules are shared with check-in, not re-specified.
- Deviation from `docs/geofencing.md`: automatic exit detection stays removed. Checkout is an explicit action that is now location-verified; it does not close a day by itself.

### 6. Implementation plan

- [x] `OfficeGeofenceService` — extract a guard-free `evaluate(Office, lat, long, accuracy)` used by both `observe()` and checkout.
- [x] `WorkdayController::end` — decide whether a checkout location is required, validate the four fields, reject stale/outside/uncertain, store `check_out_*`.
- [x] `WorkdayController::statusPayload` — expose `attendance.requiresCheckoutLocation`.
- [x] `api/workday.ts` — `end(data, requiresLocation)` acquires a position after the explicit click, with the same account-guard pattern as `start`.
- [x] `EndDayDialog.vue` — pass the requirement, surface location failures.
- [x] `OfficeGeofenceAttendanceTest` — update the existing end call and add coverage for required, outside, stale, uncertain and successful checkout.
- [x] `docs/geofencing.md` — document checkout verification and remove the automatic-exit sentence.
- [x] `utils/apiValidation.ts` — mirror the new optional end-of-day location fields.

### 7. Validation plan and results

- [x] `php artisan test --filter=OfficeGeofenceAttendanceTest` — **11 passed, 93 assertions**.
- [x] `php artisan test --filter=PhaseSixWorkdayTest` — **14 passed, 1 failed**; the failure (`hr can correct a missed checkout` audit assertion) reproduces on the unmodified HEAD files, so it is pre-existing and unrelated.
- [x] `php artisan test --filter=PhaseSevenWorkingTimeTest` — passing in the combined run with the employee-status suite.
- [x] `npm run typecheck` — 69 errors, all pre-existing in `staff_list.vue`, `applicant-case.vue` and `support.vue`; **none** in `api/workday.ts`, `EndDayDialog.vue` or `apiValidation.ts`.
- Results: feature verified; one pre-existing failure reported above.

### 8. Risks and mitigations

- An employee who leaves the office before ending the day is blocked — mitigation: this is the requested behaviour; HR's missed-checkout correction remains the exception path.
- Ending an overnight shift from home is blocked — mitigation: same correction path, and the rule only applies when the shift started inside a fence.
- Location permission denied mid-shift prevents checkout — mitigation: the dialog explains the requirement instead of silently failing; retry is possible without losing the report draft.
- A stale device clock makes every observation stale — mitigation: identical to the existing check-in behaviour, with the same message.

### 9. Progress log

- 2026-11-08 — DISCOVERY: confirmed checkout is unverified; the checkout columns exist but are never written; `docs/geofencing.md` describes behaviour the audit removed.
- 2026-11-08 — PLAN: recorded and saved before implementation.

### 10. Completion summary

- Delivered: checkout geofencing symmetric with check-in, a shared `OfficeGeofenceService::evaluate()`, server-computed `requiresCheckoutLocation` on the workday status, browser location acquisition in `WorkdayApi.end`, a translatable failure alert in the end-of-day dialog, and updated geofence/audit documentation.
- Acceptance criteria satisfied: all seven.
- Tests/checks passed: `OfficeGeofenceAttendanceTest` 11/11; `PhaseSixWorkdayTest` 14/15 with the pre-existing audit failure; typecheck clean for the touched files.
- Remaining limitations: automatic background exit detection stays removed, so leaving the radius does not close a day. An employee outside the fence cannot end the workday themselves; HR's missed-checkout correction remains the exception path. The client behaviour was not exercised in a real browser with device GPS.
