# Educational Agency Portal — Super-Admin Review

**Environment:** http://127.0.0.1:8000 (Laravel 12 + Vue 3/Vuexy, local demo database)
**Reviewed as:** super administrator, normal UI only; database and source used read-only to verify numbers and rules
**Date:** 2026-10-29, approximately 10:45–12:10 Asia/Kabul
**Viewports:** 1280×720 desktop, 390×844 mobile; light, dark, LTR and Dari/RTL sampled
**Review type:** UI/UX, user-security and business-logic sanity review. Findings only; no application changes made.

---

## 1. Access note (important)

The supplied login `emailadmin@example.test` does not exist in this database and returned `401`. The seeded super administrator is:

- **admin@example.test / 1234** (DB user id 1, role `super_admin`, level 500)

I used that account. The seed file documents `1234` as a demo-only password, but it is also the live password in this environment, and it is below the system's own 8-character password rule.

---
small thing about staff leave request view details; currently its ui is messy and doesn't have titles for their desc, so fix this
e.g
Essential information
The details needed to identify and understand this record.

Date
Oct 29, 2026
2,026
No
No
pending
—
—
Notes & context
Supporting information kept in full, without truncation.

Reason
sick

—

one small thing about hrm employee contract, change contract number from CTR-2026-000001 to CTR-26-001 to make them shorter.

moving to /timesheet page for hrm;
remove two unnecessary kpis form there, and only keep 4 kpis, reduce their width and keep them in one row from mobile till laptop and use scrolling effect for them, instead of starting from second row.

for Attendance Roll Ledger table
it currently shrinks everything inside it so try to remove Date th and td, role th and td.
and give enough width for each th and td.
remove current loader and use reusable skeleton, responsive on mobile.
if data are large in table it should be able to expand width of table and scrolling it without scrollbar icon.

for Off Day Requests / /leave-requests page 
remove Scroll tables sideways on small screens desc from there.
for table 
remove current loader and use reusable skeleton, responsive on mobile.
when there is no data use current reusable component which is icon + desc.
same here for View Details popup fix its ui, titles aren't shown... for all tabs;
e.g
Essential information
The details needed to identify and understand this record.

Date
Oct 29, 2026
2,026
No
No
pending
—
—
—
Notes & context
Supporting information kept in full, without truncation.

Reason
sick

—
## 2. Verdict at a glance

**Overall: 2.9 / 5 — a broad, ambitious and visually coherent ERP shell that is not yet operationally trustworthy.**

| Category | Score | Reading |
|---|---|---|
| UI/UX | **2.7 / 5** | Attractive and mostly consistent design language, but repeated clipping, hidden actions, placeholder copy, inconsistent headers and a weak mobile list experience. |
| Security | **3.5 / 5** | Logout/session handling and payment-request self-approval are implemented correctly. 2FA setup, seeding, admin account visibility and audit detail are below the standard expected for a super-admin ERP. |
| Logic sanity | **2.4 / 5** | Several screens are technically impressive but disagree with each other and with their own database: two parallel stage models, unenforced document gates, unpaid salaries labelled "posted", payment status missing, broken follow-up filter, unlabelled asset currency. |

There is **no confirmed P0** (unauthenticated access or cross-tenant data leak). The highest-impact confirmed issues are **P1 business-integrity defects** in case staging and document gating.

### Score rubric

| Score | Meaning |
|---|---|
| 5 | Exceeds normal ERP practice; no material friction found |
| 4 | Solid; minor polish issues only |
| 3 | Usable, but with notable gaps a trained user must work around |
| 2 | Significant defects that cause wrong interpretation, extra work or lost context |
| 1 | Broken or unsafe for the stated task |

Security scores reflect page-level controls visible to a super admin **only**. They are not a penetration-test result and do not cover other roles (see §8).

---

## 3. Page scorecard

Every page below was opened and used through the UI.

| # | Page | Route | UI/UX | Security | Logic | Main reason for the score |
|---|---|---|---|---|---|---|
| 1 | Login | `/login` | 3 | 2 | 4 | Clean layout; duplicate accessible labels ("Email Email"), unnamed password-visibility button, generic 401, no visible lockout/2FA signal, 4-character seeded password. |
| 2 | Agency overview | `/agency-overview` | 3 | 4 | 2 | Useful dashboard, but card titles truncate, mobile KPI grid overflows, salaries are labelled "posted" while unpaid, and "cases by stage" actually shows service-line stages. |
| 3 | Applications list | `/applications` | 3 | 4 | 2 | 21 horizontally scrolling tabs; no create action; payment-status column always "—"; stage filter returns rows labelled with other stages. |
| 4 | Case detail | `/applicants/1` | 2 | 3 | 2 | Right column clipped; applicant data duplicated; blank Timeline; two attach UIs; stage advanced with no documents; case vs service-line stages diverge. |
| 5 | Applicant pipeline | `/applicant-pipeline` | 3 | 4 | 2 | Same case appears as two cards (primary + extension line); "Active: 2" beside three visible cards; columns clipped; "Stage" link jumps to configuration. |
| 6 | Visa reports | `/visa-reports` | 3 | 4 | 3 | Good filter/export shell; raw enum strings (`scholarship`, `test_offer`); no visible report period. |
| 7 | Contracts list | `/contracts/scholarship` | 2 | 4 | 2 | Status and all row actions off-screen at 1280 with no scroll hint; icon-only actions unnamed; primary and extension contracts look identical; Payment status not shown. |
| 8 | Contract detail drawer | contract row | 3 | 4 | 3 | "18 useful details" and "Activity" label are misleading; activity shows timestamps only, not changes/actor; content clipped behind footer. |
| 9 | Create contract dialog | contracts → Create | 2 | 4 | 3 | Footer covers form; unlabelled disabled "No matching version"; "Percentage" unexplained; required-field wall with no guidance. |
| 10 | Employee contracts | `/employee-contracts` | 3 | 4 | 2 | Dashboard flags CTR-26-015 for attention, but the list has no end-date/expiry column, so the warning cannot be understood or acted on. |
| 11 | CRM Visitors | `/visitor` | 2 | 3 | 2 | Actions and Consultant column clipped; every Email cell "—"; "Date" purpose unlabeled; service taxonomy not enforced on create. |
| 12 | Add Visitor dialog | visitors → Add | 3 | 4 | 2 | Service is free text while the rest of the system uses four fixed services; Percentage has no definition; notes hidden under footer. |
| 13 | Follow-up list | `/visitor?list=follow-up` | 2 | 3 | 1 | Identical to Visitors: all five visitors shown although **none** is flagged for follow-up (DB: 0 of 6); no visible active filter. |
| 14 | Income ledger | `/income` | 3 | 4 | 3 | Promises "currency totals" but shows none; "0 Records Currently Show"; zero-state pagination; inconsistent header. |
| 15 | Record income dialog | income → Record | 3 | 3 | 3 | Two competing modes; no payment method/reference; no required markers; computed total works correctly (tested 100 × 2 = 200). |
| 16 | Expense ledger | `/expenses` | 3 | 4 | 3 | Title-Case placeholder-like subtitle; no totals or date context; duplicate "Outgoing Stream / Spend Ledger" labelling. |
| 17 | Payment requests | `/payment-requests` | 2 | 3 | 3 | Two independent paginations with different page sizes (10 and 25); clipped subtitle; no search/filter. Self-approval is blocked server-side. |
| 18 | New payment request | requests → New | 3 | 3 | 2 | No payee/beneficiary, expense category, payment method, reference or attachment — insufficient for a real payment voucher. |
| 19 | Commissions | `/commissions` | 3 | 4 | 3 | Clear settlement concept and columns, but empty demo data and no explanation of rate/eligibility rules. |
| 20 | Asset inventory | `/asset` | 3 | 4 | 2 | Price 550 / 1200 with **no currency**; no acquisition date, total value or depreciation; Office/Allocated-to hidden. |
| 21 | Reports | `/reports` | 3 | 4 | 2 | Report period is invisible; "Headcount 6" excludes the seventh active employee; KPI subtitles clipped; payroll 0 while 580,000 AFN sits unpaid. |
| 22 | Task board | `/tasks` | 2 | 4 | 2 | Called Kanban but columns are services, not statuses; no page header; fifth column clipped; cards omit due date and assignee names. |
| 23 | Task edit dialog | task card | 3 | 4 | 2 | Only title/date/label/assignee; description, priority, progress, results, comments and attachments that exist in DB are not editable. |
| 24 | Todo matrix | `/tasks` → Todo Matrix | 3 | 4 | 2 | "Mega Tasks" jargon; progress 25% computed while stored progress is 60%; no selected-state, due date or owner in the list. |
| 25 | Staff directory | `/staff-list` | 2 | 3 | 2 | Salaries shown as **$45,000** while all payroll uses AFN; seven actions per row, five unnamed; Status/Actions off-screen. |
| 26 | Payrolls | `/payrolls` | 3 | 3 | 3 | Placeholder subtitle "Description"; currency symbol as suffix ("0.00؋"); hidden Actions; month/generated counts are otherwise coherent. |
| 27 | Timesheet | `/timesheet` | 2 | 3 | 2 | No page title; "Total present in range" with no range control; date range only under "Print range filter"; 6 unmarked of 7 staff. |
| 28 | Off Day Requests | `/leave-requests` | 3 | 4 | 3 | Clear explanation of allowance vs absence, sensible empty state; empty data prevents deeper checks. |
| 29 | Bonus list | `/bonuslist` | 2 | 3 | 2 | "1 candidates"; "2 granted bonuses" with 0.00 payout; currency shown as "؋0.00 AFN"; H2 as page title; no breadcrumb. |
| 30 | Users | `/setup/users` | 2 | 3 | 2 | Super-admin account is missing (1–7 of 7; DB has 8 users); "Email" column actually shows "Verified"; no last login. |
| 31 | Roles | `/setup/roles` | 3 | 3 | 3 | Good overview, but "Total 1 users", no permission summary, in-use roles offer delete, no page heading; Super Admin correctly undeletable. |
| 32 | Settings — Company | `/setup/settings/company` | 3 | 4 | 3 | Sensible settings shell; no company email; native "Choose File" nested inside upload button; status of unsaved changes clear. |
| 33 | Settings — Appearance | `/setup/settings/appearance` | 3 | 4 | 3 | Color controls work and preview live; no explicit light/dark toggle; page follows system scheme. |
| 34 | My Account | `/account-settings/account` | 3 | 3 | 2 | Avatar says "US" while the name is "Demo Admin"; side tabs clipped ("My Work Hi…"); role/permissions not shown. |
| 35 | Security settings | `/account-settings/security` | 3 | 2 | 3 | 2FA is a raw secret + otpauth link with issuer "Laravel", no QR, no recovery codes; no device/session list; password rules not stated. |
| 36 | Audits | `/audits` | 2 | 3 | 1 | 550 seeded "needs audit" rows; the one real change reads "Other information: 5 → 6"; no field names, old/new labels or actor detail; clipped table. |
| 37 | Notifications panel | bell icon | 3 | 4 | 2 | "Applicants Ahmad Wali was changed" — no stage, actor or action; missing Dari/Pashto strings observed; unnamed clear button. |
| 38 | Dashboard filters | Filters | 3 | 4 | 2 | Works, but uses `mm/dd/yyyy` while the app otherwise uses ISO or long dates; no applied-filter summary after Apply. |

**Category averages:** UI/UX **2.7**, Security **3.5**, Logic **2.4** (38 scored surfaces).

---

## 4. Cross-cutting findings (priority order)

### P1-1 — Two competing stage models; lists, filters and dashboards disagree with each other
**Pages:** Dashboard, Applications, Case detail, Pipeline, Tasks, Commissions
**Confidence:** High, reproduced

Each applicant has `applicants.current_stage_id`, and each service line has `applicant_contract.current_stage_id`. The UI edits and displays both, without naming which is authoritative:

- Case #1 was changed through the case header from **University Selection → University Application** (timeline recorded, task created).
- The same case's two service lines remained at **New**; the pipeline showed the case twice under New, while the Applications list showed **University Application**.
- Clicking the dashboard tile **"New Scholarship 2"** opens `/applications?stage=new&service=scholarship`, which returns **2 records whose visible stages are Consultation and University Application**. The "All applications" tab stays selected and only a small "1 Active Filters" hint appears.

Database evidence:

| Table | Case #1 value |
|---|---|
| `applicants.current_stage_id` | 6 = University Application |
| `applicant_contract.current_stage_id` (2 lines) | 1 = New (scholarship) |
| All 8 service lines across 4 applicants | each service's "New" stage |

**Why this matters:** stage filters, pipeline counts, dashboard tiles, stage-based task creation, commission eligibility ("when a case reaches completed") and notifications can all operate on different truths. A super admin cannot reconcile "5 active cases", "2 active / 3 cards" or "2 New Scholarship" from the screens.

**Standard practice:** one authoritative current stage per case/service line, consistently rolled up for the case list, board, filters and reports; the filter value is the same field the table displays.

**Evidence:** `applications.png`, `apps-new-filter.png`, `pipeline.png`, `case1.png`, `case1-after-stage.png`.

---

### P1-2 — Required documents and close reasons are not enforced
**Page:** Case detail (`/applicants/1`)
**Confidence:** High (document gate reproduced; close-note rule confirmed in source, not executed)

- The page states: *"Add a labeled document before progressing the case or creating a contract."*
- With **zero documents**, the case advanced from University Selection to University Application and the UI showed "Stage change recorded."
- `app/Services/ApplicantDocumentReadiness.php` exists but has **no call sites**; `ApplicantController::changeStage()` validates only `stage_id` and a nullable `note`.
- The field says "Stage note (required to close a case)", but the API validates `note` as `nullable`, and Cancelled/Withdrawn are offered with the note empty. I did not close the case because Cancelled is terminal in the UI.

**Why this matters:** this is the workflow control that prevents contracts being issued without verified documents. It currently depends on user discipline, not the system.

**Standard practice:** server-side prerequisite checks at the transition boundary, visible disabled state + explanation in the UI, and a mandatory reason for terminal transitions.

**Evidence:** `case1.png`, `case1-after-stage.png`; source pointer `app/Http/Controllers/ApplicantController.php:318-341`, `app/Services/ApplicantDocumentReadiness.php`.

---

### P2-1 — Financial labels do not match the ledger
**Pages:** Dashboard, Payrolls, Reports
**Confidence:** High

- Dashboard says **"Salaries posted 580,000 AFN"** and **"Posted expenses 0"**.
- Database: 7 salaries totalling **580,000**, all `payment_status = open`, paid total **0**. The dashboard sums `salaries.total_amount` by payment date with no payment-status filter (`EducationalDashboardController.php:262`).
- Reports "People & payroll" shows Payroll cost **AFN 0** for a period that is never displayed; the dashboard shows 580,000. Payroll is not part of "Posted expenses".
- "Still to collect 230,000 AFN" is mathematically correct, but **165,000 of it comes from future-dated extension contracts** (start 2026-11-28), not current receivables.

**Standard practice:** distinguish accrued vs paid vs posted; include payroll in expense reporting or label it separately; show the reporting period and currency on every financial summary; do not include not-yet-effective extensions in current receivables without a clear label.

**Evidence:** `dashboard-full.png`, `reports-payroll.png`; DB queries in §7.

---

### P2-2 — Payment status exists in the database but is hidden in the screens that need it
**Pages:** Applications, Contracts
**Confidence:** High

`applicant_contract` stores `payment_status` (paid / partially_paid / unpaid) and `amount_paid`. Yet:

- The Applications table's **Payment status** column is "—" for all five rows, although the data is paid/partially paid/unpaid.
- The Contracts list shows only "Active"; no paid/unpaid/outstanding column.

**Why this matters:** staff cannot see which contract is paid before delivering service; the only payment number is a dashboard total.

**Evidence:** `applications.png`, `contracts.png`; DB rows in §7.

---

### P2-3 — Follow-up list is not a follow-up list
**Page:** `/visitor?list=follow-up`
**Confidence:** High

- All six visitors are `follow_up = 0`.
- The Follow-up list shows the same five unconverted visitors as the Visitors list (Hamid Noorzai is excluded because converted — correctly).
- The page title stays "Visitors", the nav item has no active state, and no filter chip is shown. The API only filters when a `follow_up` parameter is present (`VisitorController.php:48`).

**Standard practice:** a follow-up queue filtered by owner/date/flag, with visible active-filter state and an empty state explaining there is nothing to follow up.

**Evidence:** `crm.png`, `followup.png`.

---

### P2-4 — Contract "attention" cannot be understood or acted on
**Pages:** Dashboard, Employee contracts
**Confidence:** High

- Dashboard: **Contract attention 1 — CTR-26-015**.
- `/employee-contracts` lists CTR-26-015 as "Active" with no end date, no "expiring" badge and no row highlight. DB: `end_date = 2026-11-18`, i.e. 20 days away.
- The dashboard also calls the link "Contract attention" while sending the user to a page that has no attention view.

**Standard practice:** show end date, days remaining and a renewal action in the contract register; the alert deep-links to the filtered/expiring set.

**Evidence:** `dashboard-full.png`, `employee-contracts.png`.

---

### P2-5 — Duplicate paginations and inconsistent currency/date formatting
**Pages:** Payment requests, Staff directory, Payrolls, Contracts, Reports, Dashboard filters, RTL dashboard
**Confidence:** High

- Payment requests renders **two paginators in one card**: "Items per page 10 / 0-0 of 0" and a second "Per page 25 / 0 records" with different prev/next labels.
- Currency formatting varies: `$45000.00` (staff), `50000.00 AFN` (contracts), `230,000 AFN` (dashboard), `60,000.00؋` (payroll), `؋0.00 AFN` (bonuses), `AFN 0` (reports).
- Dates vary: `2026-11-28`, `Nov 28, 2026`, `Oct 29, 2026, 10:54 AM`, `mm/dd/yyyy` (dashboard filter).
- Dari dashboard mixes Persian and Western digits on the same screen ("۲", "۷" vs "1 / 1", "AFN ۲۳۰,۰۰۰").

**Standard practice:** one locale-aware money and date formatter with a currency code, thousands separators and decimal precision; no raw enum values.

**Evidence:** `payment-requests.png`, `staff-list.png`, `payrolls.png`, `contracts.png`, `dashboard-dari.png`, `dashboard-filters.png`.

---

### P2-6 — Hidden columns/actions and inaccessible icon buttons
**Pages:** Contracts, Visitors, Staff, Payrolls, Applications, dialogs
**Confidence:** High

- At 1280×720, Contracts hides **Status** and **Actions**; Visitors hides Consultant and Actions; Staff hides Status/Actions; Payrolls hides Actions. Tables scroll horizontally only via trackpad/wheel; no scrollbar or affordance is visible. The hint "Scroll tables sideways on small screens" appears on desktop while columns are already hidden.
- Row actions are mostly icon-only and unnamed: Contracts has 3 unnamed of 4; Visitors 4 unnamed of 4; Staff 5 unnamed of 8. Login's password eye is "Appended action"; dialog close buttons are unnamed; the Income gear is unnamed.
- Tables are not sortable and column configuration is not exposed, unlike standard ERP lists.

**Standard practice:** sticky key columns and action column, a visible overflow affordance, single action menu, and every icon control with an accessible name/tooltip.

**Evidence:** `contracts.png`, `contracts-scrolled.png`, `crm.png`, `staff-list.png`, `payrolls.png`, snapshots in §7.

---

### P2-7 — Mobile and RTL/i18n quality is uneven
**Pages:** Dashboard, Applications, Visa reports, Dari dashboard, dark mode
**Confidence:** High

- 390px dashboard: the KPI grid overflows horizontally; the "Action items" card is cut and "University offers received" is unreachable.
- 390px Applications: each row becomes a card with **only the applicant name** and a 3-dot menu; stage, service, assignee, status and payment are gone.
- The three Applications sub-nav buttons overflow; "Visa reports" is clipped.
- Dark mode: the logo becomes unreadable (dark wordmark on dark background).
- Dari: layout mirrors correctly, but country names stay English, notification strings fall back to English (i18n console warnings), and number digits are inconsistent.

**Standard practice:** mobile rows expose the 3-5 decision fields; responsive grids do not overflow; translated notification keys; theme-aware logo variants.

**Evidence:** `dashboard-mobile.png`, `apps-mobile.png`, `dashboard-dari.png`, `dashboard-dark.png`.

---

### P2-8 — Admin and audit surfaces hide or misdescribe the truth
**Pages:** Users, Roles, Audits, Security settings, My Account
**Confidence:** High / one item needs verification

- **Users:** 7 rows "1–7 of 7" while the DB has 8 users — the only super admin (`admin@example.test`) is absent. The "Email" column actually contains "Verified" (email-verification state), duplicating the email already under the user name.
- **Roles:** "Total 1 users"; no permission counts or matrix; delete offered on roles that are assigned to users.
- **Audits:** the only real change I made reads **"Applicants · Ahmad Wali — Other information: 5 → 6"**; it does not say stage changed, from what, to what, or by whom in a readable way. 550 seeded "System" rows dilute the queue.
- **2FA:** setup shows a raw 32-character secret and an `otpauth://` link with issuer **"Laravel"**, no QR code, no recovery codes, no explanation of what happens if the device is lost. (I did not confirm enabling 2FA; the secret was generated before confirmation.)
- **My Account:** avatar initials "US" vs name "Demo Admin"; role/permissions not shown; side-tab labels clipped.

**Evidence:** `users.png`, `roles.png`, `audits.png`, `my-account.png`; DB users matrix in §7. 2FA screenshot withheld because it contains a live secret.

---

### P2-9 — Asset and CRM records lack essential commercial fields
**Pages:** Asset, Add Visitor
**Confidence:** High

- Assets store a price with **no currency column at all** (`assets` table), no acquisition date, no total value, no depreciation. "Quantity 5 × 90" cannot be totalled or compared.
- Visitor service is **free text** ("Enter service") while every list/report uses the fixed scholarship/private/test_offer/academy vocabulary. A typo permanently fragments reporting.
- "Percentage" (visitor grade) and "PhD 90.00%" are undefined in the UI.

**Evidence:** `asset.png`, `crm-scrolled.png`; `DESCRIBE assets` in §7.

---

### P3 observations (grouped, not separate issue files)
- **Placeholder/misleading copy:** Payrolls subtitle is literally "Description"; contract drawer headline "Contract · 18 useful details"; "0 Expense Records Shown"; "1 Active Filters"; "2 case(s)"; "Total 1 users"; "1 candidates"; Title-Case expense subtitle.
- **Inconsistent page headers:** Applications/Contracts have breadcrumb + H1; Dashboard, Tasks, Timesheet, Roles, Payment requests, Income, Expenses, Reports, Bonus list, Audits, Account settings do not. Cards often repeat the H1.
- **Truncated text without tooltips:** "Who has th…", "Where are applicants going? (selected case …)", "An admission officer or counselor raises … and …", "Deduct… Across salary p…".
- **Case detail duplication:** applicant/grade/passport details appear twice on the same screen; two "Attach document" paths; disabled Attach button styled as an enabled teal primary.
- **Undefined periods:** Reports and Timesheet state "in period"/"in range" with no visible period; dashboard says "all dates" but includes future extensions.
- **Print flows untested** (Print contract / Payslip / Timesheet report) because printing has external side effects and no print pipeline was verified.

---

## 5. What works well (do not regress)

- **Session handling:** logout returns to `/login`; browser Back stays on login; a deep link to `/setup/users` after logout redirects to `/login`. No stale page was shown.
- **Self-approval control:** `PendingTransactionController` rejects a requester approving their own payment request (`business.pending_transaction_self_approval`). I did not execute an approval, but the guard is present and explicit.
- **Sequential stage transitions:** only the next stage plus terminal outcomes are offered; jumping to arbitrary stages is blocked.
- **Computed receipt total:** Amount × Quantity × Exchange rate recalculated correctly (100 × 2 = 200); manual override reverted.
- **Unsaved-changes protection** in forms; required-field validation messages ("Select an income type.").
- **No horizontal page overflow on most desktop pages; the toolbar language switch, RTL mirroring and dark theme work at a basic level.**
- **Clear empty states** on Income/Expenses/Payment requests/Commissions/Visitors, though several zero-state controls remain (pagination at 0-0 of 0).

---

## 6. Comparison with standard ERP/CRM practice

| Area | Standard expectation | This system |
|---|---|---|
| Case workflow | One authoritative stage; document/approval gates enforced server-side; audit with actor, time, before/after | Two stage fields, UI shows both; document gate absent; audit not field-readable |
| Lists/tables | Sticky header + actions, sortable, filter chips, column config, export, totals | Hidden actions at 1280, no sort, no chips/clear filter, duplicate icons, no totals |
| Finance | Currency per transaction, accrued/paid/posted states, payroll included, visible period | Currency symbols inconsistent; unpaid salary called "posted"; hidden payment status; period invisible |
| Payroll/HR | Base/allowance/deduction/advance/outstanding, month and currency context | Good column concept, but placeholder copy, suffix currency, hidden actions, no advance detail |
| Contract register | Type/role, expiry, renewal, payment status, version | Role only inside drawer; expiry only in DB; payment status absent; primary/extension indistinguishable |
| CRM | Owner, lead source, next action, follow-up queue | Free-text service, no follow-up filter, "Date" unlabeled, no next action |
| Assets | Cost + currency, acquisition date, total value, depreciation, custodian | Price with no currency/date/total/depreciation |
| Admin | Visible accounts incl. self, role matrix, in-use protections, session list, 2FA QR + recovery | Admin missing from list; "Total 1 users"; 2FA secret + "Laravel" issuer; no sessions |
| Audit | Field-level old → new, actor, IP, timestamp, filters | "Other information: 5 → 6"; 550 seeded rows; no actor in case timeline |
| Localization | One number/date/currency formatter; complete translations | Mixed digits, symbols, date formats; missing notification translations |

---

## 7. Verification queries and exact values

Run against `educational_office_main` (read-only):

```sql
-- 8 users, admin present but not in /setup/users
SELECT u.id,u.name,u.email,GROUP_CONCAT(r.name) roles
FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id
LEFT JOIN roles r ON r.id=ur.role_id GROUP BY u.id;

-- salaries 580,000 AFN, all open, 0 paid
SELECT COUNT(*) cnt, SUM(total_amount) total,
       SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END) paid,
       GROUP_CONCAT(DISTINCT payment_status) statuses
FROM salaries;

-- case #1: applicant stage 6 vs both service lines stage 1 (New)
SELECT current_stage_id FROM applicants WHERE id=1;
SELECT ac.id, ac.service, ac.current_stage_id, s.name, ac.fee, ac.amount_paid, ac.payment_status
FROM applicant_contract ac LEFT JOIN applicant_stages s ON s.id=ac.current_stage_id;

-- outstanding 230,000 = 65,000 current + 165,000 future extensions
SELECT applicant_id, SUM(fee) fee, SUM(amount_paid) paid, SUM(fee-amount_paid) outstanding
FROM applicant_contract GROUP BY applicant_id;

-- follow-up queue: 0 flagged
SELECT COUNT(*) total, SUM(follow_up=1) follow FROM visitors;

-- assets have no currency column
DESCRIBE assets;

-- contracts: extension rows active with future starts
SELECT contract_number, applicant_id, contract_role, start_date, end_date, status
FROM contracts ORDER BY id;
```

Observed values: users 8 / list 7; salaries 7 × open = 580,000 paid 0; applicant stage 6, lines stage 1; outstanding 50,000 + 130,000 + 30,000 + 20,000 = **230,000** (of which 165,000 is extension fees); visitors 6, flagged 0.

---

## 8. Scope, method and limitations

- **UI-only interaction:** normal clicks, typing, filters, dialogs, language switch, viewport resize. No API probing, no DevTools, no injected scripts, no route guessing beyond routes already exposed in the UI.
- **Read-only data verification:** MySQL and targeted source reads were used after a UI symptom appeared. No code, test, config or database row was modified by me, with one exception:
  - **Case #1 was advanced from University Selection to University Application** through the normal UI while testing whether the document gate is enforced. This is a real state change in the demo database (timeline entry, new open task). No other business record was created or changed.
- **Not tested:** permission boundaries for counselor/manager/finance roles (only the super-admin persona was authorized); approval/rejection/posting of payment requests (financial side effects); payroll generation; bulk operations; email delivery; print/PDF output; file uploads; concurrent edits and duplicate-submit race conditions; session expiry; brute-force/rate-limit behavior; office/tenant isolation; full keyboard-only traversal and WCAG conformance scoring.
- **No fixes were made.** This document is findings and evidence only.

---

## 9. Recommended next steps (for the implementation team)

1. Fix the **single source of truth for case stage** (and whether extension lines independently progress) before anything else; make list filters, pipeline, dashboard, tasks and commissions read the same field.
2. Enforce the **document gate** and **terminal reason** at the service/controller boundary, then make the UI explain unavailable transitions.
3. Make **money truthful**: filter "posted" by status, include payroll in expense reporting or label it, show payment status on contracts/cases, and unify currency/date/percentage formatting.
4. Fix admin surfaces: show the super-admin account (with self-protection), correct the Users "Email" column, add permission summaries, make audit rows field-readable.
5. Harden 2FA (QR, issuer name, recovery codes), document password policy, and add a session/device list.
6. Make tables usable at 1280 and 390: pinned actions/key columns, visible scroll, named icon buttons, sort/filter chips, mobile rows that show stage/service/assignee.
7. Fix the follow-up filter, asset currency/valuation fields, employee-contract expiry view, and the duplicate pagination on Payment requests.

---

## 10. Evidence index

All files are in `review/evidence/`. Data is synthetic demo data. The 2FA secret screenshot was withheld.

| Finding | Evidence |
|---|---|
| Dashboard + finance labels | `dashboard-full.png`, `reports-payroll.png`, `dashboard-filters.png` |
| Stage divergence / filter | `applications.png`, `apps-new-filter.png`, `pipeline.png`, `case1.png`, `case1-after-stage.png` |
| Document gate | `case1.png`, `case1-after-stage.png` |
| Contracts | `contracts.png`, `contracts-scrolled.png`, `contract-detail.png`, `contract-create.png`, `contract-create-scrolled.png`, `employee-contracts.png` |
| CRM/follow-up | `crm.png`, `crm-scrolled.png`, `followup.png` |
| Finance | `income.png`, `expenses.png`, `payment-requests.png`, `commissions.png`, `asset.png` |
| HR | `staff-list.png`, `payrolls.png`, `timesheet.png`, `leave-requests.png`, `bonuslist.png` |
| Tasks | `tasks.png`, `task-detail.png` |
| Admin/security/audit | `users.png`, `roles.png`, `my-account.png`, `audits.png`, `notifications.png` |
| Responsive/i18n/theme | `dashboard-mobile.png`, `apps-mobile.png`, `dashboard-dari.png`, `dashboard-dark.png` |
| Settings | `settings.png` |
| Visa reports | `visa-reports.png` |

Supporting issue reports: `review/issues/`.

---

## 11. Post-review addendum — service contract creation is blocked (2026-10-31)

A later UI session found that **no service contract can be created** from Contracts → Create contract, for any role.

- The counselor completed the scholarship dialog with a compatible active version (`Scholarship — English · v1 · Current`); Save was enabled; pressing it returned **“Start Date: Required.”** and no contract was created.
- The dialog renders no start-date field (18 inputs, zero date inputs) and the payload never sends `start_date`.
- The client request validator requires `start_date` for `POST /contracts/{scholarship,test_offer,academy,private}` (`resources/ts/utils/apiValidation.ts:15-22`), while the server request class **prohibits** it (`app/Http/Requests/StoreServiceContractRequest.php:108`) and derives it (`app/Services/Contracts/ContractGenerationService.php:104`).
- The Axios interceptor rejects the request before it leaves the browser, so the server never sees it.

Full report: `issues/18-service-contract-start-date-blocker/issue.md`. This supersedes the scorecard note for the “Create contract dialog” row; earlier scores were not recalculated.
