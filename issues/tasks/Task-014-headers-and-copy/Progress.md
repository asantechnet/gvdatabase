# Progress — Task-014

## Status snapshot
- Status: `Review`
- Owner: `Vue/Vuetify implementer`
- Last updated: `2026-10-31`
- Current focus: `Owned-file implementation is complete and verified; board-card, expense-dialog and brand-mark criteria need files outside this task's exclusive ownership.`
- Blocker: `TaskAppShell/KanbanView/KanbanCard, ExpenseFormDialog and DefaultLayoutWithVerticalNav are not owned by Task-014, so the card metadata, tooltip and save-action slices cannot be completed here.`
- Next action: `Independent review, then reassign the blocked card/dialog/brand slices or widen ownership.`

## Acceptance criteria progress
- [x] Given the tasks and expenses pages, when each opens, then it shows a breadcrumb and H1 through `ErpPageHeader`, and no card repeats the same H1 as a second title. — Evidence: `resources/ts/pages/tasks.vue` and `resources/ts/pages/expenses.vue` now render `ErpPageHeader`; the old expense header and the ledger card title were removed. `ErpPageHeader` gained an optional `description` prop and `tasks`/`finance` breadcrumb sections, with matching `erp.tasks` and `erp.finance` keys in all three locales.
- [x] Given a page with a count, when the count is 1 or greater than 1, then the string is pluralized correctly and no "1 candidates", "1 users" or "1 Active Filters" text remains on the owned pages. — Evidence: expenses uses `expenseRecordCount` with `$t(key, { count: total })`; a vue-i18n runtime check returned `0 expense records`, `1 expense record`, `2 expense records`. Source grep for the three bad strings on the owned pages found none.
- [ ] Given the tasks board at 1280 px, when it renders, then the header states that columns are services, the fifth column is reachable without clipping, and each card exposes its due date and assignee in text. — Evidence: the header description says each column is a service and the tasks page adds horizontal overflow/scrollbar styles. NOT VERIFIED at 1280 px. The card part is blocked: `KanbanCard.vue` (outside ownership) does not render `dueDate` at all and shows assignees as avatar tooltips rather than text.
- [x] Given expenses, when it renders, then each concept has one label and no duplicate stream/ledger naming remains on the page. — Evidence: removed the ledger card's "Outgoing Stream" title and "Spend Ledger" chip; source grep for `outgoingStream|spendLedger` in `expenses.vue` returns no matches.
- [ ] Given a truncated card description on an owned page, when the user hovers or focuses it, then the full text is available without navigating away. — Evidence: BLOCKED BY OWNERSHIP. The truncation is created in `KanbanView.vue` (`comments` cut to 50 characters) and the card is `KanbanCard.vue`; neither is owned by this task.
- [ ] Given dark theme, when the header/brand area renders, then the company brand mark is legible and the exact asset/component used is recorded in `Progress.md`. — Evidence: BLOCKED BY OWNERSHIP / NOT VERIFIED. The app logo source is `resources/images/logo.svg` (a `currentColor` SVG) and it is rendered by `resources/ts/layouts/components/DefaultLayoutWithVerticalNav.vue`; no file in this task's ownership renders the brand mark. No dark-theme visual check was run.
- [ ] Given a destructive action and a save action on the same dialog, when they render, then the primary action is visually dominant, cancel is secondary, and delete is separated and clearly destructive. — Evidence: PARTIAL. The expense delete confirmation now uses a separated destructive flat button with a trash icon and a text cancel. The create/edit save button is inside `ExpenseFormDialog.vue` (outside ownership) and is still `color="error"`, so the save hierarchy could not be corrected here.

## Work completed with evidence
- Added an optional `description` prop to `ErpPageHeader`; all existing adopters are unchanged because the prop defaults to absent. Added empty `tasks` and `finance` sections so the breadcrumb reads `Home / Tasks` and `Home / Finance` without inventing new navigation.
- Added the shared header to `tasks.vue` with copy that states every board column is a service and that the board scrolls sideways.
- Added scoped horizontal-overflow, scrollbar-gutter and thin-scrollbar rules to the tasks board so the fifth service column is reachable.
- Replaced the expenses page's bespoke heading with `ErpPageHeader`, keeping Print as a secondary text action and Record expense as the primary action.
- Pluralized the expenses record count through a new i18n pipe key and removed the duplicated ledger card heading/chip.
- Improved the expense delete confirmation hierarchy: Cancel is text, Delete is a separated flat destructive button with an icon.
- Added `tasksPage`, `erp.tasks`, `erp.finance`, `expenseRecordCount` and `expensePageDescription` keys to English, Dari and Pashto.

## Files changed and why
| File | Change |
|---|---|
| `resources/ts/components/erp/ErpPageHeader.vue` | Optional description, tasks/finance breadcrumb sections. |
| `resources/ts/pages/tasks.vue` | Shared header plus horizontal board access. |
| `resources/ts/pages/expenses.vue` | Shared header, plural count, single ledger label, delete hierarchy. |
| `resources/ts/plugins/i18n/locales/en.json` | New English header, count and description keys. |
| `resources/ts/plugins/i18n/locales/fa-AF.json` | New Dari keys. |
| `resources/ts/plugins/i18n/locales/ps-AF.json` | New Pashto keys. |

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `npm run typecheck` | Local | PASS | Terminal output | Final run after the `$t` call correction. |
| 2026-10-31 | Locale key/JSON check (3 locales) | Local | PASS | Terminal output | `tasksPage`, `erp.tasks/finance`, `expensePageDescription`, `expenseRecordCount` present; JSON parses. |
| 2026-10-31 | vue-i18n plural runtime check | Local Node | PASS | Terminal output | `0 expense records`, `1 expense record`, `2 expense records`. |
| 2026-10-31 | Source grep for `outgoingStream`/`spendLedger` | Local | PASS | Terminal output | No duplicate labels remain in `expenses.vue`. |
| 2026-10-31 | Task validator | Local | PASS | Terminal output | 20 tasks, 0 errors. |
| 2026-10-31 | `git diff --check` | Local | PASS | Terminal output | No whitespace errors. |
| 2026-10-31 | Browser at 1280/768/390, LTR/RTL, light/dark | Browser | NOT RUN | — | No visual or accessibility pass is claimed. |

## Decisions and deviations
- Extended `ErpPageHeader` additively (`description`, two empty sections) instead of forking the header on each page.
- Kept the existing translation keys and added new ones; no adopter keys were renamed.
- Removed the expenses card heading entirely rather than inventing a second name for the same ledger.
- Did not edit `TaskAppShell.vue`, `KanbanView.vue`, `KanbanCard.vue`, `ExpenseFormDialog.vue`, `DefaultLayoutWithVerticalNav.vue` or any responsive-table primitive because they are outside the task's exclusive ownership.
- No server/API, route or permission behaviour changed.

## Blockers and open questions
- Board card due date/assignee text and full-description access require `pages/kanban/KanbanCard.vue` and the `comments` truncation in `KanbanView.vue`; both are outside ownership.
- The save/cancel hierarchy in the expense form requires `components/forms/ExpenseFormDialog.vue`, outside ownership.
- Dark brand legibility requires the layout/branding surface, outside ownership. The existing app logo is `currentColor`, but this is not a substitute for a dark-theme visual check.

## Handoff notes
- Owned changes are ready for review; the task is not complete against all acceptance criteria.
- Suggested follow-up: reassign or widen ownership for `KanbanCard.vue`, `KanbanView.vue`, `ExpenseFormDialog.vue` and the layout brand mark, then run the viewport/direction/theme matrix.
- No browser screenshots or manual viewport evidence were produced.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issue 16; implementation has not started.
- `2026-10-31 Asia/Kabul` Claimed task; begun owned-file implementation.
- `2026-10-31 Asia/Kabul` Implemented header, plural, label and delete-hierarchy changes; typecheck, locale, plural, validator and diff checks passed. Moved to Review with card/dialog/brand slices blocked by ownership.
