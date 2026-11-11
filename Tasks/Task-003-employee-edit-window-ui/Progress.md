# Progress — Task-003

## Status snapshot
- Status: `Review`
- Owner: `Implementation agent`
- Last updated: `2026-11-10`
- Current focus: `Review-ready employee edit-window UI and API payload.`
- Blocker: `None`
- Next action: `Review the diff and browser evidence, then merge.`

## Acceptance criteria progress
- [x] Given an expired employee record and no override, when the staff list loads, then the employee row exposes can_edit false and a server-derived editable_until value. — Evidence: `EmployeeResource` computes both fields with `EditWindow`; `EmployeeEditWindowTest.php` asserts the expired manager payload.
- [x] Given that expired row, when the user attempts the edit action, then the action is disabled or explains the window and does not open a doomed save form. — Evidence: the manager menu shows a lock and the translated deadline/subtitle; click, keyboard Enter and direct handler all show the warning toast while no dialog opens.
- [x] Given a fresh record or an override holder, when the staff list loads, then can_edit is true and the edit form opens and saves. — Evidence: the feature test covers fresh save and override editability; the admin browser session shows the expired override row with no lock and its `Edit Staff` dialog opens.
- [x] Given a direct expired save that bypasses the UI, when it reaches the server, then the existing 422 business.edit_window_expired remains authoritative. — Evidence: `EmployeeEditWindowTest.php` sends the direct expired update and asserts 422 + the existing error code.
- [x] Given the updated staff screen, when checked in light, dark, LTR, RTL, and mobile widths, then the disabled state and explanation remain readable and keyboard accessible. — Evidence: Playwright checks at 1440px LTR/light and 390px LTR/light, RTL/dark Persian; the menu item grows to 78px with a 40px fully wrapped subtitle, and ArrowDown + Enter reaches the item and raises the translated warning.

## Work completed with evidence
- Added actor-derived `editable_until` and `can_edit` to employee API resources. The server remains authoritative; the UI never derives the window from the client clock.
- Added the typed employee response fields and updated the staff list to format the server timestamp for display.
- Blocked the staff edit action when `can_edit` is false: the menu item keeps the lock, explanatory subtitle and deadline, raises the existing translated warning on activation, and never opens the edit dialog. `openEditStaffDialog` also guards the state so no other call path can open the form.
- Kept fresh records and override holders on the normal edit path.
- Added focused feature coverage for expired payload/blocked write, fresh editable/save, and override editable state.

## Files changed and why
- `app/Http/Resources/EmployeeResource.php` — additive actor-specific `editable_until`/`can_edit` fields.
- `resources/ts/api/employee.ts` — typed employee response contract for both fields.
- `resources/ts/pages/staff_list.vue` — locked menu state, translated deadline formatting, toast guard, three-line mobile wrapping, and edit-dialog guard.
- `tests/Feature/EmployeeEditWindowTest.php` — expired/no-override, fresh/save, override, and direct-422 coverage.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-10 | `php artisan test tests/Feature/EmployeeEditWindowTest.php --stop-on-failure` | Local dev | PASS — 3 tests, 19 assertions | Terminal output | Focused Task-003 feature suite. |
| 2026-11-10 | `npm run typecheck` | Local dev | PASS | Terminal output | Run after the final `lines`/wrapping change. |
| 2026-11-10 | `php -l` on the changed PHP resource/test and scoped whitespace checks | Local dev | PASS | Terminal output | No syntax or scoped whitespace errors. |
| 2026-11-10 | Playwright manager, 1440px, light LTR | Local dev | PASS | `.playwright-cli/page-2026-11-10T12-06-38-294Z.png`, `.playwright-cli/page-2026-11-10T12-06-53-298Z.png` | Lock + deadline subtitle; clicking shows the warning toast and no dialog. |
| 2026-11-10 | Playwright manager, 390px, light LTR | Local dev | PASS | `.playwright-cli/page-2026-11-10T12-11-29-569Z.png` | Item height 78px; subtitle 40px with full message/date visible. |
| 2026-11-10 | Playwright manager, 390px, dark RTL (fa-AF) | Local dev | PASS | `.playwright-cli/page-2026-11-10T12-15-18-283Z.png`, `.playwright-cli/page-2026-11-10T12-15-23-036Z.png` | `dir=rtl`, `lang=fa-AF`, app classes `v-theme--dark v-locale--is-rtl`; translated message shown in the toast. |
| 2026-11-10 | Playwright admin override, 1440px, light LTR | Local dev | PASS | `.playwright-cli/page-2026-11-10T12-08-29-374Z.png`, `.playwright-cli/page-2026-11-10T12-08-57-063Z.png` | Expired override row has no lock/subtitle and opens `Edit Staff`. |
| 2026-11-10 | Keyboard path: focus trigger, Enter, ArrowDown ×4, Enter | Local dev | PASS | Terminal output | Locked item receives focus and its activation raises the warning; dialogs remain 0. |
| 2026-11-10 | Browser console check during the UI passes | Local dev | PASS | Terminal output | 0 console errors (5 existing warnings). |
| 2026-11-10 | Fixture cleanup: employees 36/37 and users 43/44 | Local dev | PASS | Terminal output | Both counts returned 0; browser sessions closed. |

## Decisions and deviations
- The task text names `resources/ts/api/employees.ts`, but the repository’s existing typed employee contract is `resources/ts/api/employee.ts`; that file was updated instead of adding a duplicate.
- The locked item is not disabled at the HTML level, so keyboard and touch users can still reach the explanation. Activation explains the closed window through the existing `common.messages.editWindowExpired` toast.
- The UI only formats `editable_until`; it does not calculate the one-hour boundary locally.
- No new locale keys or locale-file edits were needed.
- Vuetify’s three-line layout plus a minimum item height keeps the complete mobile hint and deadline visible.

## Blockers and open questions
- None.

## Handoff notes
- The server middleware and Task-002 route/seed behavior were not changed.
- Review the focused diff and the Playwright evidence above. Temporary browser fixtures have been removed.
- The task is intentionally `Review`, not `Done`.

## Activity log (append-only)
- `2026-11-10` Created task record; implementation has not started.
- `2026-11-10` Implemented the resource fields, typed API contract, staff-list lock/guard UI, and focused feature test.
- `2026-11-10` Focused feature test and typecheck passed; reviewed the UI in light/LTR desktop, light/LTR mobile, dark/RTL mobile, and override desktop sessions.
- `2026-11-10` Added three-line/mobile wrapping after finding the deadline clipped at 390px; rechecked full subtitle visibility and keyboard activation.
- `2026-11-10` Removed temporary employees/users and closed Playwright sessions; status moved to `Review`.
