# Task Task-007 — Enable assigned teachers to complete classes

## Metadata
- Task ID: `Task-007`
- Title: `Enable assigned teachers to complete classes`
- Initial status: `Ready`
- Type: `UI`
- Priority: `P1`
- Owner role: `Vue Academy implementer`
- Estimated size: `S`
- Source plan/version: `commission-issues/LOGIC-20261110-002/issue.md`, 2026-11-10 review
- Requirement / behavior IDs: `LOGIC-20261110-002`

## Objective
Let an employee assigned as a class teacher complete that class from the Academy Classes UI, while keeping the action unavailable to teachers who are not assigned. Also make the existing Teacher role selectable in the Users screen.

## Context and problem
The backend already permits an assigned teacher to call the complete endpoint, but `classes.vue` renders the complete action only when `canManage` (the `manage_academy_classes` permission) is true. A teacher has the default Teacher role, which does not include that permission, so the assigned teacher sees only Roster. The Users role options also omit `teacher`, so the account cannot be given the Teacher role through the UI.

## Scope
### Included
- Show Complete class to a user who is assigned to that class as a teacher, in addition to users with `manage_academy_classes`.
- Keep Complete class hidden from teachers who are not assigned to the row.
- Let a teacher-only account reach the Academy Classes route and navigation item through the same `view_academy_attendance` permission the class-list API already requires.
- Load the page's manager-only program and teacher options only for class managers, so a teacher session is not failed by unrelated master-data requests.
- Add `teacher` to the typed frontend role union, the Users role options, and all three locale labels.
- Confirm the existing backend authorization still limits completion to assigned teachers or Academy managers.
- Add a focused regression test for the assigned-teacher endpoint behavior.

### Excluded
- Changing the backend completion rules, commission calculation, or teacher assignment data model.
- Redesigning the Academy Classes page or attendance workflow.
- Allowing every teacher to complete every class.
- Academy student enrollment or counselor commission; Task-009 owns that.

## Preconditions and assumptions
- The backend `AcademyClassController::complete` already accepts an assigned teacher and remains the authority.
- The class API response includes the teacher rows needed to compare the signed-in employee; if it does not, expose the minimum identifier without broadening the payload.
- The `teacher` role exists in the backend role catalogue; this task makes it assignable in the UI.

## Dependencies
- `None` — no upstream task is required; the backend behavior already exists.

## Contracts and boundaries
- Permission contract: `manage_academy_classes` may complete any class; a teacher may complete only an assigned class; no other role gains completion rights.
- UI contract: the action is rendered only when the current user is an Academy manager or is in the row's teacher set.
- Role contract: `teacher` is a valid `UserRole` in the frontend and has a translated label in en, fa-AF, and ps-AF.
- Route/navigation contract: `/academy/classes` and its navigation item use `view_academy_attendance`, matching `GET /api/academy/classes`; class visibility remains scoped by the API to the teacher's assigned classes.
- API contract: no change to the completion endpoint or its response shape unless the class resource is missing a stable teacher identifier.
- Files/modules this task must not modify: `app/Http/Controllers/AcademyClassController.php`, commission services, student enrollment pages, and contract screens.

## Exclusive file ownership
- `resources/ts/pages/academy/classes.vue` — per-row completion availability and manager-only option loading.
- `resources/ts/plugins/1.router/routes.ts` — allow attendance-authorized teachers to reach the classes route.
- `resources/ts/navigation/horizontal/academy.ts` — expose the classes navigation item to attendance-authorized teachers.
- `resources/ts/pages/setup/users.vue` — Teacher role option.
- `resources/ts/api/user.ts` — typed teacher role.
- `resources/ts/plugins/i18n/locales/en.json` — Teacher label.
- `resources/ts/plugins/i18n/locales/fa-AF.json` — Teacher label.
- `resources/ts/plugins/i18n/locales/ps-AF.json` — Teacher label.
- `tests/Feature/TeacherClassCompletionTest.php` — assigned and unassigned teacher authorization.

## Acceptance criteria
- [ ] Given an assigned teacher opens Academy Classes, when the class is not completed, then the Complete class action is available.
- [ ] Given an unassigned teacher opens the same page, when the class is not completed, then the Complete class action is not available.
- [ ] Given a super admin opens Users, when choosing roles, then Teacher is present and can be saved on an account.
- [ ] Given the assigned teacher completes the class, when the request finishes, then the class is completed and the existing teacher commission path still runs.
- [ ] Given the role option is added, when the three locales render, then the Teacher label is translated and the page passes typecheck.

## Verification plan
- Automated checks: `php artisan test tests/Feature/TeacherClassCompletionTest.php tests/Feature/AcademyPermissionTest.php`; `npm run typecheck`.
- Manual/UI scenarios: sign in as an assigned teacher and an unassigned teacher; confirm the action visibility and completion result. Sign in as super admin and assign the Teacher role through Users.
- Expected result: only the assigned teacher or an Academy manager can complete; the teacher role is selectable and translated.
- Regression: completion still creates the existing teacher commission and does not alter student enrollment.
- Required evidence: test output, typecheck output, and observations from the teacher and admin sessions.

## Implementation notes
- Reuse the class row's teacher data rather than adding a new permission or a second API request per row.
- Keep the manager path unchanged; this is an additive condition, not a replacement.
- If the backend rejects `teacher` during user save, stop and add the role validation file to this task's ownership before changing it; do not silently fall back to `staff`.
- Use the existing translation structure and avoid hard-coded English labels.

## Risks and rollback
- Risk: comparing the wrong user identifier could hide the action from the assigned teacher or show it to the wrong teacher.
- Risk: adding `teacher` to the UI without backend role support would produce a confusing save failure; verify the role catalogue first.
- Risk: the action could be shown after a class is already completed; keep the existing completed guard.
- Rollback: restore the previous `canManage` condition and remove the role option and translations; no data migration is required.

## Handoff checklist
- [ ] Assigned teacher can complete; unassigned teacher cannot.
- [ ] Teacher role is selectable and translated.
- [ ] Existing teacher commission path remains intact.
- [ ] Typecheck and focused backend tests pass.
