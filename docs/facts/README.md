# Verified system behavior

This directory is the maintained reference for implemented behavior. The source code is executable evidence; these documents are the human-readable behavior contract, not a substitute for testing. Read the relevant facts document before changing a feature, then inspect its linked source files.

## Area references

| Area | Reference | Coverage |
| --- | --- | --- |
| Schedules and cronjobs | [schedules/README.md](schedules/README.md) | Company timezone, scheduler deployment, every scheduled command, backfills, automation runs, idempotency, retries and monitoring. |
| HR, attendance, leave and payroll | [hr/README.md](hr/README.md) | Employee fields, edit windows, three-status attendance, missing-day policy, company holidays, leave allowance, salary calculations, advances and payslip printing. |
| Finance and commissions | [finance/README.md](finance/README.md) | Draft/approved ledger, receipts, service-line payments, currency snapshots, expenses, commission accruals/settlements, reports and the analytics status gap. |
| Academy | [academy/README.md](academy/README.md) | Feature gate, case-free contracts, classes, enrollments, monthly attendance, certificates, slips, student records and teacher commission. |
| Contracts | [contracts/README.md](contracts/README.md) | Four built-in services, primary/extension lifecycle, applicants and service details, templates/versions, snapshots, printing, cancellation and expiry alerts. |
| Applicant cases | [cases/README.md](cases/README.md) | Per-line cases, stage context and transitions, terminal notes, case wins, commission trigger, tasks, reminders, CRM and portal. |

## Cross-cutting references

| Feature | Reference | Coverage |
| --- | --- | --- |
| Testing workflow and performance | [testing.md](testing.md) | Test database configuration, bulk authorization seeding, measured setup cost, commands, parallel/isolated runs, guardrails and troubleshooting. |
| PWA and release updates | [pwa.md](pwa.md) | Installation, branding, release identity, update checks, consent, caches, offline behavior, manual deployment, troubleshooting and regression checks. |
| Background polling and error feedback | [background-polling.md](background-polling.md) | Active-tab scheduling, quiet API reads, auth retries, deferred heartbeat notices and regression procedure. |
| RTL direction and the always-LTR header | [rtl-direction.md](rtl-direction.md) | Locale-driven document direction, header direction override, scoped-style `:global()` mirroring pitfall, browser diagnosis and prevention checks. |
| Slip printing | [printing.md](printing.md) | Shared slip preview dialog, card-sheet grouping, fixed A5/A4 page settings, namespaced print CSS and browser print-control limits. |

## Maintenance rules

1. Read [project instructions](../../AGENTS.md), this index and the relevant facts document before implementation.
2. Trace the implementation and update the facts document together with the code, migrations, resources and regression tests. Historical plan/progress folders are intentionally not part of this reference.
3. Record validation limitations explicitly. Do not present source behavior as a verified live deployment when the environment prevented a build, migration or browser check.
4. Keep deployment instructions consistent with the actual manual deployment process. Never hand-edit generated build, service-worker, version or icon output.
5. If code and facts disagree, identify the mismatch explicitly and reconcile it. Do not silently restore behavior from an old plan.
6. Keep links relative and current. When a feature is removed, delete or replace its facts entry rather than leaving a dead reference.

The facts set is intentionally organized by business area plus cross-cutting platform behavior. If a change touches more than one area, update every affected reference and the index.
