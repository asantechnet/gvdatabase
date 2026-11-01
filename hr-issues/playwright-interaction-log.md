# HR Module — Playwright CLI Interaction Log

Scope: every action taken through `playwright-cli` during the HR review session.
Everything below was performed as a **super-admin user against the running app at `http://127.0.0.1:8000`**.
No source file was consulted to produce this log.

Playwright element references (`e23`, `f1e184`, `f3e593`, …) are the snapshot refs returned by
`playwright-cli snapshot` at that moment. They are recorded so any step can be replayed.

---

## 1. Login — `/login`

| # | Action | Result |
|---|--------|--------|
| 1 | `open http://127.0.0.1:8000/login` | Login screen rendered |
| 2 | `snapshot` | Form fields and labels enumerated |
| 3 | `fill e23 "admin@example.test"` | Email field accepted input |
| 4 | `click e79` (submit) | Authenticated as super admin — the shell and HR navigation group became available |

**Verdict:** authentication through the UI works.

---

## 2. Departments — `/setup/departments`

Navigated: `goto http://127.0.0.1:8000/setup/departments` (opened twice in the session).

| # | Action | Result |
|---|--------|--------|
| 1 | `click f1e157` | "Add department" dialog opened |
| 2 | `find "Department name"` | Field located inside the dialog |
| 3 | `fill f1e184 "Counseling"` | Department name accepted |
| 4 | `click f1e223` | Create submitted |
| 5 | `click f1e192` | Second submit/save control in the same flow |
| 6 | `snapshot //div[@role='dialog']` | Dialog state inspected |
| 7 | `eval` over dialog text | **Two identical toast messages** rendered for one save |
| 8 | `click` named button via `getByRole` | Delete of a department that still has members |
| 9 | `eval` (`DUP_INLINE:`) | Confirmed the same message appears **twice**; no domain-specific reason shown |

**Verdict:** create works; **duplicate-toast defect reproduced** (HR-002, HR-003). Delete-blocked
reasoning surfaces only as a generic "Unable to save your changes." twice.

---

## 3. Staff list — `/staff-list`

Navigated: `goto http://127.0.0.1:8000/staff-list`.

| # | Action | Result |
|---|--------|--------|
| 1 | `click f2e175` | "Add staff" dialog opened |
| 2 | `click f2e753` (save, empty form) | Inline field errors rendered, no toast |
| 3 | `fill f2e561 "Test Counselor"` | Name entered |
| 4 | `snapshot //div[@role='dialog']` | Steps/fields inspected |
| 5 | `click f2e806` | Position/department selector opened |
| 6 | `snapshot //div[@role='dialog']` | Options inspected |
| 7 | `click f2e828` | Option selected |
| 8 | `click f2e753` (save again) | Save retried after correcting the field |

**Verdict:** dialog and selectors operate; **stale inline errors persist after correction** and the
salary value is silently rewritten from the position default (HR-008). No API-level save toast was reached.

---

## 4. Payrolls — `/payrolls`

Navigated: `goto http://127.0.0.1:8000/payrolls`.

| # | Action | Result |
|---|--------|--------|
| 1 | `click f3e165` | Generate-payslips dialog opened |
| 2 | `click f3e433` | Generation confirmed and executed |
| 3 | `eval` (`querySelectorAll` over toast + table text) | Toast read: ` payslip(s) created;  skipped.` — **counts blank** |
| 4 | `find "Zahra Aziz"` | Employee row located in the payroll table |
| 5 | `click f3e543` | Payment dialog opened for that employee |
| 6 | `fill f3e593 "999999"` | Deliberately oversized payment amount |
| 7 | `click f3e605` | Payment submitted |

**Verdict:** generation persists rows but the summary toast is broken (HR-005). Overpayment is
correctly rejected with a descriptive message (`Amount: The payment cannot exceed the outstanding
balance.`) — captured in `evidence/payroll-overpayment-error.png`.

---

## 5. Timesheet — `/timesheet`

Navigated: `goto http://127.0.0.1:8000/timesheet` (three times; once for a re-check).

| # | Action | Result |
|---|--------|--------|
| 1 | `goto` → page load | Grid, filters and log form rendered |
| 2 | `click f12e197` | Log/action control pressed |
| 3 | `goto` again and inspect | **No toast** produced for the action; feedback is inline text only |

**Verdict:** page renders and the control fires, but the action gives no toast confirmation (HR-007).

---

## 6. Pages opened for render only — no functional test

| Page | URL | What was done |
|------|-----|---------------|
| Leave requests | `/leave-requests` | `goto` only — page loaded, **no submission, approval or rejection performed** |
| Bonus list | `/bonuslist` | `goto` only — page loaded, **no nomination, grant, edit or delete performed** |

These were **navigation/rendering checks**, not CRUD tests.

---

## Not touched through Playwright at all

The following HR surfaces were **never opened in the browser** during this session:

- `/commissions` (commission lists and accruals)
- `/off-days` (company holidays / off-day requests)
- Salaries: individual payslip edit, manual deductions, advance creation and recovery
- Employment contracts and contract completion
- Positions, announcements, bonus rules dialog
- Task assignment as a department manager (no second user was ever logged in)

Any statement in the issue files about those areas is derived from reading source, and each such
report says so in its own "Evidence" section.

---

## Honest coverage summary

| Surface | Interaction level |
|---------|-------------------|
| Login | Functional (authenticated) |
| Departments | Partial CRUD — create + blocked delete + manager picker |
| Staff list | Partial — add dialog, validation, save retry |
| Payrolls | Partial — generate, overpayment rejection |
| Timesheet | Minimal — one action, no toast |
| Leave requests | Render only |
| Bonus list | Render only |
| Commissions / off-days / advances / contracts / positions / tasks | Not visited |

**Correction to earlier claims:** the first draft of the issue set implied broader end-to-end UI
coverage than actually occurred. The findings that rest on source inspection rather than a click are
HR-006 (commission trigger), HR-009 (leave/off-day validation gaps), HR-004 (manager eligibility
filter, partially observed in the UI) and the payroll-wiring parts of HR-001. Those remain valid as
code-reading findings, but they were **not** reproduced by driving the UI end to end.
