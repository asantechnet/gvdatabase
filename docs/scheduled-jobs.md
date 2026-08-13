# Scheduled jobs deployment

The application scheduler uses the company IANA timezone configured in Settings (for example, `Asia/Kabul`). Attendance dates and workday boundaries are evaluated in that timezone; raw punch timestamps remain UTC in the database.

## System cron

Run one Laravel scheduler process per deployment. Replace the example path and PHP binary as appropriate:

```cron
* * * * * cd /var/www/mini-management && php artisan schedule:run >> /dev/null 2>&1
```

Laravel's `onOneServer` locks require a shared cache driver when multiple application nodes are deployed. Do not add a separate OS cron entry for each application command.

## Scheduled work

- `attendance:finalize` runs daily and creates missing weekday attendance rows, calculates changed sessions, rebuilds monthly summaries, and refreshes each employee's `total_worked_hours`.
- `attendance:calculate-overtime` runs weekly as an incremental reconciliation (daily finalization already calculates overtime). It skips summary and employee-total writes when no punch or schedule input changed.
- `analytics:sync` processes the previous finalized day by default. It upserts daily and monthly aggregates for finance, attendance, worked time, tasks, contracts, onboarding, and approved bonuses. Dashboard code uses these rows only when the requested range has complete coverage; otherwise it safely falls back to source tables.
- `bonuses:calculate` runs monthly for the previous company-timezone month. It scores completed-task participation using the active versioned rule set, creates or updates one review-only nomination per employee/month, and preserves every scoring input. It never grants money or includes a nomination in payroll automatically.
- Both commands claim an application lock and a unique `automation_runs` period record. Completed periods skip by default, failures remain inspectable, and retries update the same history row.

## Manual execution and backfills

```bash
php artisan attendance:finalize
php artisan attendance:finalize --from=2026-07-01 --to=2026-07-31
php artisan attendance:finalize --from=2026-07-01 --to=2026-07-31 --force
php artisan attendance:calculate-overtime --from=2026-07-01 --to=2026-07-31
php artisan analytics:sync --from=2026-07-01 --to=2026-07-31
php artisan bonuses:calculate --month=2026-07
php artisan analytics:sync --from=2026-01-01 --to=2026-07-31 --force
php artisan bonuses:calculate --month=2026-07 --force
php artisan schedule:list
```

Commands are idempotent: attendance input hashes prevent unnecessary recalculation, monthly rows use a unique employee/year/month key, analytics use a unique date, and automatic bonus nominations use a unique employee/task key.

## Queue worker

These aggregation commands currently execute synchronously, so they do not require a queue worker. A production queue worker is still required for queued notifications and mail:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Use a process supervisor to restart failed workers and restart workers after each deployment with `php artisan queue:restart`.

## Monitoring and retries

Monitor the application log, scheduler process, queue failures, the Automation Runs API/UI consumer, and `calculated_at` on aggregate rows. The commands return a failure exit code and preserve the exception message and a bounded trace in `automation_runs`. Correct the underlying problem, then rerun the exact range/month; use `--force` when the prior run completed but source records changed. Uniqueness constraints, locks, and upserts make retries safe.

Before the first bonus run, an authorized reviewer should configure the task bonus rule page and managers should record quality/participation on completed tasks. Rule changes create a new version; existing nomination evidence retains the rule snapshot used for its calculation. Automatic nominations still require a user with `grant_bonuses` to approve them before salary selection.
