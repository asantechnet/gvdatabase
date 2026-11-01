# FIN-002 — Editing an issued receipt silently triples its value

- **Severity:** High
- **Area:** Finance → Income → Edit receipt
- **Status:** Open

## Steps to reproduce

1. Record a standalone income: type `Service`, amount `5000`, quantity `1`, currency AFN.
   Result: `RCPT-2026-000001`, value `؋5000.00`.
2. Open the row's edit action (the pencil icon).
3. Change amount to `6000` and quantity to `3`.
4. Save.

## Expected

A receipt is a financial document. Changing its value should at minimum:

- warn that the receipt total will change, showing **old → new**;
- require a reason or confirmation;
- be reflected in the audit trail.

## Actual

- The receipt is saved with no confirmation, no warning and **no success toast**.
- The row now reads `؋18000.00`, `Quantity: 3`. The value tripled silently.
- The receipt number is preserved (`RCPT-2026-000001`), which is correct, but the amount behind it
  is not protected.

## Why it matters

Any user with edit rights can restate historical receipts, and the ledger and every report that
reads it change retroactively with no acknowledgement. Cash reconciliation then depends on the
audit log rather than on the record itself.

## Suggested fix

- Treat issued receipts as append-only: corrections via a reversing entry or a credit note.
- If editing must remain, require a reason and show a difference summary before save.
- Emit a success toast that states the new total (see FIN-003).
