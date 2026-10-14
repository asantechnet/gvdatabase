# Agency overview redesign

The `/agency-overview` page is a daily management overview for an education and visa firm. Detailed accounting, personnel administration and asset management remain in their dedicated pages.

## Project review and management questions

| Question | Authoritative records | Dashboard presentation |
| --- | --- | --- |
| How much case work is live, and who still needs a counselor? | `Applicant.status`, `responsible_employee_id` | Current active total, unassigned alert and filtered applications links |
| Which prospective students can we still convert? | Unconverted `Visitor` records, counselor ownership | Permission-aware prospects link with a current count; omitted for destination slices because visitors have no destination fields |
| Where are cases getting held up? | `ApplicantStage`, applicant stages and stage history | Case-stage bars, 14-day idle alerts, oldest records first |
| How are contracted services progressing? | `ApplicantContract.current_stage_id` | Collapsed service-stage summary; separate from overall case stage |
| What work needs action? | Incomplete case tasks and their due dates, case/task attachments, contracts, pending transactions | Full alert counts with bounded case previews, approval link and unassigned-case link |
| Which agreements need review? | Draft/terminated contracts and active agreements whose `end_date` is within 30 days or already past | Contract attention queue; cancelled agreements excluded |
| How much remains collectible? | Persisted service-line fee, discount, paid amount, currency and parent contract state | Balances grouped by currency; cancelled/terminated agreements excluded; filtered debtor list |
| How are admissions going? | `ApplicantUniversity.status` | Submitted, admitted, rejected, withdrawn and pending counts; offers and awaiting-decision links |
| What money has actually been recorded? | `Income`, `Expense`, `Salary` | Posted amounts by currency, separate from agreed service fees and receivable balances |
| What did we sell? | Service lines and their parent agreements | Four service counts linked to their own contract pages; cancelled/terminated agreements excluded |
| Who has the heaviest caseload? | Active employees and responsible applicants; task assignees/case ownership | Top six counselors by active cases, filtered application links, open/overdue/due-today task counts |
| Which destinations and universities dominate? | Admission details and university applications | Top destinations and university outcomes with explicit active/total and admitted/application denominators |

Attendance, leave, salaries, advances, assets, recurring events, support tickets, notifications and other general business models were considered as supporting administration. Putting their individual counters on this page would dilute the daily case-management picture. Payroll remains represented in posted finance. Notification delivery and printing are unaffected.

The schema does not provide a complete required-document checklist, visa appointment/deadline schedule or standalone visa-decision register. The dashboard therefore reports **cases awaiting attachments**, recorded university outcomes and actual configured workflow stages. It does not invent document completeness, visa approval rates or deadline metrics. CRM follow-up timestamps have no explicit completion state, so an authoritative overdue follow-up queue would require an agreed completion convention.

## Counting and navigation rules

- Live priorities, balances, admissions, unassigned cases and prospects ignore the reporting date range. Employee/destination filters still apply where the source supports them.
- Case-stage bars, counselor cases and destination case counts use applicant creation dates. Posted finance uses posting dates; service sales use agreement start dates; university rankings use application creation dates. Captions identify these populations.
- An admission count is a university application, not a unique applicant or visa approval. An applicant may have several offers.
- Action items are category totals, not distinct people; a case may contribute to several categories. Counts are computed before preview limits.
- A newly created case is not marked idle simply because it lacks a stage-history row.
- A scoped account without a linked employee receives empty scope, not agency-wide metrics.
- Links retain the filters used by the last successful response. Application drill-down filters are visible and removable; static routes use current names.
- Money stays separated by currency and preserves the payment service's persisted amount overrides. Receipt numbers, agreement snapshots and schema are unchanged.

## Validation

- Backend regression suite: **21 tests passed, 188 assertions** covering dashboard, applicant pipeline/workflow, university outcomes, built-in reporting and hierarchy authorization.
- Vue script/template compilation against the installed compiler package files, translation-key parity, icon availability and scoped diff whitespace checks.
- Standard `npm run typecheck`, `npm run build` and `npm run i18n:check` could not resolve existing dependency links. Offline pnpm repair lacked cached tarballs; an escalated repair also failed to start. Full type checking, production build and rendered-browser validation remain unverified in this environment.
