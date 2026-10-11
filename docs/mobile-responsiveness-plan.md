# Frontend responsiveness implementation plan

Audit date: 2026-10-08. Scope: every file under `resources/ts`, with implementation changes centered there. This is an implementation plan, not an implemented redesign.

## 1. Desired result

An employee can start their day, find a record, inspect it, complete a form, manage work, and end their day on a 300px phone without sideways page scrolling or unreachable controls. Tablet layouts make sensible use of the extra space. Existing desktop workflows and document printing retain their quality.

Compactness comes from smaller spacing, restrained headings, and changing layouts. Long screens may scroll vertically. “Everything visible” means every field and action is reachable inside the viewport; it cannot mean putting an entire long form on one screen.

Operational tables become a list of compact cards below 1000px. Each collapsed card shows one business identifier, such as a contract number. Tapping the card opens the same read-only details popup as its View action. Editing, deleting, printing, approving, and selection remain separate deliberate actions.

## 2. Audit coverage and evidence limits

The file inventory is in [mobile-responsiveness-inventory.csv](mobile-responsiveness-inventory.csv). It includes every file under `resources/ts`, with source signals and an assigned migration responsibility. The inventory is a scope ledger, not a claim that every screen has passed browser testing.

Source inspection covered the full file inventory, table/dialog declarations, routing, shared layout and Vuetify registration, global sizing, custom page families, task views, account views, and document workflows. Selected high-impact implementations were read in detail. The existing graph was queried for relationships and current source was used to verify findings; the graph includes retired feature names and is not authoritative for current scope.

Snapshot counts: 387 total files; generated icon CSS was inventoried but excluded from the text scan.

| Surface | Count | Meaning |
| --- | ---: | --- |
| Vue files | 210 | Includes custom UI, shared framework wrappers, templates, and legacy/demo components |
| Page Vue files | 64 | Includes wrapper pages, template components, and legacy Kanban components; not 64 distinct live routes |
| Table declarations | 52 | 24 ResponsiveDataTable, 3 ResponsiveDataTableServer, 2 VDataTable, 16 VTable, 7 native table |
| VDialog declarations | 116 | Source declarations, not all reachable in the live product |
| VNavigationDrawer declarations | 2 | The customizer and task comments; other components named “drawer” actually use dialogs |

Browser work attempted:

- `php artisan serve --host=127.0.0.1 --port=8000` failed during application boot because the configured MySQL connection was unavailable, including after an escalated retry.
- Playwright CLI launches failed at the daemon stage, including a temporary writable-cache configuration.
- The existing `scripts/educational-ui-regression.mjs` successfully compiled the current service-contract Vue page and shared components into mock-data HTML previews in `/tmp/educational-erp-preview`.
- Direct Playwright Chromium launch failed with a restricted socket/crashpad error. A headless-shell fallback path was unavailable.

**No fresh browser screenshots or rendered-layout measurements were obtained.** Findings below are source-confirmed design risks, not claims of visually observed defects. The first implementation phase must establish a working preview and capture the baseline before declaring visual approval. Mock component previews are useful but cannot prove the real navigation shell, global CSS cascade, authentication, APIs, mobile keyboard, or printing.

## 3. Findings that determine the architecture

| Finding | Evidence | Implementation implication |
| --- | --- | --- |
| Responsive tables already have a shared implementation | `components/responsive-data-table/responsiveDataTable.ts:123`; registration in `plugins/vuetify/index.ts:52` | Extend the existing table boundary instead of adding a second table framework |
| Cards are opt-in and use an independent 959px media query | `responsiveDataTable.ts:141–157`; only `pages/audits.vue` passes `mobile-cards` | Migrate every operational table explicitly and consolidate the card breakpoint |
| Card taps expand fields inline | `responsiveDataTable.ts:223–240`, `:278–289` | Replace this behavior with an explicit View callback; reserve expansion for intentional secondary content |
| Choosing the title is heuristic | `responsiveDataTable.ts:191–194` | Require explicit title keys/formatters; the heuristic does not prioritize `contract_number`, `receipt_number`, or `serial_number` |
| Actions are extracted from rendered VNodes and inferred from icons | `responsiveDataTable.ts:98–106`, `:382–435` | Introduce a typed action contract; do not infer which action is View from icon names or slot trees |
| A custom body slot bypasses the shared mobile renderer | `responsiveDataTable.ts:448–451` | Audit body/group/expanded slots and supply an explicit mobile slot where necessary |
| Global CSS forces narrow tables back into a wide grid | `resources/styles/styles.scss:590–610` sets max-content width and nowrap | Add an explicit card-mode reset that wins regardless of lazy-route CSS order |
| Card styling is local to audits | `pages/audits.vue:861–868` | Move the reusable rules to shared styles; retain only audit-specific styling locally |
| UI scaling is a root font override | `utils/uiScale.ts:7–16`, called by `main.ts` | Preserve user size preferences and introduce separate responsive spacing/type tokens; reducing the root alone cannot resize px utilities or fixed controls consistently |
| The start popup has desktop padding and decoration | `layouts/components/UserProfile.vue:182–217`: pa-8, 72px avatar, 38px icon, large button | Extract a responsive StartDayDialog with compact spacing and a clear close affordance |
| The end popup has one non-wrapping title row and five growing textareas | `components/workday/EndDayDialog.vue:75–160` | Use a bounded dialog, a wrapping header, a scrollable body, and reachable actions; progressively disclose optional fields |
| Navigation contains many simultaneous controls | Both DefaultLayoutWith*Nav components; ApplicantPortalLayout | Consolidate secondary controls into a menu and validate the shell at 300px, rather than shrinking every icon |
| Several surfaces bypass data-table wrappers entirely | applicants, master data, reports, bonuses, currencies, attendance, dashboard and portal | Shared wrapper changes alone cannot complete this task |
| Some workflows depend on dragging | applicant-pipeline, task Kanban, calendar, Gantt, letterhead editor | Every drag-based operation needs an equivalent touch/keyboard form action |
| Task comments use a fixed 400px drawer | `TaskCommentsDrawer.vue:72–78` | Constrain to the viewport and make the message composer keyboard-aware |
| Document dimensions and screens are coupled in several templates | `pages/templates/*`, receipt and contract printers | Adapt the screen preview host while retaining actual A4/A5 paper geometry |

Several current patterns are worth retaining: logical CSS properties, `minmax(0, 1fr)` grids, the ERP field/header components, centralized API contracts, existing `FullDetailsButton` formatting, and the namespaced print helper. Consolidate these rather than replacing the product's visual language.

## 4. Responsive design specification

### Widths and layout behavior

Use CSS pixels, including when browser zoom reduces the available width. Keep Vuetify's existing framework grid thresholds initially; introduce shared product layout thresholds instead of silently redefining every `sm`, `md`, and `lg` utility.

| Width | Product layout | Lists | Forms and overlays |
| --- | --- | --- | --- |
| 300–359px | Extra compact phone; 8px page gutters | One full-width identifier card per row | Single column; long forms/details use full-screen dialogs; short confirmations remain bounded |
| 360–499px | Phone; 12px gutters | Same card behavior | Single column; 12–16px inner padding |
| 500–899px | Tablet; 16px gutters | Same cards, one column, comfortably readable | Two columns only where each field has sufficient width; dialogs use available height |
| 900–999px | Explicit transition range; 20px gutters | Cards continue | Typically two-column forms; no accidental overflow at the desktop boundary |
| 1000–1279px | Small laptop; existing desktop appearance | Desktop tables, with container-based fallback for an unusually narrow host | Existing desktop widths bounded to their host |
| 1280–2000px+ | Desktop; existing boxed/fluid preference | Desktop tables | Preserve established layout; bound readable text and form widths |

Centralize `phone = 500`, `tabletEnd = 900`, and `desktop = 1000` in a small `responsiveLayout.ts` utility and `useResponsiveLayout.ts` composable. CSS uses the same documented values. Overlay-navigation policy is separate: current layouts have 1280px-specific navigation rules. Reconcile store thresholds, toggles, and CSS together; a persisted horizontal-navigation preference must remain usable on phones.

Favor container queries for components embedded in a drawer or split workspace. A desktop viewport does not guarantee a wide component. Use container width to collapse field groups and toolbars; use the shared viewport policy for the main list mode. Any fallback to cards on a desktop-width narrow host must be explicit and tested.

### Sizing and density

Keep the root font preference controlled by `uiScale.ts`. Store only the user preference, not the current viewport density. A device resize must not write a different preference into localStorage.

Introduce semantic variables for page gutter, section gap, card padding, dialog padding, heading sizes, control spacing, and radius. At 300px target approximately 80% of the current large-screen spacing and decorative sizes; allow more substantial restructuring when necessary to fit a real control. Do not apply `transform: scale(.8)` or CSS zoom to the app.

| Element | Desktop reference | Compact starting point | Constraint |
| --- | --- | --- | --- |
| Large section gap | 24px | 18–20px | Keep related fields visually grouped |
| Dialog/card padding | 24px | 16–20px; 12px on tight phones | Reduce nested padding before reducing text |
| Page heading | 24–28px | 20–22px | Wrap naturally; no clipped titles |
| Body text | Current 14–16px styles | 14–16px | Must remain readable in Persian/Pashto/Arabic script |
| Editable field text | Current field typography | Start at 16px on phones | Validate iOS focus behavior on a real device |
| Primary controls | Current desktop density | 44–48px effective tap height | Long labels wrap or use a justified shorter translation |
| Row cards | New pattern | Minimum 52px high; 8px separation | Whole identity area is an easy tap target |
| Decoration/avatar | Large visual elements | Approximately 20% smaller | Decorative changes must not reduce action targets |

44px is the proposed product target for important controls, not a claim that WCAG AA requires every control to be 44px. WCAG 2.2's target-size minimum is 24px with defined exceptions; its enhanced target criterion uses 44px. [W3C target-size guidance](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html).

User-selected larger text must continue to work. Test all four current UI-scale options and 200% browser zoom. Do not multiply a 0.8 viewport font scale by a 0.9 user preference and accidentally make form text too small.

### Shared styling boundary

Add `resources/ts/styles/responsive.scss` and import it from `main.ts` after the existing app styles. Keep responsive overrides inside `@media screen`; use feature/component classes rather than blanket `.v-card`, `header`, or `footer` rules.

This keeps the implementation within the requested frontend directory while accounting for `resources/styles/styles.scss`, which is a dependency outside that directory. The existing max-content table rule needs a deliberate `.responsive-data-table--mobile-cards` override. A later cleanup of stale rules in `resources/styles` can be separate; it is not required to make this scoped implementation work.

Do not assume “imported last” is enough: lazy-loaded route styles can arrive later. Put base card-mode rules with the shared component, avoid equal-specificity route overrides, and test different navigation histories. Choose one owner per sizing rule and avoid accumulating competing `!important` declarations.

## 5. Shared components and contracts

Reuse existing components where practical. Proposed additions have one clear responsibility each:

| Boundary | Change |
| --- | --- |
| `responsiveLayout.ts` / `useResponsiveLayout.ts` | Shared product thresholds; viewport flags; no duplicate per-page media-query logic |
| `styles/responsive.scss` | Screen-only density tokens, responsive shell/layout primitives, card-mode resets |
| `AppResponsiveDialog.vue` | Short/long/details dialog variants, bounded width/height, header/body/footer slots, focus and close behavior |
| Existing `ErpPageHeader`, `AppSearchHeader` | Compact title/action/subnavigation behavior; existing pages adopt the shared header as appropriate |
| Existing `DataTableFilterMenu`, `ErpDateRange` | Phone filter sheet; bounded tablet menu; stacked dates at extra-narrow widths |
| Existing responsive-data-table implementation | Explicit identifier, View callback, row actions, mobile sorting/selection/pagination, shared visual treatment |
| Extracted `RecordDetailsDialog.vue` | Controlled reusable read-only details viewer using current FullDetailsButton formatting |
| Existing `FullDetailsButton.vue` | Thin trigger for the controlled viewer; desktop and card callers use the same details configuration |
| `StartDayDialog.vue` | Extract the start workflow from UserProfile; responsive presentation using the current Workday API |
| Existing `EndDayDialog.vue` | Adopt the shared long-form dialog and mobile information hierarchy |

Avoid one new component for every Vuetify primitive. Keep domain-specific forms and validation in their existing owners. Shared wrappers must forward attributes, slots and events intentionally, including `persistent`, scrollable behavior, loading, and accessible labels.

Verify how the Vite/Vuetify auto-import plugin resolves `VDataTable` and `VDataTableServer`. Global Vuetify registration is not sufficient evidence that every compiled page uses the custom wrappers. Standardize operational consumers on explicit `ResponsiveDataTable` / `ResponsiveDataTableServer` imports or component resolution, and verify the production bundle uses those boundaries.

### Mobile list API

Design a typed contract around the existing wrappers. Example target API, to be refined against actual generic typings during Phase 1:

```ts
interface MobileRowPresentation<T> {
  title: (item: T) => string
  view: (item: T) => void | Promise<void>
  actions?: (item: T) => RowAction<T>[]
}

interface RowAction<T> {
  key: string
  label: string
  icon: string
  visible?: (item: T) => boolean
  disabled?: (item: T) => boolean
  run: (item: T) => void | Promise<void>
}
```

Use an explicit `mobileTitleKey` for straightforward scalar identifiers and a formatter for composite identities such as attendance date plus employee name. No runtime discovery of View by inspecting a VNode, querying the DOM for an eye icon, or synthesizing clicks.

### Card behavior

- The collapsed identity area shows one identifier and a small directional affordance. No hidden secondary columns are squeezed into the title slot. Long identifiers wrap; they are not irretrievably truncated.
- The primary area is a semantic button, or an equivalent accessible element with Enter/Space support and a visible focus state. Its accessible name includes “View details” and the identifier.
- A selection checkbox and a separate more-actions button sit beside the identity area when needed. They are siblings of the primary button, not nested controls, and do not trigger View.
- Actions open a compact, labeled action sheet. Destructive actions retain the existing confirmation flow. Do not place destructive actions directly on a phone card.
- Tapping View in the action sheet and tapping the identity call the same handler with the same record. Preserve permissions and disabled/loading states.
- Keep one controlled details dialog per list where practical, not one mounted dialog for every row. Avoid independently fetched or independently paginated mobile data.
- On close, return focus to the originating card/action. Keep the filter, page, sort, and scroll position.
- Supply loading skeletons, empty/search-no-results states, retry/error messaging, and a bounded pagination footer. A card with missing data uses a deliberate business fallback such as an existing identifier, not a guessed array index.

### Preserve table semantics and state

The client and server wrappers must retain their public attributes, slots, selection model, item-value identity, pagination, sorting, emitted update events, and loading semantics. Use the same processed rows as the desktop table. Server tables must issue the same request for both layouts; viewport changes must not reset data or issue duplicate requests.

Prototype the mobile renderer against Vuetify 3.10.8's actual slot contract before migrating consumers. The current implementation puts cards in a spanning table cell; if retaining that bridge, explicitly handle hidden column headers, table sizing, accessibility, and selection outside the title action. Do not reach into undocumented Vuetify internals or duplicate its selection/pagination engine to get a separate list.

Test client and server tables, grouped and expanded content, selection, custom item/body slots, action menus, and resize while a popup is open. Table headers disappearing must not remove mobile sorting or select-all; provide those controls in the list toolbar.

## 6. Start/end workday: first production migration

### Entry points

Preserve the account-menu action and add a prominent permission-aware workday control to the home overview. This is a more discoverable daily entry point than requiring users to know it is inside their avatar menu. Use the same status/API owner to avoid two pollers and contradictory states.

Support every current status: not started, active, awaiting report, completed, blocked, checkout correction needed, and ineligible. Start remains an explicit user action; opening the menu or dialog never creates attendance.

### Start my day

- Phone: short centered dialog with bounded width, 16px padding, approximately 48–56px decorative avatar, wrapping title, short explanatory text, a full-width primary button, and an obvious close button.
- Tablet/desktop: retain the familiar centered confirmation with more breathing room; cap width near the current 560px.
- Explain location only when the current office policy requires it. Show “Checking location” and “Starting…” during the respective stages, rather than leaving an unexplained spinner.
- Show actionable denied/unavailable location and connection errors inside the dialog. Preserve the user's ability to retry; prevent duplicate submissions.
- On success, close, show a toast through vue-toastification, and update the visible status. Do not alter geofence rules, secure-context requirements, or repeat-start recovery in `api/workday.ts`.

### End my day

- Phone: full-screen long-form dialog with a compact wrapping title/header, session date on its own line if needed, one scrollable body, and reachable footer actions.
- Show required Summary first. Put Achievements, Challenges, Next-day plan, and Support needed in an “Additional report details” expansion, initially collapsed on phones. Optional content remains editable and preserved when collapsed; submitted payload is unchanged.
- Tablet/desktop: show the full report by default, with readable textarea widths; retain the current maximum width near 700px.
- Bound auto-growing textareas to a sensible editing height and allow scrolling inside long input content. Validate counters and errors without pushing the action area offscreen.
- Primary “Submit and end day” action is full-width on a tight phone. The cancel/close action remains visible and unambiguous. Stack long action labels instead of forcing a squeezed horizontal row.
- Preserve draft-on-close, attendance ID, session date, stale-response protection, and reset-on-session-change. Changing layout must not clear a report or attach it to another session.
- On error, keep the draft, show errors inline, and focus/scroll to the first invalid field. On success, retain the existing state-change event and API behavior.
- Use a draft warning through the existing form warning infrastructure if accidental-dismissal protection is introduced; avoid a second conflicting confirmation mechanism.

Use dynamic viewport sizing for browser chrome, then validate the visual viewport and focused field when the keyboard opens. `100dvh` alone is not a guarantee that the software keyboard will be accounted for. [MDN viewport behavior](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/CSSOM_view/Viewport_concepts).

## 7. Tables: explicit migration ledger

All operational row lists receive the identifier-card/detail-popup pattern. Nested tables are classified by purpose; interactive field matrices become stacked field groups, while printed document tables retain paper geometry.

| Pages/components | Collapsed identifier | Details and additional work |
| --- | --- | --- |
| `service-contracts.vue` | `contract_number` | Add a contract View action; current actions are Print and Extend. Show applicant and service/payment information in details; case navigation remains a separate action |
| `contract_list.vue`, employee/other wrappers, cancelled contracts | `contract_number` | Reuse existing FullDetails field configuration; retain cancellation, print and document versions |
| `income.vue` | `receipt_number` | Reuse income details; retain standalone/service-linked payments, attachments, overrides and receipt printing |
| `expenses.vue` | Existing expense ID, formatted as an expense reference | The current typed API has `id` and no `expense_number`; reuse details and attachments without inventing a new backend number |
| `asset.vue` | `serial_number` | Reuse details; show allocation history, office, quantity and condition in the popup |
| `visitor.vue` | Name | Use existing CRM relationship information as the View destination; retain contact menu and conversion actions |
| `all_customers.vue` | Name | Add a read-only applicant details action; keep edit/delete and case navigation separate |
| `applicants.vue` | Name | Replace raw table with shared list contract. Card opens a read-only case-summary popup; “Open case” remains explicit within it |
| `staff_list.vue` and `hr.vue` | Name | Reuse details; migrate nested leave table too; do not expose HR edit fields to read-only users |
| `staff_salary.vue` | Payslip number for issued slips; employee + month for period balances | Migrate all three table declarations by purpose; retain calculation preview, issue/pay actions and A5 output |
| `timesheet.vue` | Employee + date | Raw attendance grid needs a read-only attendance detail dialog; include report access and permitted checkout correction |
| `paid_time_offs.vue`, MyPaidTimeOffDialog, staff nested leave list | Existing request identifier, or employee + date range | Retain request/status/detail and approve/reject policies; printed list is independent |
| `bonuslist.vue`, BonusRulesDialog | Employee for bonus rows; existing bonus identity/date for history | Replace raw record tables; stack calculation inputs; preserve calculation values and approval actions |
| `pending-transactions.vue` | Existing transaction identifier, or type + counterparty | Add read-only details; actions for approval/rejection remain explicit with existing rules |
| `recurring-events.vue` | Name | Existing event details and occurrence history become responsive; execution and skip are separate actions |
| `reports.vue` | Explicit primary field per report section | Add report-row details and mobile sort; retain current report calculations, pagination, export and filters |
| `educational-reports.vue` | Explicit label per report type | Per-type field registry; rows stay inspectable when dynamic columns differ |
| `educational-dashboard.vue` | Service name / employee name | Sales and employee breakdown rows use the same card/detail pattern; preserve KPI context |
| `educational-master-data.vue`, `applicant-stages.vue` | Name | Convert raw table; add read-only details instead of making taps edit configuration |
| `audits.vue` | Record identity / resource label | Replace inline expansion with the existing appropriate record/event detail viewer; migrate nested change tables; remove shared-card CSS duplication |
| `setup/users.vue` | Display name | Reuse details; invite, role assignment, reset/deletion keep separate actions |
| `setup/positions.vue` | Title | Reuse details and hierarchy information |
| `setup/departments.vue` | Name | Add read-only details; manager and supervisory rules stay intact |
| `DirectoryCrudPage.vue` → marketers/offices | Name | Reuse details, table/filter contract; include office map and marketer relations appropriately |
| `setup/roles.vue` | Existing role cards remain cards | Permission matrix becomes labeled grouped controls; no multi-column phone matrix |
| `options/currencies.vue` | Currency code | Replace raw table; keep base currency, rate and decimal-place behavior |
| `options_hr.vue`, `options_finance.vue`, `options_contract.vue` | Option name | Reuse FullDetails; preserve destinations |
| `contract/policies.vue` | Template name | Read-only template/version details first; editor is explicit. Version table becomes cards |
| `contract/policy_library.vue` | Policy title/name | Add read-only policy details; editor is separate |
| `applicant-portal.vue` university application table | University name | Read-only application popup; retain portal permissions |
| Account work history | Session date | Card opens the applicable session/report details; missing report state stays clear |
| Account deactivation requests | Requester identity | Read-only request details; approve/reject separate |
| Account notification preference matrix | Event name | Stack labeled channel toggles; this is an editable matrix, not a row-View list |
| TaskSettingsView board/configuration table | Board name | Read-only configuration details, with edit/delete explicit |
| PrintableListReport and document-template tables | Paper layout | Responsive screen host only; print remains a proper table |

Confirm identifier keys against each current API contract during migration. Do not introduce invented identifiers into backend records. Composite labels disambiguate repeated names/dates; the card still displays one identity value.

Dynamic reports need a frontend presentation registry keyed by report type and section, with primary label, formatter, and allowed detail fields. Reuse existing row/column data. No backend response shape changes are needed for responsiveness; if details truly need additional data, normalize it in the API layer, never in the page.

## 8. All remaining frontend surfaces

### Shell, navigation, search and account menus

Affected: `App.vue`, `main.ts`, `layouts/*`, `layouts/components/*`, `@layouts/components/*`, `@layouts/styles/*`, configuration stores, navigation definitions, `@core/components/AppBarSearch.vue`, `I18n.vue`, `Notifications.vue`, `Shortcuts.vue`, `ThemeSwitcher.vue`, `TheCustomizer.vue`, `ScrollToTop.vue`, `HeaderPageGuide.vue` and `usePageGuide.ts`.

On phones keep navigation, search, notifications and profile immediately reachable. Theme, locale, audit logs, layout preference and fullscreen can move into a labeled overflow menu; they must remain available. Expose the workday state on home. Avoid squeezing seven icon buttons into a 300px header. Profile and notification popovers must fit the host viewport, including long names and unread badges. Replace click-only avatar/menu triggers with keyboard-accessible controls where needed.

Both vertical and horizontal navigation preferences and the applicant portal must pass the same tests. Keep the sidebar dismissible, restore focus, and avoid a desktop rail consuming mobile content width. Portal branding wraps or simplifies before pushing controls offscreen. Footer content wraps and cannot have a fixed height that clips translated text. Search results retain full actionable destinations; guide popovers reposition within the viewport and work by tap.

### Forms, dialogs, menus and feedback

Affected: every `components/forms/*`, `ContractForm.vue`, `ApplicantServiceFields.vue`, `UserAccountSelect.vue`, ERP fields/summary, `@core/components/app-form-elements/*`, all custom and legacy `components/dialogs/*`, confirmation/warning/error/loading components, and each inline page dialog.

Adopt the shared dialog variants by purpose. Remove desktop-only minimum sizes and exterior close buttons that leave the viewport. Give long forms one scrollable region with a fixed flex/grid header/footer; do not rely on hidden scrollbars as the only overflow strategy. Preserve the browser's normal scroll and zoom behavior.

Use single-column form grids below 500px. At wider sizes add columns only when labels, controls and error text fit. Normalize nested card padding, flex children with `min-inline-size: 0`, wrapping action labels, optional help, and field descriptions. Keep required labels and errors visible. Never hide validation to make a form look smaller.

DataTableFilterMenu currently has a 280px minimum width, leaving little room in a 300px screen. Use a phone filter sheet with Apply/Reset/Close and a clear active count; tablet menus are bounded by available space. On a 300px phone, ErpDateRange stacks date fields rather than insisting on two columns. Preserve any page's existing applied-versus-immediate filter semantics.

AppDateTimePicker remains in body popup mode in dialogs, retains the zero-offset `translateY(0) scale(0.86)` transform and high z-index, and needs positioning tests against all screen edges in RTL/LTR. Numeric inputs get suitable `inputmode`/precision without changing values. Upload pickers and autocomplete menus fit the visual viewport. Toasts wrap and respect safe areas, long translated errors, and stacked notifications.

### Dashboards and non-table lists

Affected: `home.vue`, educational-dashboard, search, notifications, announcements, support, recurring summaries, `DataTablePageKpis.vue`, statistic cards, chart wrappers/configuration and `AppPricing.vue` where used.

Keep the most useful summary and the main action near the top. Avoid three tall decorative KPI cards consuming the entire first phone screen: use compact text/value tiles, single column at 300px and two columns only where labels fit. Do not hide important metrics; allow secondary context to expand.

Charts resize from their host, use bounded legends, and expose textual summaries for values that would otherwise require hover. Filter/period controls wrap or use a select/menu. Notification/announcement rows wrap titles and metadata; their toggles must not impose max-content width on the page. Support ticket forms, file upload, history and status remain usable with the keyboard open.

### Applicant and CRM workspaces

Affected: applicant-case, applicant-portal, applicant-pipeline, applicants/all-customers/visitor, CRM relationship details, ContactActionMenu, ApplicantSummary and all six case dialogs.

Stack summary panels and timelines. Put the key case action in a compact header; move secondary actions into a menu. Document upload controls cannot retain inline 180/220px minima inside a tight row. Attachments need wrapping filenames, clear download/delete buttons and full progress/error visibility. Popup relation grids and fixed 130px counterparty labels become vertical label/value groups when narrow.

On phones, pipeline shows a stage selector and the selected stage's applicants, with stage counts and a clear next/previous stage affordance. Preserve the desktop board at larger widths. Provide “Move to stage” as an explicit action using the same allowed-transition checks as drag/drop. Do not make touch dragging the only way to move an applicant.

### Tasks and legacy Kanban

Affected: `tasks.vue`, `components/task-manager/**/*`, `stores/useTaskStore.ts` only where UI state requires it, `pages/kanban/*`, related task types and helpers.

TaskAppShell uses a compact view selector on phones; tablet tabs stay bounded. Kanban uses board/column selection or contained swipe navigation with a visible column label; no uncontrolled page-wide board. Provide Move task/status actions alongside desktop dragging. Todo Matrix becomes stacked task/section panels, retaining completion and subtask controls.

Calendar opens in a readable agenda/list view on phones, with date navigation and task editing accessible by tap. The current default is `dayGridMonth`, with `listMonth` available; switch the phone presentation while preserving its date-span and update rules. Gantt has a mobile chronological task list with start/end/status and editing in details; a deliberate Timeline mode may retain contained horizontal scrolling. Date editing is the accessible alternative to bar dragging.

TaskDetailDrawer is currently a dialog and should use the long-form dialog pattern. TaskCommentsDrawer uses full available width below its desktop size and a keyboard-aware composer. Results, comments, attachments and HTML previews wrap. Legacy `pages/kanban/*` receive shared responsive primitives and dedicated component checks if unreachable; do not silently delete them during this project.

### Settings, account and administration

Affected: setup pages, OfficeSettingsPanel/OfficeGeofenceMap, account-settings page and all five account views, DirectoryCrudPage, permissions and role forms.

Settings section navigation becomes a select or compact bounded tabs on phones. Roles/permissions stack by group with clear select-all controls. Office map is contained, resizes after tab/dialog changes, and has coordinate/radius inputs as a precise alternative to marker dragging. Appearance controls retain all text-size options. Work history and device/security lists use the same layout standards.

Letterhead artwork safe-area editing retains existing percentage coordinates. Keep the screen preview within its host, offer numeric position/size controls, and test pointer/touch handles. Changing preview size must not change saved paper coordinates. Avoid accidental drag while the user tries to scroll.

### Authentication

Affected: login, forgot/reset/create password, verify email, their form components, blank layout, ErrorHeader and loading/error states.

Bound the card to available width, reduce decoration and nested padding, let long descriptions wrap, and make password/email forms usable in landscape and with a keyboard. Keep autofill, focus, validation, recovery links, loading and password visibility controls intact. Replace inappropriate fixed viewport-height assumptions with a layout that can scroll on short screens.

### Documents, editors and printing

Affected: `pages/templates/*`, `ContractContentEditor`, TiptapEditor/ProductDescriptionEditor, ContractPrintHeader/CounterpartyCard/ContractDocumentPrinter, ReceiptPrinter, PrintableListReport, contract extensions, print composables and print utilities.

Screen previews use a contained preview host with Fit width/Actual size and explicit zoom if needed. A full-page document can be visually scaled inside that host because it represents fixed paper; reserve unscaled paper geometry for the printable DOM. Surrounding controls and print options still reflow normally. Do not shrink currency text on actual paper to satisfy phone sizing.

Editor toolbars use wrapping groups and a More menu; placeholder/policy pickers become bounded sheets. Preserve caret/selection when opening a picker. Embedded rich-text tables can scroll inside the editor, with a visible affordance. Images fit the editor host. Long contracts and policies remain editable on mobile, not merely previewable.

Screen responsiveness never injects persistent `@page`, changes document numbers/snapshots, or broadens global print selectors. Use `printDocument.ts`, the relevant print-mode body class, A5 for receipts/payslips, and A4 for contracts. Reset print ancestors; break only between non-final pages. Test route visitation in different orders before each print job.

### Non-visual and generated files

The inventory assigns API contracts, authorization, stores, types, extensions, localization and utilities as supporting dependencies. They are reviewed for UI-state or formatting changes but do not need artificial edits merely to touch every file. API normalization stays under `resources/ts/api`; no business/API schema changes are implied by viewport changes.

Update all existing locale catalogues for new visible strings and audit the localization pipeline for any supported Arabic locale supplied outside this directory. The current directory has English, Persian and Pashto catalogues; do not invent an existing Arabic catalogue. Test translated script/RTL behavior and long strings.

Icon CSS is generated. Reference new icons statically or update the allowlist for dynamic names, then run the icon generator. Never hand-edit `plugins/iconify/icons.css`. Type declarations and package files remain supporting configuration unless a concrete implementation requires changes.

## 9. Implementation phases and completion gates

Implement in reviewable increments. Each phase includes desktop verification and relevant print verification, not a final testing-only phase after every page has changed.

| Phase | Deliverable | Completion gate |
| --- | --- | --- |
| 0: Baseline | Working preview, deterministic test records/roles, route and overlay checklist, baseline screenshots | Real shell plus login/home/workday/contracts/income/applicant/tasks rendered at phone/tablet/desktop; no visual claim based only on source scan |
| 1: Foundations | Tokens, layout composable, responsive dialog, typed table/View contract, extracted details viewer | Client/server table prototype passes data/state/permissions tests; no duplicate viewport fetches; global CSS conflict resolved |
| 2: Daily workflow and shell | Start/end dialogs, home entry point, all three shells, menus, filter/pagination primitives | Employee can start, fill/end report, retry an error and navigate on 300px; keyboard/RTL/theme checks pass |
| 3: Core records | Service/general/cancelled contracts, income, expenses, applicants/customers/visitor, case and portal | Every list card opens read-only details; forms and payments work; A4/A5 outputs verified |
| 4: Operations | Staff, payroll, attendance, leave, bonuses, assets, pending transactions, recurring events | All nested lists and approval/issue/correction flows pass mobile and tablet checks |
| 5: Complex workspaces | Task views, pipeline, reports, dashboards, document/policy editors | Every drag/hover-dependent task has a tap alternative; dynamic rows have explicit details; editors remain functional |
| 6: Administration and remaining UI | Settings, office/maps/artwork, master data/stages, roles/users/options, account, auth/support/notifications/guides, legacy/demo components | Every inventory row has a recorded disposition; every reachable UI has route/component evidence |
| 7: Release gate | Full route sweep, visual review, real-device checks, performance and print-order regressions | Acceptance criteria below satisfied; no unresolved critical/mobile workflow failures |

Phases 3–6 may overlap by feature once shared contracts are stable. Do not estimate completion from Vue file count: large HR/payroll/settings pages, dynamic reports, editors, maps, and printing carry disproportionate work. Re-estimate after the workday and table pilot; allocate roughly a quarter of the effort to visual/interaction verification and regressions rather than treating it as a small cleanup.

Keep existing user changes intact. This working tree already contains substantial unrelated edits; avoid broad formatting, generation, mass replacement or reverting files during migration.

## 10. Validation plan

### Coverage matrix

Automate a light route sweep at 300, 390, 768, 900, 1000 and 1440px. For shared boundaries use 499/500, 899/900, 999/1000, and the existing navigation/framework edges at 599/600, 959/960 and 1279/1280. Check 2000px for desktop regressions and a short landscape viewport around 844×390.

Run the core workflows in English LTR/light and Persian RTL/dark, then invert themes and check Pashto RTL. Cover each current UI-scale preference. Use realistic long names, identifiers, currencies, validation messages, textareas, empty states, large result counts, expired sessions and slow/error responses. Use read-only and action-capable staff roles, manager/admin roles where required, and applicant portal access.

Use Playwright CLI for discovery, snapshots and focused interaction, then put repeatable meaningful checks in a `scripts/mobile-responsive-regression.mjs` harness following existing project scripts. Keep authenticated route sweeps and mock-component previews separate in test output. Use test-only fixtures or isolated accounts for mutations; never submit workday/payment/approval actions on a real employee merely to inspect layout.

### Assertions that demonstrate usability

- Document scroll width does not exceed viewport width beyond a 1–2px rounding allowance. Also inspect each overlay/control; clipping with `overflow: hidden` is not a passing fix.
- Cards show exactly the configured business identity; card tap and View open the same record once. Edit/delete/selection/menu taps cannot accidentally open details or submit changes.
- Client and server filters/sort/pagination/selection produce the same records on both layouts. Resize does not lose selection or drafts, duplicate requests, or leave a stale record in details.
- Keyboard Tab, Enter/Space, Escape and focus return work. Dialogs trap focus appropriately, have an accessible title, and expose complete fields/errors. Primary controls have the agreed effective tap area.
- Headers, close buttons, footer actions, menus and autocomplete/date pickers remain within available width. Long action labels do not overlap. One intended scrolling region per overlay.
- End-day draft survives dismissal, failed submission and resize. Attendance-session changes reset the right draft; validation and all workday status variants remain correct.
- Phone layouts expose sorting and selection even when table headers are absent. Empty/loading/error states appear in the correct place and remain actionable.
- Touch alternatives successfully move a task/applicant or change a date, using the same rules as desktop actions.
- Print output has the correct A4/A5 dimensions, readable values and no extra blank first/trailing sheet. Visit settings, contracts, payroll and income in different orders before generating each document.

Use real iOS Safari and Android Chrome for keyboard, native file/date inputs, safe areas, browser chrome and touch behavior. A resized desktop Chromium viewport cannot prove those behaviors. Playwright WebKit is an additional automated signal, not a substitute for iOS-device verification.

### Existing checks to retain

- `npm run typecheck` only; retain `noEmit: true`.
- `npm run build:icons` after icon changes; warnings for missing referenced icons are failures.
- `npm run i18n:check` and `npm run i18n:test` after locale changes.
- Appropriate existing attendance-workday, income-payment, service-contract, educational-ui and receipt-print regression scripts; browser flags only in a working browser environment.
- Run a production build before release to check lazy-route imports and responsive style loading. Avoid the existing auto-fixing lint script for a blanket repository pass with unrelated work in progress; check touched files deliberately.

Do not create tests that merely assert a CSS class exists. Test the record opened, the action reached, layout bounds, data requests, draft retained, and document produced.

## 11. Acceptance criteria

The migration is complete only when:

1. Every file in the inventory has a disposition: direct migration, shared responsive behavior verified, supporting code reviewed, generated artifact regenerated, or unreachable legacy/demo component checked in isolation. Every live static route and its dialogs/nested surfaces has recorded evidence.
2. No ordinary page or form requires sideways page scrolling at 300px. Specialized timeline/map/editor/document views may use deliberate contained pan/scroll/zoom, with clear controls and usable alternatives.
3. All operational tables use one-identity cards below 1000px, and each card opens the corresponding read-only details popup. No card click silently performs edit, print, approval or deletion.
4. Start/end day are discoverable, readable, keyboard/touch usable, and preserve the current workday rules and draft/session behavior.
5. Responsive density approaches the requested 20% reduction in spacing/decorative sizing at the tightest widths while field text and tap targets remain usable.
6. Tablet portrait/landscape and the 900–999px transition work deliberately. Existing 1000–2000px desktop layouts have no material regression.
7. Dark/light, RTL/LTR, current locale catalogues, long data, user text-size preferences, and zoom pass the agreed checks.
8. Printed receipts/payslips remain A5 and contracts remain A4, with stable snapshots/numbers and namespaced printing.

The first implementation slice should deliver the shared responsive dialog and list contracts, then migrate the workday popups plus one service-contract list. That pilot establishes the visual and behavioral pattern the rest of the inventory must follow.

## 12. Reference guidance

- Vuetify display behavior should be checked against the installed version and its documented global/component mobile breakpoints: [Vuetify display documentation source](https://github.com/vuetifyjs/vuetify/blob/master/packages/docs/src/pages/en/features/display-and-platform.md). Product breakpoints in this plan do not automatically replace framework grid thresholds.
- Reflow should support 320 CSS pixels under WCAG guidance; this project deliberately targets the tighter 300px requirement as well: [W3C Reflow](https://www.w3.org/WAI/WCAG21/Understanding/reflow).
- Dynamic viewport units distinguish browser viewport sizes, but keyboard handling still needs focused-field/visual-viewport checks: [MDN CSS length units](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/length), [MDN viewport concepts](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/CSSOM_view/Viewport_concepts).
