# Task Task-013 — Fix the shared table and action accessibility foundations

## Metadata
- Task ID: `Task-013`
- Title: `Fix the shared table and action accessibility foundations`
- Initial status: `Ready`
- Type: `Frontend`
- Priority: `P2`
- Owner role: `Frontend design-system implementer`
- Estimated size: `L`
- Source plan/version: `issues/review/issues/07-tables-and-accessible-actions/issue.md`; `issues/review/REPORT.md` §4 P2-6; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-07`, `ISSUE-13-TABLE`

## Objective
One shared data-table contract keeps key columns and row actions discoverable at 1280 px, exposes usable mobile cards, names every icon control, and guarantees a single paginator.

## Context and problem
Registers at 1280 px hide status and action columns behind horizontal scrolling with no affordance. Row actions are icon-only with tooltips but no accessible names. Mobile application cards show only a title and a menu. `ResponsiveDataTableServer` has no sticky key/action configuration, and `pending-transactions.vue` adds a second paginator on top of the table's own footer.

A tooltip is not an accessible name. The shared primitives must make the correct pattern the default, so page tasks do not re-solve it screen by screen.

## Scope
### Included
- Add a shared table configuration for sticky key columns and a sticky last action column, with sensible defaults and an opt-out.
- Add a visible horizontal-scroll affordance when content overflows, without a permanent scrollbar that dominates the design.
- Add a single, configurable pagination/footer contract: page, page size, total, range, first/prev/next/last and one accessible name per control.
- Add mobile card configuration that exposes 3-5 decision fields (for example stage, service, assignee, status, amount) plus a labelled action, not just a title.
- Provide a named icon-button pattern and apply it to shared dialog close buttons and row actions; ensure tooltips remain as visual hints.
- Support LTR/RTL, light/dark, 390/768/1280 px and keyboard focus order.
- Document the props and the required accessible-name rule for page authors.

### Excluded
- Editing individual domain pages; Tasks 001, 003, 005, 006, 007, 008 and 011 adopt the primitives.
- Replacing Vuetify data tables wholesale.
- Server-side API or pagination changes.
- Login form labels beyond the password-toggle naming (Task-009 owns `LoginForm.vue`).

## Preconditions and assumptions
- The shared table directory currently contains `ResponsiveDataTableServer.ts` and its `.vue` wrapper; the implementation must extend those, not fork a third table.
- Assumption to validate: Vuetify's `VDataTableServer` exposes enough slots/props for a sticky last column and a custom footer. If not, add a wrapper-level CSS/slot solution and document the limitation.
- Existing pages pass `sticky-last-column` selectively; the new default must not break pages that already rely on it.
- There is no dedicated accessibility test harness; verification uses DOM inspection plus manual keyboard traversal.

## Dependencies
- `None` — this is the shared foundation the page tasks depend on.

## Contracts and boundaries
- Input/output: the table component keeps its current props and adds optional `stickyKeyColumn`, `stickyActionColumn`, `mobileFields`, `showScrollAffordance` and `pagination`-related props.
- Compatibility guarantee: pages that pass no new props keep their current rendering except for the accessibility fixes.
- Error behavior: a missing mobile field renders a neutral dash, never `undefined`; an empty table shows one empty state and no pagination.
- Permission/data scope: none; the table only renders what the page passes.
- Integration seam: page tasks configure fields and handlers; they do not clone pagination or add their own aria labels to shared controls.
- Files this task must not modify: any file under `resources/ts/pages`, `resources/ts/views`, `resources/ts/api`, `resources/ts/utils/localization.ts`.

## Exclusive file ownership
- `resources/ts/components/TableEmptyState.vue` — shared empty state used by all lists.
- `resources/ts/components/TableLoadingSkeleton.vue` — shared skeleton used by all lists.
- `resources/ts/components/TableScrollAffordance.vue` — new visible overflow affordance.

Forward ownership: `resources/ts/components/responsive-data-table` moved to Task-035 on 2026-11-07 for the audit-rail integration; this task retains the empty/loading/scroll primitives.

## Acceptance criteria
- [ ] Given a table with more columns than fit at 1280 px, when it renders with sticky actions enabled, then the key identifier column and the action column remain visible while the middle columns scroll, and a visible affordance indicates more content.
- [ ] Given a table with rows, when it renders, then exactly one pagination footer is present, with one page-size control, one range/total count and first/previous/next/last controls that each have one accessible name.
- [ ] Given an empty result set, when the table renders, then one shared empty state appears and no paginator or zero-range control is shown.
- [ ] Given a 390 px viewport, when the table switches to card mode with configured mobile fields, then each card shows the configured decision fields and a labelled action, not only a title.
- [ ] Given any shared icon-only control, when inspected, then it has an accessible name, and the tooltip is supplementary rather than the only label.
- [ ] Given LTR, RTL, light and dark themes at 390, 768 and 1280 px, when the table and its dialogs render, then layout, sticky columns, focus order and scroll affordance work without page-level horizontal overflow.

## Verification plan
- Automated: `npm run typecheck`; add a component test only if the repository already has a component-test harness. Run a source check that no page passes a duplicate footer or an unlabeled shared icon action.
- Manual: open one wide table, one narrow mobile card table and one empty table; tab through pagination and actions; inspect accessible names in the browser accessibility tree; switch RTL and dark mode.
- Fixtures: reuse existing list pages with seeded data; no new data required for the shared component check.
- Regression: verify every page that already uses the table still paginates, sorts, selects rows and renders its empty state; check the payment-requests page after Task-011 adopts the single footer.
- Evidence: screenshots at each viewport and direction, accessibility-tree output, typecheck result.

## Implementation notes
Make the action column sticky by default where a page supplies actions; allow an opt-out for very wide tables. Do not hide the scrollbar entirely — either show it on hover/focus or render an explicit affordance. For named icons, add `aria-label` from the same translation key as the tooltip. For mobile cards, require an explicit field list rather than deriving from all columns; the page author chooses the decision fields.

## Risks and rollback
- Risk: sticky columns can overlap in RTL. Mitigation: test both directions and use logical inset properties.
- Risk: changing the footer breaks pages that relied on the old slot. Mitigation: keep the existing slot names and add the new contract as an additive mode.
- Risk: making actions sticky can obscure data on small screens. Mitigation: use card mode below the breakpoint instead of sticky columns.
- Data migration: none. Rollback restores the previous component files.
- Stop condition: if the underlying Vuetify table cannot support sticky key and action columns together, stop and document the chosen alternative before page tasks adopt it.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
