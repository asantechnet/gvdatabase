# ISSUE-16 — Placeholder copy and inconsistent page headers across modules

- **Severity:** P3 (polish / trust)
- **Category:** UI/UX
- **Pages:** Payrolls, Contract detail, Reports, Income, Expenses, Payment requests, Tasks, Timesheet, Roles, Bonus list, Audits, Account settings, Settings
- **Persona:** Super administrator
- **Confidence:** High

## Expectation

Every page has a consistent header (breadcrumb + H1), real copy (no placeholders), non-truncated descriptions, and consistent action hierarchy.

## Observed

- **Placeholder copy:** Payrolls subtitle is literally **"Description"**; contract drawer headline says **"Contract · 18 useful details"**; "No matching version" in the create-contract header is unlabelled.
- **Headers:** Contracts/Applications use breadcrumb + H1. Dashboard, Tasks, Timesheet, Roles, Payment requests, Income, Expenses, Reports, Bonus list, Audits and Account settings do not. Cards duplicate the H1 (Payrolls → card title "Payrolls").
- **Truncation:** "Who has th…", "Where are applicants going? (selected case …)", "An admission officer or counselor raises … and …", "Deduct… Across salary p…", "Top 6 counsel…".
- **Action hierarchy:** Create-contract dialog footer covers the form at 720px; Submit/Save are often low-emphasis text while Cancel/outlined controls compete; Delete is a large pale-red button beside Update in the task dialog.
- **Card grammar:** "1 users", "1 candidates", "2 case(s)", "1 Active Filters".
- **Duplicate concepts:** Payment requests has two paginations; Income has "Service Payments" and "Record Income"; Expenses has "Outgoing Stream" and "Spend Ledger" on one card.

## Impact

The product reads as unfinished and forces users to relearn the header/action pattern per module; truncation hides the meaning of key cards.

## Evidence

- `review/evidence/payrolls.png`
- `review/evidence/contract-detail.png`
- `review/evidence/contract-create.png`
- `review/evidence/payment-requests.png`
- `review/evidence/income.png`
- `review/evidence/expenses.png`
- `review/evidence/tasks.png`
- `review/evidence/timesheet.png`
- `review/evidence/roles.png`
- `review/evidence/bonuslist.png`
- `review/evidence/audits.png`
- `review/evidence/settings.png`
