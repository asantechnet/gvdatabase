# Task: WhatsApp template messages

- **Task ID:** whatsapp-template-messages
- **Created:** 2026-10-14
- **Last updated:** 2026-10-14
- **Status:** COMPLETED
- **Outcome:** A new **Options → WhatsApp Messages** workspace with one subpage per audience (visitors, applicants, employees) that manages reusable WhatsApp message templates (short title, labels, message, plus active/order metadata). Every audience exposes the placeholders the app fills from the record the message is sent to, and the message editor accepts only content WhatsApp renders correctly. The same templates are offered in the visitor follow-up list, the applicants list and the employees directory quick-contact menus, filtered to the audience of the page they are used on.

## 1. Requirements and acceptance criteria

- [x] AC1 `Options → WhatsApp Messages` navigation entry opens `/options/whatsapp-messages` (`whatsapp-messages`), deep-linkable per audience via `?tab=visitor|applicant|employee`.
- [x] AC2 The page has one subpage per audience; only the audiences the user may view are offered, and the active subpage is kept in the query string.
- [x] AC3 Each template row shows a short title, labels, the message body and secondary metadata (active state, sort order); create / edit / delete are available to users who may manage that audience.
- [x] AC4 Each audience exposes its own placeholder catalogue (visitor / applicant / employee fields) that the app fills from the record the message is sent to.
- [x] AC5 The message editor only allows content WhatsApp displays without trouble (plain text, line breaks, `*bold*`, `_italic_`, `~strikethrough~`, ```monospace```, links, emoji, placeholders) — no HTML/rich-text constructs, and pasted rich content is flattened to plain text.
- [x] AC6 The editor offers the audience's placeholders for insertion and shows a live preview rendered the way WhatsApp renders it, with a tokens ↔ sample-values toggle.
- [x] AC7 The quick-contact phone menu on the visitor follow-up list, the applicants list and the employees directory lists the *active* templates for that page's audience only, and each entry opens WhatsApp (`https://wa.me/<phone>?text=<rendered>`) with the message pre-filled from that row's data.
- [x] AC8 No template from another audience can appear in a given list, and the API refuses a message whose `{{tokens}}` are not valid for its audience.
- [x] AC9 Works in LTR/RTL, light/dark and on mobile widths; layout reuses existing page/table/dialog primitives.
- [x] AC10 End-to-end surface updated together: migration, model, support class, resource, controller, seeder, `routes/api.php`, `resources/ts/api`, page/component, `1.router/routes.ts`, navigation.

## 2. Scope and non-goals

- In scope: `whatsapp_templates` table + model + audience support class + placeholder registry; a REST resource with audience/active filtering and per-audience authorization; a `WhatsappTemplateSeeder`; a frontend API contract, shared render/format utility and template cache composable; a plain-text WhatsApp-format editor component with placeholder palette and live preview; the Options page with per-audience tabs; quick-contact integration in `visitor.vue` (follow-up list), `all_customers.vue` (applicants), `applicant-case.vue` (same audience, already renders the menu) and `staff_list.vue` (employees, new quick contact); locale keys in `en`/`fa-AF`/`ps-AF`; a PHP feature test and a frontend regression script.
- Out of scope: server-side WhatsApp delivery through a provider API (the schema and editor are structured so a sender can be added later without reworking either), inbound message handling, sending logs/campaign scheduling, media/attachment templates, an emoji picker, and changing the existing CRM "log conversation" behaviour or the Telegram/call actions.

## 3. Project findings

- **Routing.** `resources/ts/plugins/1.router/routes.ts` is the static, authoritative router; each route carries `meta.action`/`meta.subject` or `meta.anyPermissions` (OR semantics, consumed by `@layouts/plugins/casl.ts`). `/options/*` is the Options-path convention (`/options/finance/income-types`, `/options/contracts/documents`, `/options/currencies`).
- **Navigation.** `resources/ts/navigation/horizontal/index.ts` composes per-concept files; `settings.ts` owns the `common.navigation.options` group. A nav item supports only a single `action`/`subject` pair (`can(item.action, item.subject)`), so an entry gated by any-of-three permissions is left unfiltered and enforced by the route guard, exactly as the permission-free `search` destination already is in the CRM strip.
- **In-page subpages precedent.** `pages/contract/index.vue` is the canonical "one page, sections in `?tab=`, VTabs + VWindow + one workspace VCard" pattern (legacy routes redirect into the tab). `ErpPageHeader.vue` owns only the Contracts/CRM/Applications strips, so this Options page follows `contract/index.vue`.
- **Placeholder precedent.** `app/Services/Contracts/PlaceholderRegistry.php` declares placeholders once (machine key, label, contexts) and the frontend fetches them from the API instead of duplicating strings; `resources/ts/api/contractPlaceholders.ts` + `resources/ts/utils/contractPlaceholders.ts` localise labels via `translateOrFallback`, and `scripts/i18n/source-keys.mjs` harvests the registry keys into the audited translation set. Mirror this.
- **Quick contact.** `ContactActionMenu.vue` is imported by exactly three surfaces — `visitor.vue`, `all_customers.vue`, `applicant-case.vue` (confirmed by `graphify explain`). Its phone menu hard-codes WhatsApp (`https://wa.me/<digits>`), Telegram and call; actions with a `contactableType`/`contactableId` open the CRM "log conversation" dialog. Employees have **no** quick contact today: `staff_list.vue` renders `contact` as plain text.
- **Row data available for placeholder resolution** (already loaded client-side, so no extra request): `VisitorResource` → `name`, `contact`, `email`, `service`, `counselor.name`, `date`; `ApplicantResource` → `name`, `father_name`, `father_phone`, `backup_phone`, `contact`, `email`, `address`, `tax_number`, `service`, `status`, `current_stage.name`, `responsible_employee.name`; `EmployeeResource` → `name`, `father_name`, `contact`, `email`, `position`, `department_name`, `office.name`, `join_date`, `status`.
- **Backend conventions.** `response()->json(['success' => true, 'data' => ...])` or a bare `*Resource`/`*Resource::collection`; inline `$request->validate([...])`; `Route::apiResource(...)->middlewareFor(...)`; `EnsurePermission` takes a variadic permission list and passes when **any** matches (`permission:a,b` = OR). `Auditable` (soft deletes + change history) is the norm for business models. Permissions are a fixed catalog in `PermissionSeeder` with explicit grants in `RolePermissionSeeder`.
- **i18n gates are strict.** `npm run i18n:check` fails on raw strings in template attributes/text and on missing keys in any locale (ps-AF may fall back to fa-AF); `npm run i18n:test` runs every English key through all three locales and enforces placeholder parity. Any quoted literal matching `(common|pages|components|documents|backend|notifications|educational|erp|agencyStaff|serviceContracts).*` counts as a used key, in both `.vue` and `.ts` sources under `pages`/`components`/`layouts`/`navigation`/`api`/`utils`/`composables`. `WhatsApp` is in the checker's `properNames` allowlist.
- **Icons.** Class-based `tabler-*` icons must be referenced statically; `npm run build:icons` bundles only referenced tokens and warns about unresolved ones (a blank glyph is a real bug).

## 4. Graph and impact review

- Tools actually used: the `graphify` skill against the existing `graphify-out/graph.json` (12 149 nodes) — `graphify query` for the quick-contact/template/placeholder surfaces and `graphify explain "ContactActionMenu.vue"` — then repository search and direct file reads.
- Confirmed boundaries: `ContactActionMenu.vue` ← `visitor.vue`, `all_customers.vue`, `applicant-case.vue`; `PlaceholderRegistry` ships through `ContractController`; `localization.ts` is the shared label resolver.
- `staff_list.vue` consumes `Employee`/`EmployeeResource`/`EmployeeApi` with no quick-contact component, so employee support is purely additive.
- Downstream to update: `scripts/i18n/source-keys.mjs` (audit the new registry keys), the three locale JSONs, `components.d.ts` (regenerated by `unplugin-vue-components`), the icon bundle, `DatabaseSeeder`.
- Limitations: the graph does not model the icon generator or the i18n checker, so both are verified by running them; there is no router/layout harness, so the page regression script mounts the page component directly with mocked routing, as the existing scripts do.

## 5. Clarifications, decisions, and assumptions

- **User decisions (asked and answered):**
  1. **Permissions — reuse the record-type list permissions.** No new permission catalog rows. Viewing an audience's templates needs that audience's view permission (`view_visitors` / `view_applicants` / `view_employees`); creating, editing and deleting needs its edit permission (`edit_visitors` / `edit_applicants` / `edit_employees`). The page route is guarded with `anyPermissions` over the three view permissions, and the nav entry is left permission-free (a nav item supports only one subject) exactly like the existing permission-free `search` destination.
  2. **Delivery — deep link now, API later.** Choosing a template opens `https://wa.me/<phone>?text=<rendered message>`; nothing is sent by the server and no provider is integrated. The storage shape (audience + title + labels + plain-text body with `{{token}}` placeholders + active/order) and the single render utility already contain everything a future server-side sender needs, so adding one is additive (an outbound log table + a delivery service) and changes neither the schema nor the editor.
- **Assumptions and rationale (low risk, inferable from conventions):**
  - "A page with subpages" = one route with in-page sections selected by `?tab=`, matching `contract-documents`/`educational-master-data`, not three sibling routes.
  - Audiences are exactly `visitor`, `applicant`, `employee`, declared once in a support class so more can be added later ("et cetera").
  - Placeholder resolution happens client-side from the already-loaded table row; the backend registry is the single source of truth for tokens, source paths, labels and preview samples.
  - Only `active` templates are offered in the quick-contact menus; management screens show all.
  - `applicant-case.vue` already renders `ContactActionMenu` for applicants and receives the applicant templates for free — same audience, so AC8 still holds.
  - Employees have no CRM interaction record, so the employee quick contact opens the deep link without the "log conversation" dialog; visitor/applicant menus keep today's behaviour.

## 6. Implementation plan

- [x] **Step 1 — Schema.** `database/migrations/2026_10_14_000004_create_whatsapp_templates_table.php`: `audience` (string 32), `title` (string 150), `labels` (json, nullable), `message` (text), `active` (bool, default true), `sort_order` (unsigned small int, default 0), timestamps, plus `index(['audience', 'active', 'sort_order'])`.
- [x] **Step 2 — Audience catalogue.** `app/Support/WhatsappAudience.php`: the three audience constants + `ALL`, `viewPermission()`, `managePermission()`, `labelKey()`, `isValid()`.
- [x] **Step 3 — Placeholder registry.** `app/Services/Whatsapp/WhatsappPlaceholderRegistry.php`: per-audience groups of `{ token, label, source, sample }` (visitor / applicant / employee fields), `fieldsFor(audience)`, `tokensFor(audience)`, `groupsFor(audiences)`, and `unknownTokens(audience, message)` used by validation.
- [x] **Step 4 — Model + resource.** `app/Models/WhatsappTemplate.php` (`Auditable`, fillable, `labels` array + `active` bool casts, `audience`/`active` scopes, ordered default) and `app/Http/Resources/WhatsappTemplateResource.php`.
- [x] **Step 5 — Controller.** `app/Http/Controllers/WhatsappTemplateController.php`: `index` (audience filter, returns only the audiences the caller may view), `store` (audience-scoped manage check, token validation, label normalisation), `update`, `destroy`, and `placeholders` (registry filtered to viewable audiences).
- [x] **Step 6 — Routes.** `routes/api.php`: `apiResource('whatsapp-templates')->only([...])` with `permission:view_visitors,view_applicants,view_employees` on `index` and `permission:edit_visitors,edit_applicants,edit_employees` on the write actions; `GET whatsapp-template-placeholders`; a comment recording that delivery is a deep link.
- [x] **Step 7 — Seeder.** `database/seeders/WhatsappTemplateSeeder.php` with one starter template per audience (title, labels, message using placeholders) + registration in `DatabaseSeeder`.
- [x] **Step 8 — API contract.** `resources/ts/api/whatsappTemplates.ts`: `WhatsappAudience`, `WhatsappPlaceholderField`, `WhatsappTemplate`, payload types and CRUD + `getWhatsappPlaceholders()`, normalising `response.data.data`.
- [x] **Step 9 — Shared utility.** `resources/ts/utils/whatsappTemplates.ts`: literal audience label/icon maps, `WHATSAPP_MESSAGE_LIMIT` (4096), `renderWhatsappMessage()`, `buildWhatsappUrl()`, `formatTokens` + `wrapSelection()` for the toolbar, and `whatsappPreviewHtml()` implementing WhatsApp's supported markers.
- [x] **Step 10 — Template cache.** `resources/ts/composables/useWhatsappTemplates.ts`: module-level cache of viewable templates + placeholders keyed by audience, `ensureTemplates()`, `invalidateWhatsappTemplates()`.
- [x] **Step 11 — Editor.** `resources/ts/components/whatsapp/WhatsappMessageEditor.vue`: plain textarea + format toolbar + placeholder palette + preview pane + character counter; paste flattened to plain text; RTL-safe logical CSS.
- [x] **Step 12 — Page.** `resources/ts/pages/whatsapp-messages.vue`: workspace VCard, VTabs/VWindow per viewable audience, `?tab=` sync, per-audience toolbar + rows (title, label chips, message preview, active/sort metadata), editor dialog, delete confirmation, empty/loading/error states, toasts.
- [x] **Step 13 — Wiring.** `1.router/routes.ts` (`whatsappMessages` component + `/options/whatsapp-messages` with `anyPermissions`) and `navigation/horizontal/settings.ts` (Options leaf with `tabler-brand-whatsapp`).
- [x] **Step 14 — Quick contact.** `ContactActionMenu.vue` gains optional `audience` + `context` props and renders the audience's active templates as menu entries above the existing actions; wire `audience`/`context` in `visitor.vue`, `all_customers.vue`, `applicant-case.vue`, and add the missing quick-contact cell to `staff_list.vue`.
- [x] **Step 15 — Locales + icons.** Keys under `pages.whatsapp.*` / `common.navigation.whatsappMessages` in `en`, `fa-AF`, `ps-AF`; extend `scripts/i18n/source-keys.mjs` to harvest the new registry keys; run `npm run build:icons`.
- [x] **Step 16 — Tests.** `tests/Feature/WhatsappTemplateTest.php`: audience filter, only-visible-audiences index, per-audience manage authorization, unknown token rejection, unknown audience rejection, update/destroy, placeholders endpoint. Plus `scripts/whatsapp-templates-regression.mjs`.

## 7. Validation plan and results

- [x] `php artisan test --filter=WhatsappTemplateTest` — **9 passed (48 assertions)**.
- [x] `php artisan test` (full suite regression) — **402 passed, 14 failed (3713 assertions)**. The 14 failures are contract-creation payloads rejected with 422 (`start_date` prohibited, `contract_template_version_id` required) in `AcademySlipTest`, `ApplicantContractStageTest`, `ContractDocumentDesignTest`, `ContractTaskAssignmentTest`, `EducationalContractIntegrityTest`, `SimpleEducationalWorkflowTest`. Attribution: `git show HEAD:app/Http/Requests/StoreServiceContractRequest.php` still has `start_date => required` and no `contract_template_version_id`, while the working tree (modified before this task, not by it) prohibits `start_date` and requires the version id. Pre-existing, unrelated to WhatsApp; no WhatsApp test is among the failures.
- [x] `npm run typecheck` — exit 0.
- [x] `npm run build:icons` — 294 references, 292 tabler icons bundled, no unresolved-token warning; `tabler-brand-whatsapp` (and every `tabler-*` the new files use) is in the generated CSS.
- [x] `npm run i18n:check` — exit 0 (3163 used keys, 4559 English keys).
- [x] `npm run i18n:test` — exit 0 (runtime switching + schema for en, fa-AF, ps-AF).
- [x] `node scripts/whatsapp-templates-regression.mjs` — exit 0 for `en` and `fa-AF`: one subpage per audience, each showing only its own rows (visible-row filtering), row labels/preview/token list/order/active state, editor dialog is a plain textarea with no rich-text surface and a 5-control toolbar, stored message loads, preview renders `*bold*` and sample values, the toolbar wraps the selection, character counter against the 4096 limit, the preview toggle switching between sample values and the raw tokens, a rich paste landing as plain text with no markup in the editor, a create/edit/delete round trip driven through the page (stateful API mock: the edited row shows its new title, the new row appears, the deleted row leaves the list, each step reports its own toast and reaches the API with the right payload), mobile (390px) + dark theme, quick-contact menu offers only this audience's active templates, exactly one `wa.me` open with the row's phone and row-filled message, and the CRM dialog prefilled from the template title. No console errors.
- [x] `node scripts/visitor-follow-up-regression.mjs` — exit 0 (quick-contact menu change did not regress the CRM lists).
- Results: all of the above pass except the pre-existing contract-validation failures described above.
- Checks not run and why:
  - **PHP suite on MySQL** — MySQL/MariaDB in this environment refuses connections (`SQLSTATE[HY000] [2002] Connection refused`; `service mariadb start` needs an interactive sudo password), so the suite ran with `DB_CONNECTION=sqlite DB_DATABASE=:memory:`. The user's own `php artisan migrate:fresh --seed` on MySQL succeeded, which covers the migration and the seeder.
  - **`npm run lint`** — `eslint` is not installed in `node_modules` (`sh: eslint: not found`).

## 8. Risks and mitigations

- Templates used on the wrong page — mitigation: audience is a validated enum, the menu filters by it, and both the API test and the regression script assert it (AC8).
- A new class-based icon renders blank — mitigation: static `tabler-*` names only, plus `npm run build:icons` and an assertion in the regression script.
- Rich-text leakage into a WhatsApp message — mitigation: plain textarea + format toolbar, paste flattened to `text/plain`, preview renders only supported markers.
- i18n gate failure — mitigation: all copy through keys, registry labels harvested by `source-keys.mjs`, both i18n scripts run before completion.
- Employee quick contact touches a heavily used table — mitigation: additive `#item.contact` cell only, no header/column changes, and the visitor follow-up regression is re-run.
- Route/menu available to users without any of the three view permissions — mitigation: `anyPermissions` guard on the route; the API returns nothing for them.

## 9. Progress log

- 2026-10-14 — DISCOVERY: read the router, navigation, `contract/index.vue` (in-page sections), `ErpPageHeader.vue`, `ContactActionMenu.vue`, `visitor.vue`, `all_customers.vue`, `staff_list.vue`, `announcements.vue`, the `PlaceholderRegistry` + `contractPlaceholders` pair, `VisitorController`/`ContractTypeController`/`AnnouncementController`, the visitor/applicant/employee models and resources, `EnsurePermission`, `PermissionSeeder`/`RolePermissionSeeder`, the i18n check/runtime scripts, `VisitorFollowUpTest`, and `docs/ai/visitor-follow-up-list.md`.
- 2026-10-14 — GRAPH: ran `graphify query` for the quick-contact/template/placeholder surfaces and `graphify explain "ContactActionMenu.vue"`; consumers confirmed as `visitor.vue`, `all_customers.vue`, `applicant-case.vue`.
- 2026-10-14 — CLARIFIED: the user chose the record-type list permissions (no new permission rows) and the deep-link delivery now / server sender later shape.
- 2026-10-14 — PLAN: plan finalised and saved before implementation.
- 2026-10-14 — PROGRESS: implemented steps 1–16 (migration, audience support, placeholder registry, model + resource, controller, routes, seeder, API contract, render utility, template cache composable, editor component, Options page, router + navigation, quick-contact wiring on visitor/applicants/employees, locales + icons, PHP test + regression script).
- 2026-10-14 — PROGRESS: fixed two regression-harness defects found while running `scripts/whatsapp-templates-regression.mjs` — the template menu entry was clicked through its children as well, opening three windows, and the CRM-dialog assertion checked the dialog's static card title instead of the prefilled conversation title field.
- 2026-10-14 — PROGRESS: extended the regression with a create/edit/delete round trip through the page (the API mock became stateful and records the writes), plus the preview tokens/samples toggle and a rich-paste-to-plain-text check, so AC3, AC5 and AC6 are now verified in the browser and not only by reading the component.
- 2026-10-14 — VALIDATION: `npm run typecheck` exit 0; `npm run build:icons` exit 0 no unresolved tokens; `npm run i18n:check` exit 0; `npm run i18n:test` exit 0; `php artisan test --filter=WhatsappTemplateTest` 9 passed; `node scripts/whatsapp-templates-regression.mjs` exit 0 (en, fa-AF); `node scripts/visitor-follow-up-regression.mjs` exit 0.
- 2026-10-14 — VALIDATION: full `php artisan test` on SQLite — 402 passed / 14 failed; every failure is a pre-existing 422 on the built-in-contract payload (uncommitted `StoreServiceContractRequest` changes that predate this task), none related to WhatsApp.
- 2026-10-14 — BLOCKER (environmental, worked around): MySQL unavailable in this shell, so the PHP suite ran against in-memory SQLite.

## 10. Completion summary

- Delivered: the `whatsapp_templates` table + `WhatsappTemplate` model (audited) + `WhatsappAudience` support class + `WhatsappPlaceholderRegistry`; `WhatsappTemplateController` (audience/active filtering, per-audience authorization, token validation, label normalisation, placeholders endpoint) with routes and `WhatsappTemplateSeeder`; the frontend API contract, `renderWhatsappMessage`/`buildWhatsappUrl`/`whatsappPreviewHtml` utility and `useWhatsappTemplates` cache; `WhatsappMessageEditor.vue` (plain-textarea editor, four format markers + placeholder palette, tokens ↔ sample-values preview, 4096-character counter); the `Options → WhatsApp Messages` page with one subpage per audience and `?tab=` deep links; quick-contact integration in the visitor follow-up list, the applicants list, the applicant case and the employees directory; `en`/`fa-AF`/`ps-AF` copy; `tests/Feature/WhatsappTemplateTest.php` (9 tests, 48 assertions) and `scripts/whatsapp-templates-regression.mjs`.
- Acceptance criteria satisfied: AC1–AC10. AC8 is enforced twice (API rejects foreign tokens, PHP test) and the menus are asserted to show only the page's audience; AC9 is covered by the regression's 390px + dark-theme + `fa-AF` (RTL) passes.
- Tests/checks passed: `npm run typecheck`, `npm run build:icons`, `npm run i18n:check`, `npm run i18n:test`, `php artisan test --filter=WhatsappTemplateTest` (9), `node scripts/whatsapp-templates-regression.mjs`, `node scripts/visitor-follow-up-regression.mjs`.
- Remaining limitations or follow-up work: the 14 pre-existing contract-payload test failures in the working tree (not introduced here) still need fixing in their own task; the PHP suite could not be run on MySQL in this shell (SQLite substitute), so a MySQL run is worth doing before release; `npm run lint` could not run because `eslint` is not installed; delivery stays a `wa.me` deep link — a server-side sender is a later, additive task.
