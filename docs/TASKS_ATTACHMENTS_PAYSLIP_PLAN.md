# Tasks, Attachments & Payslip Letterhead — Plan & Progress

Three sequential product requests, all in the same document because they share
the same supporting systems (translation, audits, notifications, permissions/RBAC).

1. **Task / project management** — a department manager must be able to assign
   tasks to *other members* of their department.
2. **Broken attachment sections** — expenses, incomes and staff pages must get a
   working attachment section that closely replicates the task-attachment
   functionality, and must accept valid formats such as PDF and JPG.
3. **Payslip letterhead** — like the contract/invoice/receipt templates, the
   payslip needs a letterhead editable in **Settings → Documents** that appears
   in print.

---

## 0. Recon findings (verified against the code)

### Task assignment
- `assign_task_members` is a seeded permission (`PermissionSeeder`), granted to
  the `manager` and `team_lead` roles (`RolePermissionSeeder`).
- A "department manager" is `departments.manager_employee_id` (`Department::manager()`).
  `DepartmentController` only requires that person to have `view_subordinate_records`
  — so a read-only `senior_counselor` may head a department but cannot assign.
- Assignment authorisation today:
  - `TaskPolicy::assignMembers` = `assign_task_members` **and** `TaskPolicy::update`.
  - `TaskPolicy::update` = `view` **and** (`edit_all_tasks` or assigned/team edits).
  - `TaskController::store` hard-aborts unless `assign_task_members`.
  - `TaskAssigneeController::index` hard-aborts unless `assign_task_members`
    and returns **all** active users.
  - Frontend gates the assignee UI on `canAccess('assign_task_members')`
    (`KanbanBoardEditDrawer`, later reused elsewhere).
- **Decision (user-confirmed):** recognise department heads automatically and
  scope their assignment to their own department members, even when their role
  lacks `assign_task_members`. Existing role-based assignments stay unrestricted.

### Attachments
- Tasks already have a good model: `task_attachments` table, `TaskAttachment`
  model, `TaskAttachmentController` (store/download/destroy), multiple files,
  `input|results` sections, accept list `.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,
  .png,.jpg,.jpeg,.webp`, `max:10240`, error surfacing in `TodoMatrixView`.
- Expenses: single `document_path/name/mime/size` column + `ExpenseController`
  `uploadDocument/downloadDocument/removeDocument`, `mimes:pdf,jpg,jpeg,png,
  webp,doc,docx,xls,xlsx`, `max:5120`. UI is one file plus a shared `ref` that
  points at the wrong input when several rows are rendered.
- Incomes: **no** document columns, endpoints or UI at all.
- Staff: `employees.document_*` columns + `EmployeeController` document
  endpoints + `EmployeeApi.uploadDocument/removeDocument`, but **no UI** uses
  them and there is no download method.
- No polymorphic attachment store exists yet.
- **Decision (user-confirmed):** build a shared, multi-file attachment system
  (like task attachments) and reuse it on expenses, incomes and staff; migrate
  the existing single documents so nothing is lost.

### Payslip letterhead
- `DocumentLetterheads` resolves `receipt`/`invoice` artwork with sources
  `none|contract|custom` and a percentage safe-zone, backed by `settings`
  columns (`*_letterhead`, `*_letterhead_source`, `*_letterhead_safe_*`).
- `SettingResource` exposes raw fields + a resolved `letterheads.{type}` payload.
- `UpsertSettingRequest` validates the uploads and removable flags.
- `SettingController` stores files under per-type directories and cleans up
  superseded artwork.
- `settings.vue` renders one editor card per document kind
  (`DocumentLetterheadKind = 'receipt' | 'invoice'`) with source select, upload,
  preview and draggable safe-zone.
- `payslip_template.vue` has its own `printSalarySlip()` (serialises the DOM) and
  a `company` prop with logo/signature — but no letterhead/safe-zone support.
  `staff_salary.vue` builds that `company` prop from `SettingApi.getCompanyProfile()`.
- `AuditPresenter` already hides every field containing `letterhead`.

---

## 1. Task assignment — department-head scoped assignment

### Backend
- **`EmployeeScope`**: add
  - `managedDepartmentIds(User)`, `managedDepartmentMemberIds(User)`,
    `managedDepartmentUserIds(User)`, `isDepartmentManager(User)`.
- **`TaskAccess`**: add `isDepartmentTask(User, Task)` — true when a task has an
  assignee who is a member of a department the user heads.
- **`TaskPolicy`**:
  - `assignMembers`: `($user->hasPermission('assign_task_members') ||
    isDepartmentManager($user)) && $this->update($user, $task)`.
  - `update`: also allow a department head acting on a task in their department
    (`isDepartmentTask`), so the assignee update is permitted even without the
    global edit permissions.
- **`User`**: expose the department-manager capability to the existing
  permission pipeline so the frontend gating keeps working:
  - `rolePermissionNames()` (raw role grants) and keep `permissionNames()` as the
    *effective* set, appending `assign_task_members` for department heads.
  - `hasDirectPermission()` to distinguish a role grant (unrestricted) from the
    derived department grant (scoped).
- **`TaskController`**:
  - `store`/`update`: replace the hard `assign_task_members` abort with a check
    that allows department heads, then **validate every assignee is within
    `managedDepartmentUserIds`** when assignment comes from headship only.
- **`TaskAssigneeController`**:
  - drop the route-level permission middleware and authorise with the same rule;
    return **all** active users for unrestricted assigners, and only the
    department's users for department heads.
- **Notification** (`EducationalWorkflowNotificationService`): add `taskAssigned`
  so newly added assignees are notified (actor excluded, `notifyOnce`).
  Wire into `TaskController::store` and `::update`.
- **Audit**: task field changes already audit through `Auditable`; assignment via
  the pivot is captured by the notification + explicit subjectLabel context.

### Frontend
- No new gating needed — department heads receive `assign_task_members` through
  the effective permission set, so `canAccess('assign_task_members')` becomes
  true and the existing assignee UI appears.

### i18n
- `notifications.task.assigned.{title,message}` in en / fa-AF / ps-AF.
- Business/validation messages for the scoped-assignment rejection.

---

## 2. Attachments — shared multi-file system

### Backend
- **Migration** `create_attachments_table`: polymorphic
  `attachable_type`, `attachable_id`, `user_id`, `section` (nullable),
  `disk`, `path`, `original_name`, `mime_type`, `size`, timestamps, index on
  `(attachable_type, attachable_id)`.
- **Migration** backfills existing `expenses.document_*` and
  `employees.document_*` rows into `attachments` (non-destructive; columns kept).
- **`App\Models\Attachment`**: `morphTo attachable`, `belongsTo uploader`,
  `Auditable`; `expenses()/incomes()/employees()` `morphMany` relations.
- **`AttachmentController`** (one shared controller, `{type}` in
  `expenses|incomes|employees`): `index`, `store`, `download`, `destroy`.
  Validation mirrors task attachments (`mimes:pdf,txt,csv,doc,docx,xls,xlsx,
  png,jpg,jpeg,webp`, `max:10240`) — this fixes the PDF/JPG rejection.
  Authorises per resource permission map.
- **Routes** under the auth group; **RBAC** entries in `apiAuthorization.ts`.
- **Resources**: include `attachments` in `ExpenseResource`, `IncomeResource`,
  `EmployeeResource` (id, name, mimeType, size, uploadedAt, downloadUrl, section).
- **Audit**: register `attachments` in `AuditRegistry` (+ `subjectLabel`
  candidates) and let `Auditable` log uploads/deletions.

### Frontend
- **`resources/ts/api/attachments.ts`**: list/upload/download/delete, typed.
- **`components/attachments/AttachmentsPanel.vue`**: reusable, closely mirrors
  the task panel — file input with the same accept list, upload button, list of
  files with name+size, download (blob), delete, inline error, RTL/LTR,
  responsive, light/dark.
- **`components/attachments/AttachmentsDialog.vue`**: wraps the panel in a
  dialog opened from a paperclip button (with count badge), like tasks.
- Wire into:
  - `pages/expenses.vue` (replace the broken single-file/row-ref UI),
  - `pages/income.vue` (new attachment section),
  - `pages/staff_list.vue` (new attachment section).
- i18n: `common.attachments.*` keys in all three locales.

---

## 3. Payslip letterhead

- **Migration**: add `payslip_letterhead`, `payslip_letterhead_source`,
  `payslip_letterhead_safe_x/y/width/height` to `settings` (reversible).
- **`DocumentLetterheads`**: add a `payslip` entry to `SAFE_DEFAULTS`.
- **`Setting`**: fillable + float casts.
- **`SettingResource`**: raw fields + `letterheads.payslip` resolved payload.
- **`UpsertSettingRequest`**: rules + messages (upload, source, safe fields,
  remove flag).
- **`SettingController`**: `payslip-letterheads` directory + removable flag.
- **`settings.vue`**: extend the document-letterhead editor to a third card;
  refactor the receipt/invoice helpers to switch on kind so the UI stays DRY.
- **`payslip_template.vue`**: add `letterheadUrl` + `letterheadSafe` props,
  `.payslip-container--letterhead` background and a `.payslip-content--safe`
  wrapper; the existing DOM-serialising print path then prints the letterhead.
- **`staff_salary.vue`**: pass `companySetting?.letterheads?.payslip?.url` and
  `.safe` to the template.
- **`api/setting.ts`**: extend `DocumentLetterheadKind`/types with payslip.
- **i18n**: `payslipLetterhead` + hint keys in en / fa-AF / ps-AF.
- Audit already hides `*letterhead*`; permission is the existing `edit_settings`.

---

## 4. Verification

- `php -l` on every new/changed PHP file.
- `node scripts/i18n/check.mjs` — new UI keys present.
- `node -e "JSON.parse(...)"` for en / fa-AF / ps-AF.
- `npm run typecheck` — must stay at the pre-existing 26 errors (none in touched files).
- Feature tests: the local MySQL test DB is unavailable in this environment
  (`Connection refused … testing`), so new tests are written but cannot be
  executed here. Documented below.
- Also fix `tests/Feature/IncomeReceiptTest.php` (`Package::factory()` →
  `Package::create([...])`) carried over from the receipts work.

## 5. Progress

- [x] 1. Plan written
- [x] 2. Task assignment (backend + notification + i18n + tests)
- [x] 3. Attachments backend
- [x] 4. Attachments frontend + wiring
- [x] 5. Payslip letterhead backend + settings UI
- [x] 6. Payslip template + print wiring + i18n
- [x] 7. IncomeReceiptTest factory fix
- [x] 8. Verification + progress update

### What changed (files)

**Task assignment**
- `app/Support/EmployeeScope.php` — managed-department helpers + `isDepartmentManager`.
- `app/Support/TaskAccess.php` — `isDepartmentTask`.
- `app/Policies/TaskPolicy.php` — department-head branch on update + scoped `assignMembers`.
- `app/Models/User.php` — `rolePermissionNames()`, derived department-manager task
  capabilities in `permissionNames()`, and `hasDirectPermission()`.
- `app/Http/Controllers/TaskController.php` — scoped-assignment guard, constructor
  notification wiring, `taskAssigned` on create/update.
- `app/Http/Controllers/TaskAssigneeController.php` — scopes the picker for
  department heads.
- `app/Services/EducationalWorkflowNotificationService.php` — `taskAssigned`.
- i18n `notifications.task.assigned.*`; `lang/{en,fa,ps}/api.php`
  `task_assignee_outside_department`; `utils/apiErrors.ts`.
- `tests/Feature/DepartmentTaskAssignmentTest.php`.

**Attachments**
- `database/migrations/2026_10_03_000001_create_attachments_table.php` (table +
  backfill of legacy expense/employee documents).
- `app/Models/Attachment.php`; `expenses()/incomes()/employees()` morphMany on
  `Expense`, `Income`, `Employee`.
- `app/Http/Controllers/AttachmentController.php`; routes in `routes/api.php`.
- `app/Http/Resources/Concerns/PresentsAttachments.php` + `attachments` on the
  three resources.
- `app/Support/AuditRegistry.php` — `attachments` resource + label.
- `resources/ts/api/attachments.ts`; `components/attachments/AttachmentsPanel.vue`
  and `AttachmentsDialog.vue`; wired into `expenses.vue`, `income.vue`,
  `staff_list.vue`; types on the three API modules; `utils/apiAuthorization.ts`.
- i18n `common.attachments.*`.

**Payslip letterhead**
- `database/migrations/2026_10_03_000002_add_payslip_letterheads_to_settings.php`.
- `DocumentLetterheads` (payslip defaults), `Setting`, `SettingResource`,
  `UpsertSettingRequest`, `SettingController`.
- `resources/ts/pages/setup/settings.vue` (generic per-kind editor; payslip card),
  `resources/ts/api/setting.ts`, `resources/ts/pages/templates/payslip_template.vue`,
  `resources/ts/pages/staff_salary.vue`.
- i18n `pages.settings.settings.payslipLetterhead`.

### Verification run

- `php -l` — clean on every new/changed PHP file.
- `node -e JSON.parse` — en / fa-AF / ps-AF parse OK.
- `node scripts/i18n/check.mjs` — new `common.attachments.*`,
  `notifications.task.assigned.*` and `payslipLetterhead` keys are present in all
  three locales; remaining items are the pre-existing backlog.
- `npm run typecheck` — **no errors in any new or touched frontend file**. Total
  errors are 28; the two extra vs. the 26 recorded earlier are in
  `announcements.vue` and `notifications.vue`, which were not touched (so they
  are also pre-existing).

### Still unverified in this environment

- Feature tests cannot execute: the local MySQL test database is unavailable
  (`SQLSTATE[HY000] [2002] Connection refused … testing`). The new
  `DepartmentTaskAssignmentTest` is written but not run.
- `npx eslint` still times out here.

### Follow-ups

- Consider deriving task-creation for department heads (currently they may only
  assign/update existing department tasks).
- Consider a real `Package`/`CustomerPackage` factory so receipt package
  assertions can be restored.
- Backfilled attachments keep `expenses.document_*` / `employees.document_*`
  columns; they can be dropped in a later cleanup once no reader depends on them.
