# FIN-001 — Requester is offered Approve/Reject; both fail with duplicate generic toasts

- **Severity:** High
- **Area:** Finance → Payment requests
- **Status:** Open

## Steps to reproduce

1. Sign in as a user who has `approve_pending_transactions` (super admin).
2. Finance → Payment requests → **New payment request**.
3. Transaction type **Income**, reason `Finance review payment request`, amount `500` AFN, Submit.
   The request appears with status `submitted`.
4. Click **Send to review** on that row. Status becomes `under_review`.
5. Click **Approve this request**.

## Expected

Either the approve/reject actions are not offered to the person who created the request, or a
single clear message such as:

> You cannot approve your own payment request. Another approver must review it.

## Actual

- Approve: **two** toasts —
  `This workflow transition is not allowed.`
  and `Unable to save your changes.`
- Reject (with a reason filled in): **two** toasts —
  `Unable to reject the transaction.`
  and `Unable to save your changes.`
- Status stays `under_review`; the buttons remain enabled, so the user can retry the same
  guaranteed failure indefinitely.

## Root cause (confirmed in source)

`PendingTransactionController::approve()` and `::reject()` call:

```php
abort_if($pendingTransaction->requested_by === $request->user()->id, 422,
    'business.pending_transaction_self_approval');
```

The guard is correct. Two presentation defects follow from it:

1. The row actions are not gated by requester identity, so the creator is shown an action they
   can never complete.
2. The business error code is not mapped to copy, so the API layer falls back to a generic
   message and the global notifier adds a second generic toast.

## Suggested fix

- Hide or disable Approve/Reject when `requested_by === current user`, with a tooltip explaining why.
- Map `business.pending_transaction_self_approval` to one specific, translated sentence.
- Do not emit the global fallback toast when a specific API error was already surfaced.

## Evidence

Toast strings recorded verbatim during the run; screenshot
`evidence/fin-001-duplicate-toasts.png`.
