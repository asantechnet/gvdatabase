# LOGIC-20261110-002 — Assigned teacher cannot complete the class from the UI

## Severity: High

## Status: Confirmed

## Reproduction

1. As super admin, create a class with a fee and assign Test Teacher (employee 10).
2. Sign in as `teacher.commission@example.test`, the account linked to Test Teacher.
3. Open `/academy/classes`.
4. The class is visible with “Test Teacher” in the Teachers column, but the Actions column contains only **Roster**. There is no **Complete class** action.
5. Sign in as super admin; the same row shows **Complete class**.

## Expected

An employee assigned as the class teacher should be able to mark that class complete. The backend endpoint is designed to allow this: `AcademyClassController::complete` accepts a user with `manage_academy_classes` **or** an employee assigned to the class.

## Actual

The frontend hides the action behind `canManage = canAccess('manage_academy_classes')` in `resources/ts/pages/academy/classes.vue`. The assigned teacher has the default **Staff** role, so the button is never rendered.

The Users role list in `resources/ts/pages/setup/users.vue` also omits `teacher`, so Test Teacher cannot be given the teacher role through the UI. The account remains Staff.

## Impact

The exact requested teacher path is blocked. The super admin can complete the class, and the resulting teacher commission is correct (10,000 AFN × 10% = 1,000 AFN, pending), but the teacher cannot perform the action themselves.

## Evidence

- `evidence/teacher-has-no-complete-action.png`
- `evidence/admin-class-complete-button.png`
- `evidence/roles-menu-no-teacher.png`

## Relevant source

- `resources/ts/pages/academy/classes.vue` — `canManage` and the Complete button condition
- `app/Http/Controllers/AcademyClassController.php` — backend allows an assigned teacher
- `resources/ts/pages/setup/users.vue` — static roles list omits `teacher`
