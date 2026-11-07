# Plan B Task Index — service-case operations

- **Plan source/version:** User request 2026-11-05, described in `Task-031-service-case-operations/Task.md`.
- **Status:** `Alternative — not part of the parallel implementation set`
- **Validation command:** `python3 issues/tasks/scripts/validate_tasks.py issues/tasks/dilemma/Plan-B-service-case-operations`
- **Last validation result:** `PASS (2026-11-05), 1 task, 0 errors` (validated as an isolated alternative)

## Tasks

| Task ID | Folder | Outcome | Status | Dependencies | Exclusive owner / file area | Validation status |
|---|---|---|---|---|---|---|
| Task-031 | `Task-031-service-case-operations/` | Service cases can create/complete their own tasks and open the existing per-line finance flow; the wrapper and Applications list stay unchanged and finance is not duplicated | Ready | None | `app/Http/Controllers/TaskController.php`, `resources/ts/pages/service-case.vue`, `resources/ts/api/serviceCases.ts`, `resources/ts/api/task.ts`, `resources/ts/api/kanban.ts`, `resources/ts/utils/apiValidation.ts`, `resources/ts/components/income/ServicePaymentsDialog.vue`, `resources/ts/pages/income.vue`, `resources/ts/plugins/i18n/locales/en.json`, `resources/ts/plugins/i18n/locales/fa-AF.json`, `resources/ts/plugins/i18n/locales/ps-AF.json`, `tests/Feature/ServiceCaseTaskActionsTest.php`, `tests/Feature/ServiceCaseIsolationTest.php`, `tests/Feature/ApplicantTaskIntegrationTest.php`, `tests/Feature/IncomeReceiptTest.php` | Passed |
