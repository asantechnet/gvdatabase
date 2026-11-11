# Task Task-005 — Repair the contract details row action

## Metadata
- Task ID: `Task-005`
- Title: `Repair the contract details row action`
- Initial status: `Ready`
- Type: `UI`
- Priority: `P1`
- Owner role: `Vue component implementer`
- Estimated size: `S`
- Source plan/version: `other-contracts-issues/DETAILS-20261110-001/issue.md`, 2026-11-10 audit
- Requirement / behavior IDs: `DETAILS-20261110-001`

## Objective
Restore the primary read-only "View full details" action in the employment and other contract row menus so users can open the details dialog from every contract row.

## Context and problem
The action exists in the component but is wrapped in an extra native `<template>` with no structural directive. The resulting `VListItem` is inert, measures `0 × 0`, and cannot be clicked. The same shared component is mounted with `menu-action` in `contract_list.vue`, so both contract registers are affected. Other pages that use the component may share the defect.

## Scope
### Included
- Remove the inert wrapper so the menu, mobile button, icon button, and dialog render as intended.
- Preserve the component's existing activator modes and dialog behavior.
- Verify the action in both Employment Contracts and Other Contracts.
- Check the component's other consumers for the same wrapper or menu regression.

### Excluded
- Contract detail content, API payloads, permissions, print layout, or contract edit/extension behavior.
- Redesigning the row menu or adding a new details route.
- Fixing unrelated stale regression scripts.

## Preconditions and assumptions
- The detail dialog already works when opened through another activator; this task restores access, not the dialog's business logic.
- `contract_list.vue` continues to pass `menu-action`; no page-level workaround is preferred.
- The component is shared, so the fix must be structural rather than a contract-page-only branch.

## Dependencies
- `None` — no upstream task is required; this is an isolated component repair.

## Contracts and boundaries
- UI contract: the row menu includes a visible, keyboard-focusable "View full details" item that opens the existing dialog.
- API contract: no change to the details endpoint or payload.
- Accessibility contract: the item remains reachable by keyboard and has the existing translated label.
- Files/modules this task must not modify: contract API contracts, contract pages other than the shared component, permissions, and print CSS.

## Exclusive file ownership
- `resources/ts/components/FullDetailsButton.vue` — remove the inert wrapper and preserve activator modes.

## Acceptance criteria
- [ ] Given the Employment Contracts row menu, when it opens, then View full details is visible and opens the details dialog.
- [ ] Given the Other Contracts row menu, when it opens, then the same action is visible and opens the details dialog.
- [ ] Given the component's mobile button and icon-only activator modes, when used outside a menu, then their existing behavior is unchanged.
- [ ] Given the shared component, when typecheck and the two contract registers are exercised, then no menu or dialog regression appears.

## Verification plan
- Automated checks: `npm run typecheck`.
- Manual/UI scenarios: open the Actions menu on a row in each register, click View full details, close the dialog, and repeat with keyboard navigation.
- Regression: check the mobile/icon activator variants and at least one non-contract consumer of the component.
- Expected result: the menu item has a non-zero layout box, opens the dialog, and other activator modes still work.
- Required evidence: typecheck output and row-menu observations for both registers.

## Implementation notes
- Remove the redundant inner `<template>` and keep the existing `v-if`/`v-else` branches at a valid component root.
- If Vue requires a fragment root, use the existing component structure rather than adding a wrapper with layout or event semantics.
- Do not convert the menu item into a button that changes the established row-menu styling.

## Risks and rollback
- Risk: the component is shared by other registers, so a root-structure change can affect their menus; inspect the consumers before editing.
- Risk: dialog teleport or close behavior can change if the dialog is moved outside the component root.
- Rollback: restore the previous single-file component; no data or API rollback is needed.

## Handoff checklist
- [ ] The action is visible and opens in both contract registers.
- [ ] Activator variants and non-contract consumers still work.
- [ ] Typecheck passes.
- [ ] No API or contract business logic changed.
