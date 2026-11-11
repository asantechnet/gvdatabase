# Task Task-003 — Show employee edit-window state in the staff UI

## Metadata
- Task ID: `Task-003`
- Title: `Show employee edit-window state in the staff UI`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Vue and Laravel resource implementer`
- Estimated size: `M`
- Source plan/version: `issuesv2/README.md`, 2026-11-10 audit; Task-002 implementation
- Requirement / behavior IDs: `EW-004`, `EW-005`

## Objective
Make the staff screen show the server-computed editability of each employee and prevent users from opening a save flow that the server will reject. The server remains the authority; the UI only explains and reflects its decision.

## Context and problem
The server now enforces the approved window, but the staff list still presents the same edit action for every record. A user can open the employee form, fill it, and only learn on save that the record is past the window. The audit confirmed that only the comment and subtask UIs expose expired state; employee and contract forms do not. This task adds a server-derived row-level state so the staff screen can explain the restriction before the user starts an edit, while keeping the server as the final guard.

## Scope
### Included
- Add additive employee payload fields for the active user: `editable_until` and `can_edit`.
- Carry those fields through the typed employees API.
- Disable or clearly block the employee edit action when the window is closed, with a translated explanation and accessible tooltip or inline text.
- Keep fresh records and override holders fully editable.
- Cover expired, fresh, and override cases in a feature test.

### Excluded
- Changing the middleware, route mounting, role seeding, or error code; Task-002 owns those.
- Contract forms and contract row actions; Task-004 owns those.
- Employee domain fields, permissions, payroll, or attendance behavior.
- Client-side clock calculations as proof of editability.

## Preconditions and assumptions
- Task-002 has implemented the approved server policy and exposes a stable rejection.
- The employee list response is already loaded through the typed API layer, not raw page Axios calls.
- The UI may use the server's `editable_until` for display but must not extend the window locally.

## Dependencies
- `Task-002` — requires the enforced server policy and the seeded editor path.

## Contracts and boundaries
- API contract: additive `editable_until` (ISO timestamp or null) and `can_edit` (boolean) fields on employee records for the requesting actor.
- Error contract: direct expired writes still return `422 business.edit_window_expired`; the UI does not replace that response.
- Permission contract: `override_edit_window` produces `can_edit: true` for an expired record; no other permission should imply editability.
- Compatibility: existing employee fields and list pagination/filtering remain unchanged.
- Files/modules this task must not modify: middleware, routes, seeders, contract components, and the employee controller's domain rules.

## Exclusive file ownership
- `app/Http/Resources/EmployeeResource.php` — actor-specific editability fields.
- `resources/ts/api/employees.ts` — typed response fields.
- `resources/ts/pages/staff_list.vue` — edit action state and explanation.
- `tests/Feature/EmployeeEditWindowTest.php` — resource and UI-contract coverage.

## Acceptance criteria
- [ ] Given an expired employee record and no override, when the staff list loads, then the employee row exposes can_edit false and a server-derived editable_until value.
- [ ] Given that expired row, when the user attempts the edit action, then the action is disabled or explains the window and does not open a doomed save form.
- [ ] Given a fresh record or an override holder, when the staff list loads, then can_edit is true and the edit form opens and saves.
- [ ] Given a direct expired save that bypasses the UI, when it reaches the server, then the existing 422 business.edit_window_expired remains authoritative.
- [ ] Given the updated staff screen, when checked in light, dark, LTR, RTL, and mobile widths, then the disabled state and explanation remain readable and keyboard accessible.

## Verification plan
- Automated checks: `php artisan test tests/Feature/EmployeeEditWindowTest.php`; `npm run typecheck`.
- Manual/UI scenarios: sign in as a non-override manager and an override holder; inspect expired and fresh employee rows; attempt the disabled action and the direct save path.
- Expected result: expired rows cannot start a successful edit, fresh/override rows can, and the direct API still returns the documented 422.
- Accessibility/responsive check: keyboard focus, visible explanation, dark theme, RTL, and mobile width.
- Required evidence: test output, typecheck output, screenshots or recorded observations for each theme/direction state.

## Implementation notes
- Keep the UI state derived from the API payload; do not duplicate the 60-minute arithmetic in Vue.
- If the list does not refresh while a record ages past the boundary, the server response remains the final guard; show the translated API error rather than silently swallowing it.
- Use the existing `vue-toastification` conventions for any toast.
- Do not add a new global permission or a client-only override switch.

## Risks and rollback
- Risk: stale list data may still show an editable action just before expiry; direct save must handle the 422 gracefully.
- Risk: disabling the row action could hide the explanation on touch devices; use an accessible inline hint or menu item.
- Risk: exposing `editable_until` for every employee could leak timing metadata; it is not sensitive beyond the record age, but keep it scoped to the actor's ability.
- Rollback: remove the additive fields and restore the previous action condition; server enforcement remains in Task-002.

## Handoff checklist
- [ ] Employee payload fields are additive and typed.
- [ ] Expired, fresh, and override states are covered by tests.
- [ ] The direct 422 path remains visible to the user.
- [ ] RTL, dark theme, and mobile behavior are checked.
