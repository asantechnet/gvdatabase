# Attendance and geofence audit — 8 October 2026

The original implementation did not reliably follow the intended attendance policy. Background GPS could create attendance without an explicit Start action, permanently close a day during lunch, and process delayed browser callbacks after account switching. Midnight, Fridays, missing reports, and calculation edge cases also produced inconsistent outcomes.

The governing rule, confirmed by the owner during this audit, is that Start my day records check-in and End my day records checkout. Server acceptance time is authoritative; the browser cannot submit an arbitrary punch time through the self-service endpoints. Submitting the End my day report is the explicit checkout action. Approved HR corrections remain separate, permission-protected manual actions.

| Situation | Final behavior |
| --- | --- |
| Sign in, reload, or inspect location | Does not create or close attendance. The background GPS tracker was removed. |
| Start my day, with a required office fence | Acquire a fresh location after the click. Server verifies the employee, assigned office, coordinates, accuracy, and observation timestamp before accepting a punch. |
| Fence disabled | No location permission is needed for Start. |
| Missing assignment while the firm's fence is enabled | Block with an informative configuration message. Missing assignment cannot bypass the fence. |
| GPS uncertainty | Accept only when distance + accuracy fits within the radius, with accuracy at most 100 meters. Boundary readings cannot prove attendance. Observations older than 60 seconds, or more than 60 seconds in the future, are rejected. |
| Leave for lunch, run an errand, lose connection, hide the tab, close the browser, or sign out | The workday stays open. None of these events is a checkout. |
| End my day outside the office | Accept the explicit report and record checkout. No GPS permission is required to finish an existing workday. |
| Repeated Start click | Preserve the original check-in and logged-at values. No duplicate attendance row. |
| Overnight work, including Thursday into Friday | Keep one attendance row on the check-in date; allow checkout/report on Friday. Friday still blocks a new workday. |
| Administrator disables automatic attendance during a shift | Existing workdays can still be ended and their reports submitted. New self-service starts are disabled. |
| Old report not submitted | Keep it reachable even after another calendar day. Preserve any already recorded checkout when submitting the report. |
| Report dialog in another tab references a different session | Reject the stale attendance ID instead of submitting against a different workday. |
| Checkout forgotten for more than 24 hours | Keep the row incomplete and show an HR correction message. Do not calculate several days of invented work. HR uses the missed-checkout action in Timesheet to enter the verified local date/time. It keeps the original row, check-in and leave; the existing audit system records the actor and changed fields with source `hr_correction`. |
| Switch employee accounts in the same browser | Reject a second employee's attendance for the claimed local day. An unfinished shift also keeps the browser claimed across midnight. The browser identity survives logout. |
| Switch accounts during location lookup | Cancel before sending the delayed Start request. |
| Missing or invalid browser cookie on a mutation | Require a GET bootstrap/reload; a POST cannot silently mint a fresh identity. |
| Half-day leave | Preserve the approved leave and permit work during the remaining portion. Full-day leave and conflicting attendance remain blocked. |
| Inactive employee or future join date | Cannot start a self-service workday. Finalization does not generate new absences for inactive employees. |
| Finalize today's/future dates | Do not materialize absences before the company-local day finishes. Existing rows can still be calculated. |
| Identical punch timestamps | Zero worked time, rather than a fabricated 24-hour shift. Reversed explicit instants are incomplete/invalid; only legacy clock-only punches infer an overnight date. |
| Short workday | Clamp the applied break to actual duration without overwriting the configured/default break input. Required schedule time uses the configured break. |
| My work history | Expose report existence, allow employees to retrieve their own report without manager permissions, and return 404 for another employee's report. Use company-local dates and an exact calendar month end. |

Files traced during the audit:

| Concern | Files and related surface |
| --- | --- |
| Self-service HTTP flow | `routes/api.php`, `WorkdayController`, `AttendanceGeofenceController`, `AttendanceController` |
| Authentication and browser ownership | `AttendanceBrowser`, `AttendanceBrowserGuard`, `NormalizeApiErrorResponse`, active-account/edit-window middleware, Sanctum routes, frontend `authStorage`, `axios`, API authorization and validation |
| Geofence geometry/setup | `OfficeGeofenceService`, `Office`, `OfficeController`, `OfficeResource`, `OfficeSettingsPanel`, `OfficeGeofenceMap`, `DirectoryCrudPage`, office API contract |
| Settings and employee eligibility | `CompanyProfile`, `Setting`, `SettingController`, `SettingResource`, `UpsertSettingRequest`, settings page/composable, `Employee` active/selectable scopes and factory |
| Attendance persistence | `Attendance`, `DailyReport`, resources, HR baseline migration and `2026_10_08_000002_create_attendance_browser_days.php` |
| Calculations/finalization | `AttendanceCalculationService`, `MonthlyAttendanceProcessor`, `FinalizeAttendance`, `CalculateOvertime`, `routes/console.php` |
| Leave and payroll | Paid-time-off grant flow, `PaidTimeOff`, attendance leave metadata, salary attendance breakdown/deduction flow and payroll synchronization |
| Reports/aggregates | `ReportService`, report exports, `MonthlyAttendanceSummary`, employee total-worked-hours refresh, `SyncAnalytics` |
| Frontend interactions | Default layout, `UserProfile`, `WorkdayControl`, `EndDayDialog`, workday/geofence/attendance API modules, Timesheet and print template, `AccountSettingsWorkHistory`, English/Persian/Pashto messages |
| Regression coverage | `PhaseSixWorkdayTest`, `OfficeGeofenceAttendanceTest`, `PhaseSevenWorkingTimeTest`, `PaidLeaveGrantTest`, `SalaryAttendanceDeductionTest`, `EmployeeActiveStatusTest`, `PhaseEightReportsTest`, `scripts/attendance-workday-regression.mjs` |

Operational limits remain important. Browser geolocation requires a secure context and permission, can be inaccurate, and is not an authenticated proof of physical presence. The [W3C Geolocation specification](https://www.w3.org/TR/geolocation/) also makes background location delivery unsuitable as a dependable time clock. The new flow does not rely on that delivery. Clearing browser data, using another profile/device, sharing account credentials, or spoofing client coordinates cannot be prevented by an ordinary web cookie and GPS alone. The account guard prevents normal account switching; stronger personal identity assurance requires a separate trusted device or identity mechanism.

An open workday is not proof of every minute actively worked. This system supports one session per employee/day and deducts the configured break; it does not measure lunch intervals or continuous productivity. Monthly attendance summaries and dashboard analytics remain scheduled aggregates. Session values and employee history update immediately, while aggregates need their scheduled refresh or an explicit backfill after historical HR corrections. Issued payroll snapshots are not rewritten by this patch.

Deployment must run outstanding migrations, including the browser-day guard table, and rebuild frontend assets. Confirm HTTPS, company timezone, office coordinates/radius, employee assignments, and the existing Laravel scheduler. The local MySQL service was unavailable, so this audit did not apply migrations to the user's live database. Tests use isolated SQLite. No real-device GPS or production concurrency/load test was performed; browser launch was unavailable in this environment.

Validation completed across regression runs: 50 distinct related backend tests, client regression checks, `npm run typecheck`, `npm run i18n:test`, scoped Pint, generated icon validation, and whitespace checks. The backend set covers attendance, geofence, leave, employee eligibility, payroll deductions and CSV/print reporting. The focused client test executes the actual TypeScript API code with controlled HTTP/auth/geolocation dependencies, including account switching while a location callback is pending. It also checks ownership-route permissions and company-calendar boundaries.
