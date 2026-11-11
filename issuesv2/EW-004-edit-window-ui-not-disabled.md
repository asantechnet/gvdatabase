# EW-004 — Employee and contract forms stay editable when the window is closed; only comments/subtasks are disabled

- **Severity:** Low (the requirement allows an error instead of disabling the control)
- **Area:** Employee, contract, and task forms
- **Status:** Confirmed in the live UI
- **Confidence:** High

## Summary

The server-side window is enforced, but the UI does not reflect it for most records. Only comments and subtasks expose `editWindowExpired`/`canEdit` from their API resources and disable the edit action. Employee and contract forms remain fully interactive; the user can complete a multi-step form and only learns about the window when Save returns 422.

The toast is clear and translated:

> This item can only be edited during the first hour after creation.

## Expected

- The edit action should be disabled (with the reason and, ideally, the deadline) when the window is closed, **or** the form should show an immediate, specific error.
- The same treatment should apply consistently across employee, task, and contract records.

## Actual

- Employee: the row menu offered **Edit staff record**; the three-step dialog accepted changes; **Save** sent `PUT /api/employees/2` and received 422. The dialog remained open with the error.
- Contracts: the Edit action remains available for drafts, and the API bypass means it may succeed regardless of age (EW-003).
- Tasks: the task edit surface remains available; an old task save is rejected by the middleware (EW-002).
- Only `CommentResource`/`SubtaskResource` expose `editWindowExpired` and `canEdit`; `TaskCommentsDrawer` and `TodoMatrixView` use them to disable the relevant actions.
- Employee and contract resources do not expose an `editableUntil`/`editWindowExpired` field, so their UIs cannot show the state without an extra request.

## Impact

- Users waste time filling a form that can never be saved.
- The failure appears only at the end and can look like a broken save rather than a policy deadline.
- Behaviour is inconsistent between record types, which makes the rule harder to learn.
- The requirement is literally satisfied (“disabled **or** give error”), but the user experience is poor.

## Suggested direction (report only; no code changed)

- Add `editableUntil`/`editWindowExpired`/`canEdit` to the employee, task, and contract resources using the same `EditWindow` service.
- Disable or clearly annotate edit actions when the window is closed; show the deadline and the override path for users who have it.
- For forms already open, surface the deadline before submission rather than only after the 422.

## Evidence

- Playwright manager session: **Edit staff record** was enabled on a record created the previous day; the form accepted changes; `PUT /api/employees/2` → 422; toast text above.
- Source: `app/Http/Resources/CommentResource.php` and `SubtaskResource.php` expose the flags; `resources/ts/components/task-manager/components/TaskCommentsDrawer.vue` and `TodoMatrixView.vue` consume them.
- No employee/contract resource equivalent was found.
