# Task Set Index

- **Plan source/version:** `issuesv2/README.md`, `commission-issues/README.md` and `other-contracts-issues/README.md`, 2026-11-10 audits; user request 2026-11-10
- **Last updated:** `2026-11-10`
- **Status:** `Validated`
- **Validation command:** `python3 Tasks/scripts/validate_tasks.py Tasks`
- **Last validation result:** `PASS on 2026-11-10 — 9 tasks, 0 errors, 0 warnings`

## Tasks

| Task ID | Folder | Outcome | Status | Dependencies | Exclusive owner / file area | Validation status |
|---|---|---|---|---|---|---|
| Task-001 | `Task-001-decide-edit-window-policy/` | Decide the edit-window scope and override policy | Done | None | `Tasks/Task-001-decide-edit-window-policy/decision.md` | PASS 2026-11-10 |
| Task-002 | `Task-002-enforce-approved-edit-window/` | Enforce the approved edit-window policy | Review | Task-001 | `app/Http/Middleware/EnforceEditWindow.php`; `app/Support/EditWindow.php`; `routes/api.php`; `database/seeders/RolePermissionSeeder.php`; `database/seeders/PermissionSeeder.php`; `tests/Feature/PhaseFiveIntegrityTest.php`; `tests/Feature/EditWindowPolicyTest.php` | PASS 2026-11-10 |
| Task-003 | `Task-003-employee-edit-window-ui/` | Show employee edit-window state in the staff UI | Review | Task-002 | `app/Http/Resources/EmployeeResource.php`; `resources/ts/api/employees.ts`; `resources/ts/pages/staff_list.vue`; `tests/Feature/EmployeeEditWindowTest.php` | PASS 2026-11-10 |
| Task-004 | `Task-004-protect-contract-extension-lifecycle/` | Protect the contract extension lifecycle | Review | Task-001 | `app/Http/Controllers/ContractController.php`; `app/Models/Contract.php`; `app/Http/Resources/ContractResource.php`; `resources/ts/components/ContractForm.vue`; `resources/ts/pages/contract_list.vue`; `database/migrations/2026_11_10_000002_enforce_contract_extension_parent.php`; `tests/Feature/ContractExtensionTest.php`; `tests/Feature/ContractEditWindowTest.php` | PASS 2026-11-10 |
| Task-005 | `Task-005-repair-contract-details-menu/` | Repair the contract details row action | Review | None | `resources/ts/components/FullDetailsButton.vue` | PASS 2026-11-10 |
| Task-006 | `Task-006-unify-responsible-employee-assignment/` | Unify responsible-employee assignment | Review | None | `app/Services/Contracts/ContractGenerationService.php`; `app/Http/Controllers/ApplicantController.php`; `app/Support/ContractAccess.php`; `app/Http/Resources/ApplicantResource.php`; `resources/ts/pages/applicants.vue`; `tests/Feature/ResponsibleEmployeeAssignmentTest.php`; `tests/Feature/ResponsibleEmployeeVisibilityTest.php` | PASS 2026-11-10 |
| Task-007 | `Task-007-enable-teacher-class-completion/` | Enable assigned teachers to complete classes | Review | None | `resources/ts/pages/academy/classes.vue`; `resources/ts/plugins/1.router/routes.ts`; `resources/ts/navigation/horizontal/academy.ts`; `resources/ts/pages/setup/users.vue`; `resources/ts/api/user.ts`; `resources/ts/plugins/i18n/locales/en.json`; `resources/ts/plugins/i18n/locales/fa-AF.json`; `resources/ts/plugins/i18n/locales/ps-AF.json`; `tests/Feature/TeacherClassCompletionTest.php` | PASS 2026-11-10 |
| Task-008 | `Task-008-decide-academy-commission-path/` | Decide the Academy counselor commission path | Done | None | `Tasks/Task-008-decide-academy-commission-path/decision.md` | PASS 2026-11-10 |
| Task-009 | `Task-009-implement-academy-commission-path/` | Implement the approved Academy commission path | Review | Task-008 | `app/Http/Controllers/AcademyStudentController.php`; `app/Services/AcademyEnrollmentService.php`; `app/Services/CommissionService.php`; `app/Http/Resources/AcademyEnrollmentResource.php`; `resources/ts/components/academy/AcademyStudentDialog.vue`; `resources/ts/pages/academy/students.vue`; `tests/Feature/AcademyStudentFinanceTest.php`; `tests/Feature/AcademyContractCommissionTest.php`; `tests/Feature/AcademyCommissionPathTest.php` | PASS 2026-11-10 |

## Dependency layers
- **Layer 0:** Task-001 (policy decision), Task-005 (details menu), Task-006 (responsible assignment), Task-007 (teacher completion), Task-008 (Academy decision) — independent starts.
- **Layer 1:** Task-002 (edit-window implementation), Task-004 (contract extension integrity), Task-009 (Academy implementation) — require their layer 0 decision or no conflicting owner.
- **Layer 2:** Task-003 (employee edit-window UI) — requires Task-002 server behavior.

## Shared contracts and integration owners
| Contract/schema/interface | Exclusive owner task | Consumers | Freeze/compatibility rule |
|---|---|---|---|
| Edit-window scope, exemptions, and override policy | Task-001 | Task-002, Task-003, Task-004 | The written decision is authoritative; implementation tasks must not invent a different policy. |
| `business.edit_window_expired` and `edit_window_overridden` audit contract | Task-002 | Task-003, Task-004 | Keep the 422 code, translated message, and audit row unless Task-001 explicitly changes them. |
| Contract extension parent invariant and `snapshot_data.extension_of` | Task-004 | Contract register, contract printing, extension tests | Create-only parent; an edit must never clear or reparent an extension. |
| Responsible-employee pivot and singular compatibility mirror | Task-006 | Applications, case access, contract visibility, commission read paths | Plural pivot is authoritative; singular is a derived primary mirror. |
| Academy counselor commission path | Task-008 | Task-009, commission ledger, Academy students UI | The decision chooses one path; direct and contract-based Academy paths must not double-pay. |

## Cross-cutting coverage
| Concern | Owning task(s) | Acceptance/verification evidence |
|---|---|---|
| Permissions and data scope | Task-001, Task-002, Task-004, Task-006, Task-007 | Role seeds, case scope, contract access, teacher assignment, and negative access cases. |
| Migration/backfill and rollback | Task-004, Task-009 | Idempotent migration checks; orphan audit; existing-enrollment treatment; reversible rollback notes. |
| RTL, dark theme, responsiveness, accessibility | Task-003, Task-005, Task-007 | Manual viewport/theme/direction checks and keyboard reachability. |
| Regression coverage | Task-002, Task-003, Task-004, Task-006, Task-007, Task-009 | Focused feature tests plus typecheck; no task may claim a pass it has not run. |

## Unresolved blockers
| Decision/dependency | Affected tasks | Owner | Next action |
|---|---|---|---|
| None | None | None | Employee-only edit-window policy is approved; Task-002 and Task-004 are unblocked, and Task-003 follows Task-002. |
| None | None | None | No Academy commission decision remains open. |

## Validation history
Append date, command, result, issue/error count and follow-up owner after each validation run.
- `2026-11-10` — Task set created; validator not run yet.
- `2026-11-10` — `python3 Tasks/scripts/validate_tasks.py Tasks` → PASS; 9 tasks, 0 errors, 0 warnings. Structural and ownership validation only; Task-001 and Task-008 remain product-decision blockers.
- `2026-11-10` — Task-005 moved to Review after component change and local UI/typecheck evidence; validator PASS.
- `2026-11-10` — Task-007 moved to Review after focused backend, typecheck, locale, Playwright, and database evidence; temporary fixtures cleaned up; validator PASS.
- `2026-11-10` — Task-008 decision approved (retire Academy counselor commission); Task-009 implemented the retirement gate and teacher-position guard; Academy commission suites PASS 8 tests / 48 assertions; validator PASS.
- `2026-11-10` — Task-001 decision approved (employee-only edit window); Task-002/Task-004 unblocked and Task-003 follows Task-002; validator PASS.
- `2026-11-10` — Task-002 implemented employee-only route mounting and the manager `edit_employees` grant; policy + PhaseFive suites PASS 8 tests / 60 assertions; status Review; validator PASS (9 tasks).
- `2026-11-10` — Task-003 implemented actor-specific employee editability fields, the locked staff action, mobile/RTL/dark wrapping fixes, and focused coverage; suite PASS 3 tests / 19 assertions, typecheck PASS, Playwright light/LTR, dark/RTL, mobile, override and keyboard checks PASS, temporary fixtures removed; status Review; validator PASS (9 tasks).
- `2026-11-10` — Task-004 implemented extension parent preservation/guards, duplicate and party validation, the orphan audit migration, resource/UI Extend state, and focused coverage; suite PASS 21 tests / 147 assertions, typecheck PASS, migration rollback/re-apply PASS, live API before/after evidence and both register menu checks PASS, temporary fixtures removed; status Review; validator PASS (9 tasks).
