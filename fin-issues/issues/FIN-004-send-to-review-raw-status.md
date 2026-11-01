# FIN-004 — "Send to review" toast exposes the raw status token

- **Severity:** Medium
- **Area:** Finance → Payment requests
- **Status:** Open

## Steps to reproduce

1. Create a payment request (Income, 500 AFN).
2. Click **Send to review**.

## Expected

> Payment request sent for review.

or

> Status changed to Under review.

## Actual

> `Transaction is now under_review.`

The snake_case enum value is interpolated directly into user-facing copy. The same token is also
what the Status column displays, so the UI is presenting database vocabulary rather than a label.

## Suggested fix

- Translate status enum values once (`submitted` → Submitted, `under_review` → Under review,
  `approved`, `rejected`, `posted`).
- Use the translated label in both the toast and the table.
