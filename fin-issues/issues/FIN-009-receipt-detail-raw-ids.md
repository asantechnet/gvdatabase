# FIN-009 — Receipt detail exposes internal database IDs

- **Severity:** Low
- **Area:** Finance → Income → View full details
- **Status:** Open

## Steps to reproduce

1. Record a receipt.
2. Open **View full details**.

## Actual

The panel advertises "Receipt · 18 useful details" and lists, among the essential information:

- `Base Currency ID: 1`
- `Applicant ID: —`
- `Applicant Contract ID: —`

Internal foreign keys are presented next to human fields, and empty relationships are shown as
dashes rather than omitted. The currency appears as `AFN` elsewhere on the same screen, so the ID
adds no information for a finance user.

## Suggested fix

- Show the currency code/name, not the key.
- Hide empty relationship rows, or move them to a technical/admin section.
- Replace the "18 useful details" tally with something meaningful or drop it.
