# FIN-006 — Icon-only receipt and asset actions have no accessible name

- **Severity:** Medium
- **Area:** Finance → Income, Finance → Asset
- **Status:** Open

## Steps to reproduce

1. Open `/income` with at least one receipt.
2. Inspect the row's action buttons in the accessibility tree.
3. Repeat on `/asset`.

## Actual

Income row, five buttons:

| Icon | Accessible name |
|---|---|
| `tabler-paperclip` | Attachments |
| `tabler-eye` | View full details |
| `tabler-receipt` | **none** (`aria-label` null, `title` null, no text) |
| `tabler-edit` | **none** |
| `tabler-trash` | **none** |

Asset row: `View full details` is labelled; the remaining two icon buttons are also unlabelled.

## Impact

A screen-reader user hears "button" three times in a row on the most important row of the ledger,
with no way to tell print from edit from delete. Hovering gives no tooltip either.

## Suggested fix

Add `aria-label` and `title` to each: "Print receipt", "Edit receipt", "Delete receipt",
"Edit asset", "Delete asset". Keep the labels translated for RTL locales.
