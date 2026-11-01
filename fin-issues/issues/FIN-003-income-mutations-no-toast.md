# FIN-003 — Income create/edit/delete give no success feedback

- **Severity:** Medium
- **Area:** Finance → Income
- **Status:** Open

## Steps to reproduce

1. **Record Income** with a valid type and amount → row appears, dialog closes.
2. **Edit** that row and save.
3. **Delete** that row and confirm.

Toast container was inspected at 1 s and 3 s after each action.

## Expected

- `Receipt recorded.`
- `Receipt updated.`
- `Receipt deleted.`

The rest of the application uses `vue-toastification` for exactly this purpose.

## Actual

No toast at any point for create, edit or delete. The dialog simply closes and the table
re-renders. The delete confirmation dialog (`Are you sure you want to delete this income record?
This action cannot be undone.`) is present and clear, but confirming it produces no confirmation
of completion.

## Why it matters

Financial writes are the actions users most need acknowledged. Silence makes it impossible to
distinguish "saved" from "request dropped", and it is inconsistent with expense/payment-request
flows, which do toast.

## Suggested fix

Add success toasts in the income API layer's callers (page handlers), using the existing
translation keys, and include the receipt number in the message.
