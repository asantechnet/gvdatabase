# ISSUE-03 — Dashboard calls unpaid salaries "posted" and excludes them from expenses

- **Severity:** P2 (financial reporting)
- **Category:** Logic sanity
- **Pages:** Agency overview, Payrolls, Reports
- **Persona:** Super administrator
- **Confidence:** High — compared UI to MySQL

## Expectation

"Posted" means an accounting entry exists. Accrued but unpaid salary must not be labelled posted, and payroll must either contribute to expense reporting or be explicitly excluded. Receivables should distinguish current from future-dated extension fees.

## Observed

- Dashboard: **"Salaries posted 580,000 AFN"**, **"Posted expenses 0"**.
- Database: 7 salaries in 2026-06 totalling **580,000**, all `payment_status = open`, paid total **0**. The dashboard sums `total_amount` by payment date without a status filter (`EducationalDashboardController.php:262`).
- Reports "People & payroll" shows **Payroll cost AFN 0** with no visible report period; the dashboard shows 580,000.
- "Still to collect 230,000 AFN" is arithmetically correct (50,000 + 130,000 + 30,000 + 20,000), but **165,000** of it comes from extension contracts that start **2026-11-28** (future), not current receivables.

## Impact

A super admin reading the dashboard believes payroll is posted and expenses are zero. Liquidity, accrual and period reporting are misleading.

## Evidence

- `review/evidence/dashboard-full.png`
- `review/evidence/reports-payroll.png`
- Query: `SELECT SUM(total_amount), SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END) FROM salaries;` → 580,000 / 0.

## Standard comparison

Standard ERP dashboards show accrued vs paid vs posted separately, include a period selector with a visible applied period, and label future-dated extension invoices distinctly from current receivables.

## Verification for implementers

Open the dashboard and Payrolls for 2026-06; compare "Salaries posted" with the open salary rows. Repeat for the current month and with a date filter applied.
