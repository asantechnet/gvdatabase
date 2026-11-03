# Progress — Task-014

## Status snapshot
- Status: `Review`
- Owner: `Vue/Vuetify implementer with copywriting review`
- Last updated: `2026-11-03`
- Current focus: `All acceptance criteria are implemented. The only outstanding item is the manual 1280/768/390 px, LTR/RTL, light/dark browser pass.`
- Blocker: `None. The three slices that were blocked by file ownership (board card, expense dialog, brand mark) were completed on 2026-11-03 after ownership was widened.`
- Next action: `Reviewer runs the viewport/direction/theme matrix and confirms the masked brand mark and the 1280 px board.`

## Acceptance criteria progress
- [x] Given the tasks and expenses pages, when each opens, then it shows a breadcrumb and H1 through `ErpPageHeader`, and no card repeats the same H1 as a second title. — Evidence: `resources/ts/pages/tasks.vue` and `resources/ts/pages/expenses.vue` render `ErpPageHeader`; the old expense header and the ledger card title were removed. `ErpPageHeader` gained an optional `description` prop and `tasks`/`finance` breadcrumb sections, with matching `erp.tasks` and `erp.finance` keys in all three locales.
- [x] Given a page with a count, when the count is 1 or greater than 1, then the string is pluralized correctly and no "1 candidates", "1 users" or "1 Active Filters" text remains on the owned pages. — Evidence: expenses uses `expenseRecordCount` with `$t(key, { count: total })`; a vue-i18n runtime check returned `0 expense records`, `1 expense record`, `2 expense records`. Source grep for the three bad strings on the owned pages found none.
- [x] Given the tasks board at 1280 px, when it renders, then the header states that columns are services, the fifth column is reachable without clipping, and each card exposes its due date and assignee in text. — Evidence: the header description states each column is a service and the board scrolls sideways; the scoped styles keep `overflow-x: auto`, `scrollbar-gutter: stable` and a thin scrollbar. `KanbanView.vue` already supplied `dueDate` and `members` in the card payload, and `KanbanCard.vue` now renders both as text: `Due: <localized date>` through `formatActiveDate` and `Assignees: <names joined>`, plus a calendar glyph. The 1280 px visual confirmation is still outstanding; the card-text part is source-verified.
- [x] Given expenses, when it renders, then each concept has one label and no duplicate stream/ledger naming remains on the page. — Evidence: removed the ledger card's "Outgoing Stream" title and "Spend Ledger" chip; source grep for `outgoingStream|spendLedger` in `expenses.vue` returns no matches.
- [x] Given a truncated card description on an owned page, when the user hovers or focuses it, then the full text is available without navigating away. — Evidence: the lossy cut in `KanbanView.vue` (`substring(0, 50) + '...'`) is removed, so the card payload carries the complete plain-text description. `KanbanCard.vue` clamps it to two lines with `-webkit-line-clamp` and attaches `v-tooltip` with the full text; the same element is `tabindex="0"`, so keyboard focus opens the tooltip and carries a visible `:focus-visible` outline. Nothing is lost from the stored description any more.
- [x] Given dark theme, when the header/brand area renders, then the company brand mark is legible and the exact asset/component used is recorded in `Progress.md`. — Evidence: asset `resources/images/logo.svg` (two `currentColor` paths plus two 6%-opacity black shading paths); component `resources/ts/components/erp/AppBrandMark.vue`; adopted by the vertical, horizontal and applicant-portal layouts. An `<img>` cannot inherit `currentColor`, so the SVG painted black and vanished on the dark navigation bar. The packaged mark is now rendered as a CSS mask filled with `currentcolor`, which follows `--v-theme-on-surface` in both themes; `useBranding` exposes `isMonochromeLogo`, and an uploaded logo is never masked, so company colours are preserved. No second, unapproved logo asset was created. Visual confirmation of the dark header is still outstanding.
- [x] Given a destructive action and a save action on the same dialog, when they render, then the primary action is visually dominant, cancel is secondary, and delete is separated and clearly destructive. — Evidence: `ExpenseFormDialog.vue` now renders Save as `primary`/`flat` and Cancel as `secondary`/`text` (previously the save button was `error` red, reading as destructive); its avatar moved from `error` to `primary`. The expense delete confirmation remains a separate dialog with a flat destructive Delete and a text Cancel, and the two flows no longer compete on one surface.

## Work completed with evidence
- Added an optional `description` prop to `ErpPageHeader`; all existing adopters are unchanged because the prop defaults to absent. Added empty `tasks` and `finance` sections so the breadcrumb reads `Home / Tasks` and `Home / Finance` without inventing new navigation.
- Added the shared header to `tasks.vue` with copy that states every board column is a service and that the board scrolls sideways.
- Added scoped horizontal-overflow, scrollbar-gutter and thin-scrollbar rules to the tasks board so the fifth service column is reachable.
- Replaced the expenses page's bespoke heading with `ErpPageHeader`, keeping Print as a secondary text action and Record expense as the primary action.
- Pluralized the expenses record count through a new i18n pipe key and removed the duplicated ledger card heading/chip.
- Improved the expense delete confirmation hierarchy: Cancel is text, Delete is a separated flat destructive button with an icon.
- 2026-11-03: removed the 50-character description truncation from the Kanban card payload; the full plain-text description now reaches the card.
- 2026-11-03: the Kanban card shows the due date (locale-formatted) and the assignee names in text, and clamps the description to two lines with the complete text on hover and keyboard focus.
- 2026-11-03: `ExpenseFormDialog` uses a primary Save and a text Cancel; the dialog no longer signals a destructive action for a normal save.
- 2026-11-03: added `AppBrandMark.vue` and made the packaged `currentColor` logo render as a theme-coloured mask in all three layouts; uploaded logos still render as images. Added `isMonochromeLogo` to `useBranding`.
- Added `tasksPage`, `erp.tasks`, `erp.finance`, `expenseRecordCount`, `expensePageDescription`, `kanbanview.dueDate` and `kanbanview.assignees` keys to English, Dari and Pashto.

## Files changed and why
| File | Change |
|---|---|
| `resources/ts/components/erp/ErpPageHeader.vue` | Optional description, tasks/finance breadcrumb sections. |
| `resources/ts/pages/tasks.vue` | Shared header plus horizontal board access. |
| `resources/ts/pages/expenses.vue` | Shared header, plural count, single ledger label, delete hierarchy. |
| `resources/ts/components/task-manager/views/KanbanView.vue` | Full plain-text description instead of a 50-character cut. |
| `resources/ts/pages/kanban/KanbanCard.vue` | Due date and assignee text, clamped description with full-text tooltip/focus. |
| `resources/ts/components/forms/ExpenseFormDialog.vue` | Primary Save, secondary Cancel, primary avatar. |
| `resources/ts/components/erp/AppBrandMark.vue` | New theme-aware brand mark for the packaged monochrome logo. |
| `resources/ts/composables/useBranding.ts` | `isMonochromeLogo` flag. |
| `resources/ts/layouts/components/DefaultLayoutWithVerticalNav.vue` | Adopts `AppBrandMark`. |
| `resources/ts/layouts/components/DefaultLayoutWithHorizontalNav.vue` | Adopts `AppBrandMark`. |
| `resources/ts/layouts/components/ApplicantPortalLayout.vue` | Adopts `AppBrandMark`. |
| `resources/ts/plugins/i18n/locales/en.json` | New English header, count, description and card labels. |
| `resources/ts/plugins/i18n/locales/fa-AF.json` | New Dari keys. |
| `resources/ts/plugins/i18n/locales/ps-AF.json` | New Pashto keys. |

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `npm run typecheck` | Local | PASS | Terminal output | Final run after the `$t` call correction. |
| 2026-10-31 | Locale key/JSON check (3 locales) | Local | PASS | Terminal output | `tasksPage`, `erp.tasks/finance`, `expensePageDescription`, `expenseRecordCount` present; JSON parses. |
| 2026-10-31 | vue-i18n plural runtime check | Local Node | PASS | Terminal output | `0 expense records`, `1 expense record`, `2 expense records`. |
| 2026-10-31 | Source grep for `outgoingStream`/`spendLedger` | Local | PASS | Terminal output | No duplicate labels remain in `expenses.vue`. |
| 2026-10-31 | Task validator | Local | PASS | Terminal output | 0 errors. |
| 2026-10-31 | `git diff --check` | Local | PASS | Terminal output | No whitespace errors. |
| 2026-11-03 | `npm run typecheck` (`vue-tsc --noEmit`) | Local | PASS — exit 0 | Terminal output | After the card, dialog, brand-mark and locale edits. |
| 2026-11-03 | `npm run i18n:check` | Local | PASS — exit 0 | Terminal output | 306 source files, 3284 used keys, 4926 English keys; no missing keys. |
| 2026-11-03 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local | PASS — 27 tasks, 0 errors | Terminal output | Ownership widening kept one writer per file. |
| 2026-11-03 | `git diff --check` | Local | PASS — exit 0 | Terminal output | No whitespace errors. |
| 2026-11-03 | Browser at 1280/768/390, LTR/RTL, light/dark | Browser | NOT RUN | — | No visual or accessibility pass is claimed. |

## Decisions and deviations
- Extended `ErpPageHeader` additively (`description`, two empty sections) instead of forking the header on each page.
- Kept the existing translation keys and added new ones; no adopter keys were renamed.
- Removed the expenses card heading entirely rather than inventing a second name for the same ledger.
- Widened ownership on 2026-11-03 to the four card/dialog/brand surfaces rather than shipping around them, because the blocked criteria could not be met inside the original three files. No other task claims those files.
- Chose a CSS-mask rendering over `filter: invert(...)` for the packaged logo: a filter would also distort a coloured upload, while the mask is applied only when the packaged monochrome asset is in use. An uploaded logo remains an untouched `<img>`.
- Kept one asset (`resources/images/logo.svg`) instead of adding an unapproved light variant, matching the task's stop condition for the logo slice.
- No server/API, route or permission behaviour changed.

## Blockers and open questions
- None functional. The visual viewport matrix (1280/768/390 px, LTR/RTL, light/dark) is the only unrecorded evidence; the 1280 px board, the dark header mask and the two-line clamp all need a browser look.
- Uploaded brand logos are shown as-is: if an administrator uploads a dark raster mark, dark theme legibility remains their asset choice. The UI can only guarantee the packaged mark.

## Handoff notes
- `AppBrandMark` must keep the `monochrome` branch for the packaged asset; switching it back to a plain `<img>` reintroduces the black-on-dark logo.
- Any new layout that shows the company logo should adopt `AppBrandMark` rather than embedding `<img :src="logoUrl">`.
- The card description key is `comments` for legacy reasons; it now holds the complete plain-text description, so do not re-truncate it in `KanbanView.vue`.
- Suggested follow-up: run the viewport/direction/theme matrix and capture the 1280 px board and the dark header.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issue 16; implementation has not started.
- `2026-10-31 Asia/Kabul` Claimed task; begun owned-file implementation.
- `2026-10-31 Asia/Kabul` Implemented header, plural, label and delete-hierarchy changes; typecheck, locale, plural, validator and diff checks passed. Moved to Review with card/dialog/brand slices blocked by ownership.
- `2026-11-03 Asia/Kabul` Ownership widened and the three blocked slices implemented: card due-date/assignee text and untruncated description with tooltip, expense dialog action hierarchy, and a theme-aware `AppBrandMark` for the packaged `currentColor` logo in all three layouts. Typecheck, i18n, validator and diff checks pass; visual matrix still outstanding.
