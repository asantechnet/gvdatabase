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

## Vuexy Quirks

- `AppDateTimePicker` (Flatpickr) inside a Vuetify dialog must stay in its normal body-level popup mode (`static: false`). Static mode can be clipped or hidden by the dialog structure.
- When using that picker in a dialog, apply a zero-offset transform after `onOpen`: `transform: translateY(0) scale(0.86)` with `transform-origin: top left`. The explicit zero-offset transform is required for the calendar to stay docked to its input; do not remove it merely because it has no visible vertical offset.
- Keep the opened Flatpickr calendar above dialog content with an appropriately high z-index.

## Documents and Printing

- Document rendering and printing are core requirements and must be treated as high-importance functionality.
- Printed documents must be accurate, clean, responsive, and suitable for standard paper sizes.
- Invoices and payslips use A5 sizing.
- Contracts and similar larger documents use A4 sizing.
- Any document-related change should preserve print quality and validate the relevant paper layout.
- Treat print CSS in this SPA as global and persistent even when it originates in a route component. Never use broad print selectors such as `header`, `footer`, `.v-card`, or layout-shell selectors without a print-mode namespace; a previously visited route can otherwise alter later print jobs through CSS injection order.
- Use `resources/ts/utils/printDocument.ts` for printing. It applies a feature-specific body class, waits for Vue and browser layout, injects the current document's `@page` rule last, and cleans up temporary state after printing. Prefix every feature-specific global `@media print` selector with that body class.
- Never leave `@page` inside a route or template stylesheet. CSS cannot namespace `@page`, so lazy-loaded SPA styles for A4, A5, Letter, portrait, and landscape documents compete by source order. Pass the page size and margins to `printDocument` so the active rule is injected only for that print job.
- Keep printable DOM class names feature-specific, explicitly hide the print host on screen and show it only under its print-mode body class, and avoid depending on framework `d-print-*` utilities. Scope component styles where possible; namespace the application-shell overrides that must be global.
- Reset the real layout ancestors around a print host, including their padding, flex growth, and viewport minimum heights. A full-paper element with `break-inside: avoid` will be pushed to a blank first page if any ancestor leaves even a small amount of space before it.
- Apply forced page breaks only with `.print-page:not(:last-child)`. Do not set `page-break-after: always` on every page and rely on a last-child override, because print engines can still paginate a trailing blank sheet.

## Invoicing

- Customers have no currency. Services own their fee and currency; invoices independently select their output currency.
- Invoices belong to one customer and one currency, and contain selected customer-assigned services through `invoice_service`.
- Invoice totals are calculated on the backend by converting each service fee with `service fee / service currency rate * invoice currency rate` and then applying tax and discount.
- Snapshot service names, source fees/currencies/rates, invoice currency rates, converted fees, quantities, and line totals when issuing an invoice. Later service or currency changes must not alter issued documents.
- Users cannot edit issued invoices. Laravel may update system-managed fields; do not enforce invoice immutability at the database/model level.
- Never delete an issued invoice. Void it with a reason while retaining its number, totals, service snapshots, and audit history.
- `incomes.invoice_id` is nullable. An invoice can have multiple income records for partial payments, while unrelated incomes remain valid.

## Asset

- Asset condition is required and limited to `new`, `good`, `fair`, `poor`, or `damaged` across migration, validation, API, filters, and UI.

## Working Rules

- The repository is large; inspect filenames and relevant areas first instead of reading built-in template files line by line.
- Prefer minimal, focused changes that fit the existing architecture and avoid adding duplicate logic to pages/components.
- When the user asks to edit a feature "end to end", update the related backend and frontend surface together: migration, model, resource, controller, seeder, factory, frontend API contract under `resources/ts/api`, Vue page/component, and any extra files directly required by those changes including backend routing in api.php and 1.router/routes.ts .
- Never run bare `vue-tsc` or `tsc` in this project. Use `npm run typecheck`, which runs `vue-tsc --noEmit`.
- `tsconfig.json` must retain `"noEmit": true`. Without it, `vue-tsc` emits `.vue.js`, sibling `.js`, and `.js.map` files into the source tree because no `outDir` is configured and `sourceMap` is enabled. These generated files can shadow `.ts` API contracts and execute stale code.
