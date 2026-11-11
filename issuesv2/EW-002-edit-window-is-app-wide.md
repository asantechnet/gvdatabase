# EW-002 — The one-hour window is app-wide, not employee-specific, and blocks normal workflow edits

- **Severity:** High
- **Area:** API middleware, task/workflow editing
- **Status:** Confirmed by live API request plus source trace
- **Confidence:** High

## Summary

`EnforceEditWindow` is attached to the entire authenticated API group in `routes/api.php`, not just to employee routes. It gates every PUT/PATCH that targets a route-bound Eloquent model older than 60 minutes.

The middleware has only a small carve-out list for actions that enforce their own rules or are workflow transitions:

- `SubtaskController@update`
- `CommentController@update`
- `IncomeController@approve` / `reject`
- `ExpenseController@approve` / `reject`

Everything else that is bound is blocked after an hour. I confirmed this with an old task: a manager editing task 1 (1,285 minutes old) received 422 `business.edit_window_expired`.

## Expected

- If the policy is **employee-only**, only employee routes should carry the middleware.
- If the policy is **global**, normal workflow actions (task moves, attendance corrections, off-day approvals, audit reviews, applicant/visitor updates) need explicit exemptions or a separate “workflow transition” path that does not rewrite record content.

## Actual

- `routes/api.php:103` mounts `edit-window` on the whole authenticated group.
- `app/Http/Middleware/EnforceEditWindow.php` only inspects PUT/PATCH and uses the last bound, timestamped model in the route.
- The exemption list covers comments, subtasks, and draft finance approvals only.
- By source inspection, old bound workflow routes such as off-day approve/reject, attendance update/correction, audit review, visitor/applicant edits, task update/move, and advances are subject to the same 60-minute gate.
- The confirmed task request:
  `PUT /api/tasks/1` → **422 `{"success":false,"code":"business.edit_window_expired"}`**.

## Impact

- A manager cannot move or revise a task once it is an hour old, which is most of the working day.
- Approval and correction queues can become unactionable for non-override users.
- Only `admin` has the override by default (and `super_admin` bypasses), so ordinary managers are left with no workaround.
- The employee window is therefore not a focused HR control; it is a global record-freeze policy wearing an employee-window label.

## Suggested direction (report only; no code changed)

- Decide the intended scope. If employee-only, move `edit-window` off the authenticated group and onto the employee update routes (or enforce it in the employee policy).
- If global, enumerate and exempt every workflow action that is a state transition rather than a content edit, and document the rule per module.
- Add a regression test for a non-employee bound route (task update or move) to make the scope explicit.

## Evidence

- `routes/api.php:103` — global `edit-window` middleware.
- `app/Http/Middleware/EnforceEditWindow.php` — `SELF_ENFORCED_ACTIONS` and `editedRecord()`.
- Live API result as manager: `PUT /api/tasks/1` → 422; task 1 `created_at=2026-11-09 12:30:14`, age 1,285 minutes.
