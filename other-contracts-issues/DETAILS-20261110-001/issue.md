# DETAILS-20261110-001 — “View full details” is unreachable in the row menu

**Severity:** High  
**Area:** Employment Contracts and Other Contracts row actions (shared list)  
**Status:** Confirmed in both registers

## Summary

Every row Actions menu should begin with **View full details**. The item exists in the DOM but is not rendered: it measures `0 × 0`, its parent is a literal `<template>` element, and Playwright reports the title as not visible. The menu therefore shows only the remaining actions (Print/Extend/Cancel, and Edit/Delete on drafts).

## Reproduction

1. Open **Employment Contracts** or **Other Contracts**.
2. Open **Actions** on any row.
3. Look for **View full details**.
4. Observe that the menu contains Print/Extend/Cancel (and Edit/Delete where applicable), not View full details.
5. Attempting to click the text locator fails because the element is not visible.

## Evidence

Menus from both registers:

- `evidence/employment-row-menu.png`
- `evidence/other-row-menu.png`

Measured DOM state in the Employment menu after the open animation completed:

```json
[
  { "text": "View full details", "x": 0, "y": 0, "w": 0, "h": 0, "parent": "TEMPLATE" },
  { "text": "Print contract", "x": 1011, "y": 413, "w": 204, "h": 38, "parent": "div.v-list" },
  { "text": "Extend contract", "x": 1011, "y": 453, "w": 204, "h": 38, "parent": "div.v-list" },
  { "text": "Cancel contract", "x": 1011, "y": 493, "w": 204, "h": 38, "parent": "div.v-list" }
]
```

The other items are laid out normally; only View full details is inside the inert template. The same `0 × 0` result was measured in the Other Contracts menu.

## Root cause

`resources/ts/components/FullDetailsButton.vue:356-383` wraps all of its markup in an extra native `<template>`:

```vue
<template>
  <template>
    <VBtn v-if="mobileAction">...</VBtn>
    <IconBtn v-else-if="!menuAction">...</IconBtn>
    <VListItem
      v-else
      prepend-icon="tabler-eye"
      :title="t('common.actions.viewFullDetails')"
      @click.stop="isOpen = true"
    />
    <VDialog v-model="isOpen">...</VDialog>
  </template>
</template>
```

A `<template>` with no `v-if`/`v-for`/slot directive is a real, inert DOM element in this compiled output. The `VListItem` is its child content, not a child of the Vuetify `VList`, so it has no layout box and cannot receive the click. The dialog is inside the same inert wrapper.

`resources/ts/pages/contract_list.vue:739` mounts the component with `menu-action`, reaching the broken `VListItem` branch.

## Impact

- The primary read-only “View full details” action is unavailable on every contract row.
- The same component is shared by other registers, so the failure is not limited to contracts if they use the same wrapper.
- Users cannot inspect the detailed record view without an alternative page or API call.

## Suggested repair

- Remove the redundant inner `<template>` wrapper so the buttons and `VDialog` become component roots (or move them into a proper fragment/slot).
- Add a UI regression test that opens the row menu and asserts View full details is visible and opens the dialog.

## Evidence files

- `evidence/employment-row-menu.png`
- `evidence/other-row-menu.png`
