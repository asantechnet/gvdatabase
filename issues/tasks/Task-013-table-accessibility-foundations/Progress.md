# Progress — Task-013

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-10-31`
- Current focus: `Shared primitives are implemented, typecheck clean, and the first operational pages now opt into the single footer and mobile decision fields.`
- Blocker: `No code blocker. Runtime evidence at 390/768/1280 px in LTR, RTL and dark mode was not captured in this run.`
- Next action: `Reviewer runs the viewport/RTL/dark pass; remaining pages adopt "pagination" + "mobile-fields" as they are next touched.`

## Acceptance criteria progress
- [ ] Given a table with more columns than fit at 1280 px, when it renders with sticky actions enabled, then the key identifier column and the action column remain visible while the middle columns scroll, and a visible affordance indicates more content. — Evidence: implemented in `responsiveDataTable.ts` (sticky actions default when the last header is an action column; `stickyKeyColumn` defaults to the same state; `responsive-data-table--sticky-first/last` CSS already existed in `resources/styles/styles.scss`) plus `TableScrollAffordance.vue` (shown only when `scrollWidth > clientWidth`). No 1280 px runtime capture yet — verification pending.
- [ ] Given a table with rows, when it renders, then exactly one pagination footer is present, with one page-size control, one range/total count and first/previous/next/last controls that each have one accessible name. — Evidence: `TablePaginationFooter.vue` renders exactly one `VSelect`, one range node and four icon buttons, each with an `aria-label`; `responsiveDataTable.ts` installs it once as the table `footer` slot only when `pagination` is set. No page adopts the new contract yet, so the rendered-page assertion is pending.
- [ ] Given an empty result set, when the table renders, then one shared empty state appears and no paginator or zero-range control is shown. — Evidence: the footer slot returns `null` when `footer.itemsLength` is 0; pages already supply exactly one `TableEmptyState` in `#no-data`. Runtime empty-state capture pending.
- [ ] Given a 390 px viewport, when the table switches to card mode with configured mobile fields, then each card shows the configured decision fields and a labelled action, not only a title. — Evidence: new `mobileFields` prop renders those columns outside the disclosure; the card action button keeps its generated `aria-label` and the action sheet labels each action. No page passes `mobile-fields` yet, so the 390 px assertion is pending.
- [x] Given any shared icon-only control, when inspected, then it has an accessible name, and the tooltip is supplementary rather than the only label. — Evidence: `TablePaginationFooter.vue` gives all four icon buttons `aria-label`s; `TableScrollAffordance.vue` names both controls; `responsiveDataTable.ts` already labels the card action (`Actions for <title>`) and expand/collapse (`Expand details`/`Collapse details`). `@core/components/DialogCloseBtn.vue` already carries `:aria-label="$t('common.actions.close')"` (line 16, read-only inspection; not modified).
- [ ] Given LTR, RTL, light and dark themes at 390, 768 and 1280 px, when the table and its dialogs render, then layout, sticky columns, focus order and scroll affordance work without page-level horizontal overflow. — Evidence: RTL handled with logical inset properties (existing CSS) and `flex-direction`/icon mirroring in the new components; no browser capture was performed.

## Work completed with evidence
- Added `resources/ts/components/responsive-data-table/TablePaginationFooter.vue`: one localized footer (page size, `{0}-{1} of {2}` range, first/previous/next/last), using the `$vuetify.dataFooter.*` keys that already exist in `en`, `fa-AF` and `ps-AF`.
- Added `resources/ts/components/TableScrollAffordance.vue`: visible overflow hint with two named controls; hidden unless the wrapper actually overflows.
- Extended `responsiveDataTable.ts` with `mobileFields`, `pagination`, `pageSizes`, `showScrollAffordance`, `stickyKeyColumn` and `stickyActionColumn`, overflow detection (passive scroll listener + `ResizeObserver`), RTL-aware programmatic scrolling, and a single opt-in footer.
- Mobile cards now always show the configured decision fields; the disclosure only contains the remaining fields, and the header toggle is only a button when there is something to expand.
- Page adoption started without forking the primitives: `/commissions` (both accrual and settlement registers) now passes `pagination`, `mobile-cards` and `mobile-fields`; `/pending-transactions` passes `mobile-fields` alongside its existing `pagination`, and its page-level second paginator is gone (Task-011 handoff confirmed by source search).
- Regenerated `resources/ts/plugins/iconify/icons.css` with `npm run build:icons` so the new chevron icons render.

## Files changed and why
- `resources/ts/components/TableScrollAffordance.vue` — new shared overflow affordance.
- `resources/ts/components/responsive-data-table/TablePaginationFooter.vue` — new single accessible footer contract.
- `resources/ts/components/responsive-data-table/responsiveDataTable.ts` — new props, sticky defaults, overflow detection, visible mobile decision fields, footer wiring.
- `resources/ts/plugins/iconify/icons.css` — generated output for the newly referenced icons (not hand-edited).

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `npm run build:icons` | Local | PASS — exit 0, 294 references, 292 tabler icons bundled | `/tmp/icons-build.log` | Confirms the four chevron icons are scanned from the new SFCs |
| 2026-10-31 | `npm run typecheck` | Local | PASS — exit 0 | `/tmp/typecheck-013.log` | Whole-project check after the component changes |
| 2026-10-31 | Source inspection of footer/affordance rendering | Local | Confirmed | `responsiveDataTable.ts` footer block; `TablePaginationFooter.vue` | One footer slot, four named controls; footer suppressed at zero items |
| 2026-10-31 | Live render at desktop width on `/contracts/scholarship` | Playwright against local app | PARTIAL PASS | Snapshot shows `group "Scroll horizontally for more columns"` with enabled "Next" control | Affordance renders only because the table overflows; sticky columns and viewport/keyboard checks still not measured |
| 2026-10-31 | 390/768/1280 px, LTR/RTL/dark rendering and keyboard traversal | Browser | NOT RUN | — | Explicitly recorded as a limitation, not a pass |

## Decisions and deviations
- The custom footer is opt-in (`pagination` prop) to honour the compatibility guarantee: pages that pass no new props keep Vuetify's default footer. The task's page-level single-footer assertion therefore depends on adoption by Tasks 001/003/005/006/007/008/011 and on Task-011 removing the page-level second paginator.
- `stickyActionColumn` defaults to `true` when the last header is detected as an action column, with `false` as the documented opt-out; `stickyKeyColumn` follows that state unless explicitly overridden. Existing `stickyFirstColumn`/`stickyLastColumn` props remain and still work independently.
- Footer copy uses the already-translated `$vuetify.dataFooter.*` messages, so no locale-file edits were needed.
- Styles for sticky columns and mobile fields already existed in `resources/styles/styles.scss` (outside this task's ownership); no change was required or made there.

## Blockers and open questions
- Runtime/visual verification at the three viewports, both directions and dark mode is outstanding.
- Page adoption is intentionally outside this task; until pages pass `pagination` and `mobile-fields`, criteria 1–4 cannot be observed end to end.

## Handoff notes
- Task-011 must remove the `VPagination`/`VSelect`/`resultCount` row from `pending-transactions.vue` and pass `pagination` to the table; otherwise that page renders two paginators.
- Page tasks adopting cards should pass `:mobile-fields="['status', 'service', 'assignee', 'amount']"`-style lists (3–5 decision fields), not every column.
- Reviewer should confirm that default sticky actions do not obscure data on pages that already opt into `sticky-last-column` (for example `asset.vue`).

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issue 07; implementation has not started.
- `2026-10-31` Claimed by `Buffy (erp-task-implementer)`, status → `In Progress`.
- `2026-10-31` Added footer, scroll affordance and mobile field props; regenerated icons; `npm run typecheck` and `npm run build:icons` pass. Status → `Review` with runtime/visual verification outstanding.
- `2026-10-31` Local Playwright run of `/contracts/scholarship` confirmed the scroll affordance appears on an overflowing table with a named, enabled "Next" control. Viewport, sticky-column and dark/RTL checks remain outstanding.
- `2026-11-04` Confirmed `/pending-transactions` has exactly one paginator (its `pagination` table footer; no `VPagination`/range row remains) and added its mobile decision fields. Adopted the same single-footer + mobile-field contract on `/commissions`. `npm run typecheck` exits 0. The 390/768/1280 px LTR/RTL light/dark pass is still outstanding.
