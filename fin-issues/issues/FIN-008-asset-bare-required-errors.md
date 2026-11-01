# FIN-008 — Asset form shows three bare "Required." errors

- **Severity:** Low
- **Area:** Finance → Asset → Add New Asset Item
- **Status:** Open

## Steps to reproduce

1. Open `/asset` → **Add Asset**.
2. Leave serial number, item name and model empty.
3. Press **Save**.

## Expected

Field-specific messages, consistent with the rest of finance:

- "Enter a serial number."
- "Enter the item name."
- "Enter the model."

## Actual

Three identical messages, each with no field name:

> `Required.`
> `Required.`
> `Required.`

Compare: the income dialog says "Select an income type." and "Amount must be greater than zero.";
the expense dialog says "Enter a description." The asset form is the outlier.

## Secondary observation

The allocation line `Allocated: 0 / 1. The total cannot exceed inventory quantity.` is rendered as
an `alert` even when the state is valid. It reads like an error before the user has done anything
wrong; it should be neutral helper text until the limit is actually exceeded.
