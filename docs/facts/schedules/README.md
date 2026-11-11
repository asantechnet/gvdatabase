# Schedules and cronjobs

Current behavior reviewed 2026-11-11. The executable source is [`routes/console.php`](../../../routes/console.php), the command classes in `app/Console/Commands`, and the services those commands call.

## Timezone and deployment

- All scheduled times use the company IANA timezone from `settings.timezone` through `CompanyProfile::timezone()`. Invalid or missing values fall back to `config('app.timezone')`.
- Attendance dates and workday boundaries are evaluated in that company timezone. Raw punch timestamps stay UTC in the database.
- Run one Laravel scheduler process per deployment:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

- Every task is registered with `withoutOverlapping()` and `onOneServer()`. `onOneServer` requires a shared cache store when the application runs on multiple nodes. Do not add one OS cron entry per command.
- There are no job classes under `app/Jobs`. The scheduled aggregation/reminder commands execute synchronously. A queue worker is still needed if mail or notifications are configured to queue:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Restart workers after deployment with `php artisan queue:restart`.

## Scheduled tasks

| Command | Cadence (company time) | Window and purpose |
| --- | --- | --- |
| `attendance:finalize --from={yesterday} --to={yesterday}` | Daily at 00:20 | Materialize missing attendance for the previous day, recalculate changed sessions, rebuild monthly summaries, refresh employee worked-hour totals. |
| `attendance:calculate-overtime --from={yesterday} --to={yesterday}` | Weekly on Monday at 00:40 | Reconcile changed sessions and overtime for the previous day without creating missing rows. Daily finalization already calculates overtime. |
| `analytics:sync` | Daily at 01:10 | Process the previous day and upsert daily/monthly dashboard aggregates. |
| `bonuses:calculate` | Monthly on day 1 at 02:00 | Score the previous company month and create/update review-only bonus nominations. |
| `recurring-events:process` | Hourly | Create notifications for due recurring-event occurrences and advance their schedules. |
| `applicants:send-reminders` | Daily at 08:00 | Notify case owners about cases stalled at the 30/21/14-day thresholds. |
| `contracts:send-expiry-alerts` | Daily at 08:10 | Notify contract owners when an end date is inside the default 30-day window. |
| `sanctum:prune-expired --hours=24` | Daily at 02:30 | Remove expired Sanctum tokens. |

## Attendance finalization and overtime

`attendance:finalize` calls `MonthlyAttendanceProcessor::process()`.

- **Missing rows:** It materializes an attendance row for every elapsed non-Friday workday that has no row. Friday is the weekly day off and is never materialized. It never marks today or a future day by policy.
- **Precedence:** An approved company holiday becomes `off_day` with `off_day_id` and `source=holiday`. An approved leave request owns the day as `off_day` or `absent` according to its allowance-covered fraction and sets `source=leave`. Neither is overwritten by the configured missing policy. A recorded work row is also left in place.
- **Policy:** The no-record fallback is `settings.missing_attendance_status` (`present`, `absent`, or `off_day`) and sets `source=missing_policy`.
- **Recalculation:** It recalculates changed sessions using the attendance input hash. `--force` recalculates unchanged sessions too.
- **Summaries:** It writes complete-calendar-month `monthly_attendance_summaries` rows for each touched month. Only elapsed days count toward `total_days`; today is not treated as an absence. Present days are `present` rows less their `leave_days`; off days are the sum of `off_day_days`; absent days are `total − present − off`.
- **Employee totals:** It refreshes `employees.total_worked_hours` from summary net worked minutes.

Manual forms:

```bash
php artisan attendance:finalize
php artisan attendance:finalize --from=2026-07-01 --to=2026-07-31
php artisan attendance:finalize --from=2026-07-01 --to=2026-07-31 --force
php artisan attendance:finalize --from=2026-07-01 --to=2026-07-31 --no-missing
```

With no range, `attendance:finalize` processes the previous calendar month.

`attendance:calculate-overtime` uses the same processor with `createMissing=false` and `alwaysSummarize=false`. It defaults from the start of the current month through yesterday. It does not create rows and skips summary/employee-total writes when no session or missing row changed.

## Analytics sync

`analytics:sync` defaults to yesterday and accepts `--from`, `--to`, and `--force`.

- For each day it upserts `daily_analytics`: attendance present/absent/off-day/incomplete counts, worked/overtime minutes, income and expense totals, tasks created/completed, contracts created/cancelled, employees onboarded, bonus nominations/grants and bonus base total.
- It then upserts `monthly_analytics` by summing the daily rows for each touched month.
- Dashboard and report code only uses aggregate rows when the requested range has complete coverage; otherwise it falls back to source tables.
- The archived `income_total` and `expense_total` fields currently sum `incomes` and `expenses` by date without a status filter. The live ledger treats only approved rows as money. Treat archived totals as a known consistency gap until the sync is aligned with the approved-only ledger rule; see [Finance](../finance/README.md).

## Task and case bonus calculation

`bonuses:calculate` defaults to the previous company-timezone month and accepts `--month=YYYY-MM` and `--force`.

- It uses the active `BonusRuleSet` and scores completed tasks in the month. Each assignee's contribution is weighted by the configured quality factor (when enabled) and reduced by rejected revisions.
- Unique case wins in the month add the configured case-win reward.
- The employee must be selectable, not excluded, and meet both the minimum task credit and minimum case-win thresholds.
- The raw award is capped by `maximum_monthly_bonus`. The result is stored as an automatic `worthy_employees` nomination with `is_granted=false`; it never pays money or automatically enters payroll.
- Nominations that no longer meet the rules are zeroed if they were not granted and are not attached to a payslip. Settled/granted nominations are preserved.
- A notification is delivered to holders of `grant_bonuses`; review and granting stay manual.

## Recurring events

`recurring-events:process` processes active events whose `next_occurrence_date` is due, in company timezone.

- Up to 366 missed occurrences per event are caught up in one run.
- Each occurrence is keyed by event and date. The first pass creates one notification for the event owner and marks the occurrence `notified`; the schedule advances to the next date.
- Occurrence execution is explicit and permission-checked. It creates an income, expense, or task from the stored payload, using a fresh currency snapshot and the occurrence date. Already completed occurrences are idempotent; skipped occurrences cannot be executed.
- A schedule becomes `completed` when its next date passes `end_date`; the final generated occurrence remains executable.
- `execute_recurring_events` plus the target record's create permission (`create_incomes`, `create_expenses`, or `create_tasks`) is required.

## Applicant reminders

`applicants:send-reminders` selects active applicants with stage history and measures days since the latest stage entry.

- It uses the highest reached threshold: 30, 21, or 14 days.
- Recipients are the responsible employee and their department managers holding `view_subordinate_records`; if none exist, holders of `view_applicants` are used.
- The notification key includes applicant, current stage, entry date, and threshold, so a daily run does not repeat the same warning. A later threshold or stage entry creates a new notice.

## Contract expiry alerts

`contracts:send-expiry-alerts` accepts `--days` (default 30).

- It selects non-cancelled contracts with `status` `active` or `completed` and an end date from today through the window.
- Recipients are the contract employee, seller, and the applicant's responsible employee. An ownerless contract falls back to active users holding `view_contracts`.
- The notification key includes the contract and end date. Renewing the contract and moving the end date allows a fresh warning; rerunning on the same end date does not duplicate it.

## Idempotency, locking, and retries

- `AutomationRunService` is used by `analytics:sync` and `bonuses:calculate`. It takes a two-hour cache lock per `job + period`, writes an `automation_runs` row with `running`, `completed`, or `failed`, and records attempt count, parameters, result, error message, and a bounded trace.
- A completed run is skipped unless `--force` is supplied. A failed run can be retried; the same history row is updated.
- Attendance finalization is idempotent through `AttendanceCalculationService` input hashes, `createOrFirst` for missing rows (including soft-deleted rows), and the unique employee/year/month summary key.
- Recurring occurrences, reminders, expiry alerts, and bonus review notifications use unique keys and `firstOrCreate` so retries do not spam recipients.
- `onOneServer` and `withoutOverlapping` protect the scheduler from duplicate execution across nodes. Both depend on a shared cache/lock backend in production.

## Monitoring and backfills

```bash
php artisan schedule:list
php artisan analytics:sync --from=2026-07-01 --to=2026-07-31
php artisan analytics:sync --from=2026-07-01 --to=2026-07-31 --force
php artisan bonuses:calculate --month=2026-07
php artisan bonuses:calculate --month=2026-07 --force
php artisan recurring-events:process
php artisan applicants:send-reminders
php artisan contracts:send-expiry-alerts --days=30
```

Monitor the Laravel log, scheduler process, queue failures, `automation_runs` through the Automation Runs UI/API, and `calculated_at` on aggregate rows. A failed command returns a non-zero exit code. Correct the source data, then rerun the exact range/month; use `--force` when a run completed before the source changed.

## Source map

- `routes/console.php`
- `app/Console/Commands/FinalizeAttendance.php`
- `app/Console/Commands/CalculateOvertime.php`
- `app/Console/Commands/SyncAnalytics.php`
- `app/Console/Commands/CalculateTaskBonuses.php`
- `app/Console/Commands/ProcessRecurringEvents.php`
- `app/Console/Commands/SendApplicantReminders.php`
- `app/Console/Commands/SendContractExpiryAlerts.php`
- `app/Services/MonthlyAttendanceProcessor.php`
- `app/Services/AutomationRunService.php`
- `app/Services/TaskBonusCalculationService.php`
- `app/Services/RecurringEvents/RecurringEventService.php`
- `app/Services/EducationalWorkflowNotificationService.php`
- `app/Services/ContractExpiryNotificationService.php`
- `app/Support/CompanyProfile.php`
