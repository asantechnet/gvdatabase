# Project Notes

## Architecture

- This is a Vuexy-based Laravel/Vue 3/TypeScript application.
- Frontend and backend are separate concerns connected through a REST API.
- Backend stack: PHP 8.2, Laravel 12, Laravel Sanctum.
- Frontend stack includes Vue 3.5, TypeScript, Vite, Vuetify, Pinia, Vue Router, Axios, and `vue-i18n`.
- The product is software-solutions-company management software. It includes account management, task management, contract management, and other general business features.

## Routing

- Automatic routing was removed because of bugs.
- The codebase may contain legacy auto-routing conventions alongside the current static routing implementation.
- The current static router is in `resources/ts/plugins/1.router`.
- When adding or changing routes, follow the static router unless there is a specific reason to preserve a legacy route.

## API Contracts

- Axios and Laravel API Resources create a double `data` wrapper. The actual payload is normally at `response.data.data`.
- Unwrap this response shape in API contract files under `resources/ts/api`, never in Vue pages or components.
- Use `resources/ts/api/incomes.ts` as the reference implementation for typed API methods and nested response handling.
- Keep API types, request methods, response normalization, and API errors in the API layer.

## UI Conventions

- Use `vue-toastification` for toast messages. Do not use the legacy toast library.
- All UI must support both RTL and LTR layouts because Arabic and Persian are supported.
- All UI must work correctly with both light and dark themes.
- Interfaces must be responsive across mobile, tablet, and desktop sizes.

## Icons

Two icon mechanisms coexist. Pick the right one and do not mix them up:

- **Class-based (bundled).** `VIcon :icon="'tabler-home'"`, `prepend-icon`, `icon: 'tabler-*'` in data, and navigation `icon: { icon: 'tabler-*' }`. Vuetify's `iconify` icon set renders `<i class="tabler-home">` and the glyph comes from a generated CSS rule. An icon with no matching rule **renders as nothing**.
- **Remote (runtime).** `import { Icon } from '@iconify/vue'`, used as `() => h(Icon, { icon: 'streamline-cyber:person' })`. Fetched from the Iconify API at runtime; not bundled. Use this for one-off icons, icons outside the bundled sets, or names that are only known at runtime.

Rules:

- `resources/ts/plugins/iconify/icons.css` is **generated**. Never hand-edit it. It is produced by `npm run build:icons`, which also runs on `postinstall`.
- The generator scans `.ts`, `.tsx`, `.js`, `.vue`, `.php`, `.blade.php`, `.json`, and `.scss` files under `resources/ts`, `resources/views`, `app`, `lang`, `routes`, `database`, and `config` (plus `themeConfig.ts`) for `tabler-` / `mdi-` / `fa-` / `ph-` / `lucide-` icon tokens, and only bundles the icons the repository actually references.
- **Adding a static class-based icon needs no extra step** — reference it with its full `prefix-name` and it is picked up on the next `build:icons` run.
- **Dynamic or externally-sourced class-based names cannot be scanned** (template literals, names from the API/database, computed values). Either add the exact name to `resources/ts/plugins/iconify/icon-allowlist.json`, or switch that usage to the remote `@iconify/vue` `<Icon>` component.
- Iconify aliases are resolved by the generator, so alias names like `tabler-discount-2` render correctly. Do not assume a name is invalid just because it is absent from an icon set's `icons` map.
- The 5 checkbox/radio names mapped in `plugins/vuetify/icons.ts` (`mdi-checkbox-blank-outline`, `mdi-checkbox-marked`, `mdi-minus-box`, `mdi-radiobox-blank`, `mdi-radiobox-marked`) render from imported SVGs rather than `icons.css`, so they are listed under `ignore` in the allowlist — excluded from the bundle with their warning suppressed.
- The generator warns about tokens that are used but unavailable in any configured set. Treat that warning as a real bug — it means the icon renders blank.

## Vuexy Quirks

- `AppDateTimePicker` (Flatpickr) inside a Vuetify dialog must stay in its normal body-level popup mode (`static: false`). Static mode can be clipped or hidden by the dialog structure.
- When using that picker in a dialog, apply a zero-offset transform after `onOpen`: `transform: translateY(0) scale(0.86)` with `transform-origin: top left`. The explicit zero-offset transform is required for the calendar to stay docked to its input; do not remove it merely because it has no visible vertical offset.
- Keep the opened Flatpickr calendar above dialog content with an appropriately high z-index.

## Documents and Printing

- Document rendering and printing are core requirements and must be treated as high-importance functionality.
- Printed documents must be accurate, clean, responsive, and suitable for standard paper sizes.
- Income receipts and payslips use A5 sizing.
- Contracts and similar larger documents use A4 sizing.
- Any document-related change should preserve print quality and validate the relevant paper layout.
- Treat print CSS in this SPA as global and persistent even when it originates in a route component. Never use broad print selectors such as `header`, `footer`, `.v-card`, or layout-shell selectors without a print-mode namespace; a previously visited route can otherwise alter later print jobs through CSS injection order.
- Use `resources/ts/utils/printDocument.ts` for printing. It applies a feature-specific body class, waits for Vue and browser layout, injects the current document's `@page` rule last, and cleans up temporary state after printing. Prefix every feature-specific global `@media print` selector with that body class.
- Never leave `@page` inside a route or template stylesheet. CSS cannot namespace `@page`, so lazy-loaded SPA styles for A4, A5, Letter, portrait, and landscape documents compete by source order. Pass the page size and margins to `printDocument` so the active rule is injected only for that print job.
- Keep printable DOM class names feature-specific, explicitly hide the print host on screen and show it only under its print-mode body class, and avoid depending on framework `d-print-*` utilities. Scope component styles where possible; namespace the application-shell overrides that must be global.
- Reset the real layout ancestors around a print host, including their padding, flex growth, and viewport minimum heights. A full-paper element with `break-inside: avoid` will be pushed to a blank first page if any ancestor leaves even a small amount of space before it.
- Apply forced page breaks only with `.print-page:not(:last-child)`. Do not set `page-break-after: always` on every page and rely on a last-child override, because print engines can still paginate a trailing blank sheet.

## Payments

- Invoices, counterparty companies, commission ledgers and outsourcing ledgers are removed. Do not restore their APIs, models, schema, permissions, UI, or compatibility aliases.
- The firm's own branding, settings, letterheads and document signatures remain.
- Built-in services are scholarship, test_offer and academy. Contracts assign them through applicant_contract, which owns fee, currency, rate, discount and payment state.
- An income can optionally target one applicant_contract line. One receipt per selected service; standalone incomes remain valid.
- Incomes and expenses carry `status` (`draft`, `approved`, `rejected`); only approved rows are ledger money. `create_draft_finance_records` writes drafts, `approve_draft_finance_records` approves/rejects them, and `view_own_finance_records` / `view_all_finance_records` scope the lists. A receipt number is issued at approval, never while the row is a draft.
- ApplicantContractPaymentService owns calculated balances and sticky payment overrides. CurrencyRateService persists supplied rates atomically.
- Preserve issued contract snapshots and receipt numbers. Receipt printing uses the namespaced A5 print workflow.

## HR, payroll and leave

- `employees.guarantor_name` is a first-class field alongside the guarantor ID/phone/address/licence; keep it in the model, validation, resource, factory and staff form.
- Attendance has exactly three statuses: `present`, `absent` and `off_day`. Friday is the weekly day off and is never materialized. `attendances.leave_days` is the approved absence fraction (0, 0.5 or 1) and `attendances.off_day_days` is the part of that absence covered by an allowance or company holiday; the uncovered remainder is an absence. `attendances.source` records `manual`, `self_service`, `holiday`, `leave` or `missing_policy`.
- Company off days (`/off-days`) and employee off-day requests (`/leave-requests`) are separate ledgers. Company off days are holidays in `off_days` (range + reason + `approved_by`). `OffDayService` is the only writer: an approved holiday materializes non-worked days as `attendances.status = off_day` with `off_day_id` and `off_day_days = 1`, never as a deduction. Worked rows and leave rows are never overwritten; reverting/deleting deletes or restores the synthesized rows.
- Employee leave lives in `leaves` through `LeaveRequest`/`LeaveRequestController` (`/leave-requests`, `/my-leave-requests`, `/employees/{employee}/leave-requests`, `PATCH /leave-requests/grant`). `LeaveRequestService` writes the attendance claim. Approval splits each request by the employee's `employees.off_day_allowance_days`: the covered part is `off_day`, the excess is `absent`. A holder of `grant_leave_requests` can force an excess day to remain `off_day` only with a justification (`force_off_day` + `approval_note`).
- The company-wide `settings.off_day_allowance_days_per_year` is a creation-time catalogue value copied into a new employee profile; it is never a runtime fallback. Only the employee's own allowance governs approvals and usage. The removed office-level allowance must not return.
- Settings → Operations exposes `missing_attendance_status` (`present`, `absent` or `off_day`). `MonthlyAttendanceProcessor` materializes an elapsed non-Friday workday with no row under that policy, but never for today/future days, and never over a company holiday, approved leave or recorded work.
- A salary's base `amount_paid` is immutable after creation; only payments, manual deductions and the automatic rows change. `SalaryController::update` rejects an amount change.
- Worked days, total days, off days and absent days are attendance-derived and read-only. The salary API ignores/refuses client-supplied day overrides. The automatic absence deduction is `amount_paid × absent_days ÷ total_days`; off days and present days are never deducted. Manual deductions remain additive.
- Salary advances are recovered from payroll by `SalaryAdvanceService`: one automatic `advance_recovery` salary deduction per period, capped at `total_amount − other deductions`, so net pay never goes negative. An advance larger than the period's payable is recovered in instalments across periods; each withholding is a `payroll` row in `employee_advance_settlements`. Deleting the salary (or opting the payslip out via `PATCH /salaries/{salary}/advance-recovery`) releases that payslip's allocations. The advance's own expense remains the cash record — recovery is never a second expense.
- An advance is a balance, not a flag: `employee_advances.settled_amount` caches the sum of its settlements and `status` is derived (`outstanding`, `partial`, `settled`) — it closes itself at zero. Cash the employee hands back is a `cash` settlement (`POST /employee-advances/{advance}/settlements`) and shrinks the linked payroll expense, which is the net cash the company has out. An advance can only be recorded in a month whose payslip has not paid out, cannot be edited below what was already paid back, and cannot be deleted while a paid payslip withheld it. The payslip deducts advances automatically but the recovery is optional until the payslip pays.
- `SalaryController` writes the attendance proration row under the `attendance_proration` code so it never collides with `advance_recovery`.
- Payslips snapshot `salaries.worked_days`, `salaries.total_days`, `salaries.absent_days`, `salaries.off_days` and `salaries.advance_recovery`, and itemize recovered advances. Advance receipts print employee + company copies on one A5 sheet (`templates/advance_receipt_template.vue`).
- `/payrolls` (`resources/ts/pages/payrolls.vue`) replaces Staff Salary: monthly KPIs, generation for missing payslips (`POST /salaries/generate`), deduction/payment dialogs, payslip preview/print, the register print and the advances ledger/receipt. `/staff-salary` is not a page.
- Permissions: `view_off_days`, `manage_off_days` (announce/edit) and `approve_off_days` (approve/reject/remove an approved holiday) are separate. A holder of `approve_off_days` announces an already-approved holiday in one step. `finance_officer` holds payroll + `view_off_days`.
- `edit_own_employee_profile` lets an employee update only their own personal/contact/guarantor/bank fields through `PUT /employees/{id}`; pay, position, office, department, supervisor, status, join date, allowance, commissions and account linkage still require `edit_employees`. `EmployeeController::updateOwnProfile` rejects any other submitted field rather than ignoring it.
- Self-service leave and the staff-page leave ledger accept a date range; Fridays are skipped (never rejected), and the range is stored one row per date.

## Real-time notifications

- Read `docs/facts/background-polling.md` before changing polling or background API error behavior. Notification/audit heartbeats, navbar inbox/count, workday status and version checks run only while visible, focused and online. Route initial/event loads through `useVisiblePolling` (`start(true)` / `run`) and keep completion-based single-flight scheduling.
- Background reads opt into `backgroundReadConfig` in the API layer (quiet error header, background dispatch marker and 10-second timeout). Local catches alone cannot stop Axios global error toasts. Preserve ordinary user-action error feedback; do not globally mute `ApiErrorNotifier` or clear unrelated toasts.
- Do not cancel already-dispatched heartbeat reads on blur; they clear server flags/cursors. Retain successful late responses through `useForegroundBatch` and present only when active, within the same token/session. Transport failure after server commit remains an acknowledgment limitation. A transient background refresh-token failure must not log the user out or discard an unsaved form.

- Delivery is heartbeat-based HTTP polling; there is no websocket/broadcast stack. Do not add one without an explicit decision.
- `Notification::deliverTo()` is the only delivery choke point. Keep it that way: it filters recipients by their notification preferences and sets `users.unread_notifications = true` for everyone it actually delivered to. New producers must go through it (directly or via `EducationalWorkflowNotificationService::notifyOnce`).
- `GET /api/notifications/heartbeat` (`view_own_notifications`) returns the caller's unread notifications only while their `unread_notifications` flag is true, then clears the flag inside a transaction that locks the user row, so a batch is delivered exactly once even under concurrent polls. Route order matters: register it before `apiResource('notifications')` so `{notification}` does not swallow `heartbeat`.
- The heartbeat is a separate channel from the on-launch inbox load (`GET /notifications`) and the 60s inbox refresh; both stay in place. Heartbeat items are merged into the same list (prepended, de-duped by id), so they share the existing mark-read/delete/click-through behaviour and `go_to_url` navigation.
- The frontend polls every 30s via `resources/ts/composables/useNotificationHeartbeat.ts`. `notification-ding.mp3` plays once per heartbeat batch, and once on app launch when the user already had unread notifications (`playLaunchDingOnce`); never once per notification. The only served copy is `public/sounds/notification-ding.mp3`, fetched at runtime from `/sounds/notification-ding.mp3` (Laravel serves `public/` at the origin root) and precached by the service worker. Do not keep a repository-root copy: it is never served and drifts from the real asset. The chime is user-disableable from Account settings → Notification preferences (stored as `account_settings.notification_sound`, default off (opt-in)); disabling it gates audio only and never suppresses in-app delivery.
- Notification producers are permission/role-scoped by the producer: task assignment notifies each assignee (`taskAssigned`), draft finance records notify `approve_draft_finance_records` holders (`financeDraftSubmitted`, key `finance.draft.submitted.{kind}.{id}`) and their approval/rejection notifies the recorder (`financeDraftStatusChanged`), and contract creation notifies the applicant's responsible and admission employees plus the seller (`contractGenerated`).
- Audits have a parallel real-time channel. `AuditLog::created` raises `users.unread_audit_logs` in one bulk `UPDATE` scoped to active holders of `view_audit_logs` / `view_team_audit_logs` (plus super admins); `GET /api/audit-logs/heartbeat` returns rows after the user's `audit_logs_seen_id` cursor, then advances the cursor and clears the flag inside a row-locked transaction. Register it before the audit-log resource routes. The flag write is skipped until its migration has run, because earlier migrations create audited models. `resources/ts/composables/useAuditHeartbeat.ts` polls every 30s and drives `NavBarAuditLogs`; one notice per batch, never per row.

## Optional audit colouring in registers

- `settings.audit_coloring_enabled` (default `false`) controls the Created/Changed/Deleted rail, changed-cell emphasis and audit-history action in register tables. It is edited in Settings → Operations (`edit_settings`) and read from the public company-branding payload so every signed-in user can gate the display without the settings grant.
- `GET /api/audit-logs/row-trails` returns the latest caller-visible audit per record for 1–100 ids under `view_audit_logs`, reusing the Audits page team scope. It is registered before `audit-logs/{auditLog}` so the literal segment is never bound as an id.
- The shared `responsive-data-table` appends the legend, inline-start rail, changed-cell emphasis and `tabler-history` action from one bounded quiet request per page. Raw-table pages use `useAuditTrail` and the same components. Enrichment failure, missing permission, disabled audit scope or no history leaves the existing table unchanged; audit history is never affected by the display toggle.

## PWA and offline caching

- Read `docs/facts/pwa.md` before changing PWA, caching, release identity or update behavior. It is the maintained behavior reference. Update facts and lifecycle regression tests with behavior changes.
- Update readiness requires a completed waiting (or already controlling) worker identifying the server-reported release, different from the running bundle. A version mismatch or waiting event alone must never raise the toast. Check both API metadata and worker at launch, every 15 visible minutes and on visibility return; keep checks coalesced.
- Activation/reload consent is per tab: wait for the accepted worker to be activated and control that tab, then purge API data and reload once. Never restore an unconditional controllerchange reload or a timer that forces reload. A 30-second activation timeout reports failure and allows retry. Use GET_VERSION messaging to reject same-version, stale or unidentified workers.
- Static icon output currently lives at the public root (`icon-192.png`, `icon-512.png`, `maskable-512.png`, `apple-touch-icon.png`, `icon-64.png`, `favicon.ico`), generated from `public/favicon.svg` by `npm run pwa:assets`; older `public/icons/` references are historical.

- The SPA is installable. `vite-plugin-pwa` runs in `injectManifest` mode with
  `resources/ts/service-worker/sw.ts`; the build emits `public/sw.js` at the origin root so its scope is
  `/`. `public/sw.js`, `public/version.json` and the static root icon files are generated and committed like
  `public/build` — never hand-edit them, run `npm run build` (icons: `npm run pwa:assets`).
- The worker precaches all of `public/build/assets/**` plus `/offline.html` and `/sounds/notification-ding.mp3` (public assets are outside the Vite manifest, so the chime and the fallback are added explicitly in `sw.ts`). Bundle hashes are revisioned, so
  only changed chunks are re-downloaded; `maximumFileSizeToCacheInBytes` is 4 MiB (covers the largest chunk).
- Authenticated `GET /api/**` is `NetworkFirst` (5 s, 500 entries / 24 h, status 200 only). Never cache
  `/api/notifications/heartbeat` and `/api/audit-logs/heartbeat` (they clear a server flag — a cached response stops delivery), `/api/auth/*`,
  `/api/version`, `/manifest.webmanifest`, file downloads or report exports. Those rules are `NetworkOnly` in
  the worker; keep them first.
- Data caches are namespaced `api-<__APP_VERSION__>`, `pages-<__APP_VERSION__>`, `assets-<__APP_VERSION__>`.
  On activate the worker deletes every other version's cache, so a deploy reclaims old assets and old API data.
- Per-user isolation goes through `clearAuthTokens()` in `resources/ts/utils/authStorage.ts`, which calls
  `purgeApiCache()`. It already runs on login (before storing), logout and 401 — keep that single choke point
  instead of purging in individual components.
- Version mechanism: the build bakes `__APP_VERSION__` (`package.json` version + git short SHA) into the bundle
  and writes `public/version.json`, served publicly at `GET /api/version`. `plugins/pwa` registers the worker
  with update-waiting semantics; `useAppVersion` polls metadata and requests explicit worker update checks.
  Only a verified ready release raises the translated reload toast. Purge API data when accepting the update
  or booting a different build, rather than merely detecting a mismatch. Activation is user-consented while
  old clients remain open; browsers may activate naturally once no old clients remain. Never call skipWaiting on install.
- Offline is read-only: there is no mutation queue. A write attempted without a connection fails with the
  translated `pwa.offline_action_blocked` message. Do not add Background Sync without an explicit decision.
- The manifest is served dynamically by `PwaManifestController` from Settings (`short_company_name` /
  `website_title` / `website_logo` / `primary_color`), with committed PNG fallbacks, so branding changes need no
  rebuild. PWA install and service workers require HTTPS (or `localhost`); `devOptions.enabled` is false, so
  service-worker behaviour is only testable against `npm run build` output.

## Asset

- Asset condition is required and limited to `new`, `good`, `fair`, `poor`, or `damaged` across migration, validation, API, filters, and UI.

## Working Rules

- The repository is large; inspect filenames and relevant areas first instead of reading built-in template files line by line.
- Prefer minimal, focused changes that fit the existing architecture and avoid adding duplicate logic to pages/components.
- When the user asks to edit a feature "end to end", update the related backend and frontend surface together: migration, model, resource, controller, seeder, factory, frontend API contract under `resources/ts/api`, Vue page/component, and any extra files directly required by those changes including backend routing in api.php and 1.router/routes.ts .
- Never run bare `vue-tsc` or `tsc` in this project. Use `npm run typecheck`, which runs `vue-tsc --noEmit`.
- `tsconfig.json` must retain `"noEmit": true`. Without it, `vue-tsc` emits `.vue.js`, sibling `.js`, and `.js.map` files into the source tree because no `outDir` is configured and `sourceMap` is enabled. These generated files can shadow `.ts` API contracts and execute stale code.
