# Employment & Other Contracts Review — 2026-11-10

## Environment

- Local test database and `http://localhost:8000`
- Playwright CLI session: `contract-review-20261110`
- Super admin: `admin@example.test`
- No source code was changed by this review.
- Test records were intentionally left in place unless an action under test deletes them.

## Verdict

Employment Contracts and Other Contracts are **not fully working**:

| ID | Finding | Severity | Status |
| --- | --- | --- | --- |
| `EXT-20261110-001` | Editing a draft contract extension clears `parent_contract_id` and removes `snapshot_data.extension_of` in both registers | **High** | Confirmed |
| `DETAILS-20261110-001` | “View full details” is unreachable from the row Actions menu; the `VListItem` is rendered inside an inert `<template>` at 0×0 | **High** | Confirmed |

## Actions exercised

### Working paths

- **Add Contract** — created an employment contract (`CTR-26-020`) and an Other contract (`CTR-26-022`).
- **Extend contract** — created draft extensions from an active employment contract (`CTR-26-021` from `CTR-26-020`) and from an active Other contract (`CTR-26-023`, then `CTR-26-024`, from `CTR-26-022`).
- **Edit contract** — the dialog opens for draft records and saves successfully. Saving is what triggers `EXT-20261110-001`.
- **Cancel contract** — `CTR-26-023` moved to `cancelled`, `is_cancelled = true`; the row stayed visible with a Cancelled badge.
- **Delete contract** — `CTR-26-024` was deleted from the draft row and removed from the database.
- **Print contract** — opening the print dialog for `CTR-26-022` worked and exposed the A4 cover/letterhead options (`other-print-dialog.png`).
- **Print table** — the toolbar action collected the filtered rows and invoked `window.print` once (print call intercepted; no OS print was produced).
- **Search** — searching `CTR-26-020` returned exactly that row; clearing restored all rows.
- **Extension type/status rules** — only primary, non-cancelled `active`/`completed` records expose Extend; extensions are created as `draft` with `contract_role = extension`.

### Broken path

- **View full details** — the menu item is not usable. Both registers are affected; see `DETAILS-20261110-001`.

## Setup observation (not filed as a defect)

The live database had **no non-employment contract template** and zero Other contracts, so the Other page’s Add Contract dialog initially had no template to select. A test template, `Mutual NDA Review` (id 14), was created through the API to exercise the module.

Creating a template with a blank body through the UI is rejected with `Content: Required.`; the browser snapshot shows this explicit alert. That is form validation, not a contract-action failure, so it is not filed as a bug. Template creation with a body through the UI was not retested.

## Test data

| Contract | Type | State after review |
| --- | --- | --- |
| `CTR-26-020` | Employment, primary | Active |
| `CTR-26-021` | Employment, extension of 020 | Draft; parent link was cleared by an unchanged edit/save |
| `CTR-26-022` | Other, primary (`Mutual NDA Review`) | Active |
| `CTR-26-023` | Other, extension of 022 | Cancelled; parent link was cleared by an unchanged edit/save |
| `CTR-26-024` | Other, extension of 022 | Deleted after the clean before/after extension test |

`Mutual NDA Review` (template id 14) remains in place.

## Verification limits

- Contract printing was checked at the dialog/`window.print` boundary, not as a physical print or PDF.
- “View full details” could not be opened to inspect its contents because the action itself is inert.
- `node scripts/contract-workflow-regression.mjs` still fails at line 51. That assertion expects the literal old print guard `v-if="!item.is_cancelled"`; the current source uses `v-if="!item.is_cancelled && !isEmployeeAudience"` and the employee menu has its own `!item.is_cancelled` guard. The product behavior hides Print for cancelled rows; the script is stale. No product defect is filed for this failure.

## Evidence index

- `EXT-20261110-001/evidence/` — clean Other before/after screenshots and JSON, employment after-edit JSON, current state, print dialog, row menu, cancel/delete state.
- `DETAILS-20261110-001/evidence/` — row menus from both registers.

## Existing regression note

The failing `scripts/contract-workflow-regression.mjs` check is a stale source-pattern assertion, not a failing runtime contract action. It should be updated to the current guard rather than weakening the cancelled-contract protection.
