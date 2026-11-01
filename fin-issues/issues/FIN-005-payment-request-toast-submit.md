# FIN-005 — Payment request confirmation toast reads only "Submit"

- **Severity:** Medium
- **Area:** Finance → Payment requests
- **Status:** Open

## Steps to reproduce

1. Finance → Payment requests → **New payment request**.
2. Fill reason `Finance review payment request`, amount `500` AFN.
3. Press **Submit**.

## Expected

> Payment request submitted for approval.

## Actual

A success-coloured toast appears whose entire text is:

> `Submit`

with the standard close affordance. The request is created correctly (it appears in the table as
`submitted`), so only the message is wrong. This looks like an i18n key fallback resolving to the
button label rather than a sentence.

## Suggested fix

Use an explicit message key for the success case (and assert that no toast falls back to a control
label). Add the request amount/type to the message for context.
