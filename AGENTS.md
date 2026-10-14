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
- ApplicantContractPaymentService owns calculated balances and sticky payment overrides. CurrencyRateService persists supplied rates atomically.
- Preserve issued contract snapshots and receipt numbers. Receipt printing uses the namespaced A5 print workflow.

## Real-time notifications

- Delivery is heartbeat-based HTTP polling; there is no websocket/broadcast stack. Do not add one without an explicit decision.
- `Notification::deliverTo()` is the only delivery choke point. Keep it that way: it filters recipients by their notification preferences and sets `users.unread_notifications = true` for everyone it actually delivered to. New producers must go through it (directly or via `EducationalWorkflowNotificationService::notifyOnce`).
- `GET /api/notifications/heartbeat` (`view_own_notifications`) returns the caller's unread notifications only while their `unread_notifications` flag is true, then clears the flag inside a transaction that locks the user row, so a batch is delivered exactly once even under concurrent polls. Route order matters: register it before `apiResource('notifications')` so `{notification}` does not swallow `heartbeat`.
- The heartbeat is a separate channel from the on-launch inbox load (`GET /notifications`) and the 60s inbox refresh; both stay in place. Heartbeat items are merged into the same list (prepended, de-duped by id), so they share the existing mark-read/delete/click-through behaviour and `go_to_url` navigation.
- The frontend polls every 30s via `resources/ts/composables/useNotificationHeartbeat.ts`. `notification-ding.mp3` plays once per heartbeat batch, and once on app launch when the user already had unread notifications (`playLaunchDingOnce`); never once per notification. The served asset is `public/sounds/notification-ding.mp3`.
- Notification producers are permission/role-scoped by the producer: task assignment notifies each assignee (`taskAssigned`), payment requests notify `approve_pending_transactions` holders (`pendingSubmitted`), and contract creation notifies the applicant's responsible and admission employees plus the seller (`contractGenerated`).

## Asset

- Asset condition is required and limited to `new`, `good`, `fair`, `poor`, or `damaged` across migration, validation, API, filters, and UI.

## Working Rules

- The repository is large; inspect filenames and relevant areas first instead of reading built-in template files line by line.
- Prefer minimal, focused changes that fit the existing architecture and avoid adding duplicate logic to pages/components.
- When the user asks to edit a feature "end to end", update the related backend and frontend surface together: migration, model, resource, controller, seeder, factory, frontend API contract under `resources/ts/api`, Vue page/component, and any extra files directly required by those changes including backend routing in api.php and 1.router/routes.ts .
- Never run bare `vue-tsc` or `tsc` in this project. Use `npm run typecheck`, which runs `vue-tsc --noEmit`.
- `tsconfig.json` must retain `"noEmit": true`. Without it, `vue-tsc` emits `.vue.js`, sibling `.js`, and `.js.map` files into the source tree because no `outDir` is configured and `sourceMap` is enabled. These generated files can shadow `.ts` API contracts and execute stale code.
