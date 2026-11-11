# Edit Window & Contract Extension Review — 2026-11-10

- **Reviewer:** Buffy (AI), live UI session with Playwright CLI plus read-only source trace
- **Environment:** local Laravel app at `http://127.0.0.1:8000`, seeded demo database
- **Primary account:** `zahra.aziz@example.test` (role `manager`, no override permission)
- **Scope:** the one-hour edit window (employee records and the shared middleware) and extending employment / other contracts
- **Mode:** read-only review. No source code was changed. Temporary test permissions and data were restored; see **Cleanup**.

This package covers the edit-window and contract-extension audit only. The earlier bonus and scheduler findings are not duplicated here.

## Verdict

The 60-minute mechanism itself is implemented correctly: it is server-side, uses `created_at + 60 minutes`, rejects expired edits with **422 `business.edit_window_expired`**, and allows `override_edit_window` with an audit row. However, the feature is **not comprehensive and not sane as configured**:

- No seeded non-admin role can edit employee records, and the only role that can (`admin`) also has the override, so the window is effectively dormant for every seeded employee editor.
- The middleware is mounted app-wide, so the same one-hour gate blocks old task edits and other normal workflow updates — not just employee records.
- Several update routes, including generic contract updates, bypass the middleware entirely because their controllers take scalar IDs instead of route-bound models.
- Employment and other contract extension both work and link a draft to the parent, but editing an extension destroys that link, and an extension can be created without a duplicate-renewal or same-party guard.

## Issues

| ID | Severity | Status | Title |
| --- | --- | --- | --- |
| [EW-001](EW-001-seeded-roles-cannot-exercise-edit-window.md) | High | Confirmed | No seeded non-admin role can edit employee records; `admin` always overrides the window |
| [EW-002](EW-002-edit-window-is-app-wide.md) | High | Confirmed | The edit window is global, not employee-specific, and blocks normal task/workflow edits |
| [EW-003](EW-003-contract-routes-bypass-edit-window.md) | High | Confirmed | Contract update routes bypass the edit window because controllers use scalar IDs |
| [EW-004](EW-004-edit-window-ui-not-disabled.md) | Low | Confirmed | Employee/contract forms are not disabled or warned when the window is closed; only comments/subtasks are |
| [EW-005](EW-005-missing-edit-window-coverage.md) | Medium | Confirmed | No regression coverage for the employee or contract edit-window paths |
| [CE-001](CE-001-editing-extension-clears-parent.md) | High | Confirmed | Editing a draft contract extension clears `parent_contract_id` and `snapshot_data.extension_of` |
| [CE-002](CE-002-no-live-renewal-guard.md) | High | Source-confirmed | No guard against extending a primary that already has a live renewal |
| [CE-003](CE-003-extension-party-not-validated.md) | High | Source-confirmed | An extension may point at a different employee/applicant than its parent |
| [CE-004](CE-004-extension-parent-invariant-unenforced.md) | Medium | Confirmed | `contract_role = extension` can exist with no parent; no model/DB invariant |

## Verified working

### Edit window

- Employee record older than one hour: `PUT /api/employees/2` returned **422 `business.edit_window_expired`**, and the UI showed the translated toast.
- After granting `override_edit_window` to the manager temporarily, the same save returned **200** and produced an `edit_window_overridden` audit row with `editable_until` and `override_used_at`.
- The middleware correctly lets a `super_admin` bypass the check via `hasPermission()` / `isSuperAdmin()`.

### Contract extension

- Employment contract `CTR-26-009` → **Extend contract** created `CTR-26-026`: role `extension`, status `draft`, parent `9`.
- Other contract `CTR-26-022` → **Extend contract** created `CTR-26-027`: role `extension`, status `draft`, parent `22`.
- The parent contracts were not modified, and both extension creations returned **201 Created**.
- Backend extension rules passed: employment extension, cancelled-parent rejection, extension-as-parent/type-mismatch rejection — **3 tests / 22 assertions**.
- The shared list only exposes **Extend** for a primary, non-cancelled record in `active` or `completed` state; extensions are always issued as drafts.

## Reproduction environment

The manager account originally had no `edit_employees`, so the edit dialog could not be reached. For the employee test only, `edit_employees` was temporarily attached to the `manager` role, then removed. This is itself part of finding EW-001.

## Cleanup

- Removed the temporary `edit_employees` and `override_edit_window` grants from the `manager` role.
- Restored employee 2’s contact number to `+93 700 100 001`.
- Deleted the two test extension drafts (`CTR-26-026`, `CTR-26-027`).
- Revoked the temporary API token and closed the Playwright browser.
- The `edit_window_overridden` audit row from the test remains because audit history is append-only.
- Pre-existing orphan extensions (IDs 21, 23, 25) were not deleted; they are evidence for CE-001/CE-004.

## Checks run

- Playwright CLI as a real manager account: staff-record edit, override retry, employment extension, other-contract extension.
- API checks: expired employee edit **422**, old task edit **422**, old draft contract edit **200**, extension creation **201**.
- `php artisan test tests/Feature/PhaseFiveIntegrityTest.php --filter='comment_and_subtask|administrative_edit_window'` → **2 passed / 17 assertions**.
- `php artisan test tests/Feature/ContractExtensionTest.php --filter='employment_contract_extends|extension_contract_is_never|cancelled_contract_cannot'` → **3 passed / 22 assertions**.

## Limitations

- A fresh (<1 hour) employee edit was not driven through the UI; the open-window branch is the same `isOpen()` check covered by the comment/subtask test, and the successful override save verifies the write path.
- Not every PUT/PATCH route was executed. EW-002 and EW-003 combine one live reproduction with source-level route/middleware analysis.
- The duplicate-renewal and mismatched-party checks in CE-002/CE-003 were source-confirmed but not executed destructively against a real contract.
- Contract extension printing and the separate “View full details” defect already tracked in `other-contracts-issues/DETAILS-20261110-001` were not re-tested here.

## Related existing report

`other-contracts-issues/EXT-20261110-001/issue.md` independently confirmed the extension-parent loss; CE-001 consolidates it into this issue set with the current database evidence.
