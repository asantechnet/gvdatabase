# ISSUE-08 — Payment requests page renders two paginations

- **Severity:** P2 (conflicting controls)
- **Category:** UI/UX
- **Page:** `/payment-requests`
- **Persona:** Super administrator
- **Confidence:** High — reproduced on an empty list

## Expectation

A single pagination control per table, with one page-size value and one result count.

## Observed

One card contains two independent paginations:

1. Standard table footer: **"Items per page: 10"**, **"0–0 of 0"**, First/Previous/Next/Last.
2. A second control below: **"Per page 25"**, **"0 records"**, Previous/Next.

The values disagree (10 vs 25) and there are two sets of labels/ARIA. Empty state already says "No payment requests.", so both paginators are redundant.

The page also has no breadcrumb, a subtitle clipped mid-sentence ("…the finance office approves it and …"), and no search/filter at all.

## Impact

Users cannot tell which page size is applied; changing one may not affect the other. On a real list this creates inconsistent paging state and extra support questions.

## Evidence

- `review/evidence/payment-requests.png`

## Standard comparison

One paginator, with total count, page size, first/prev/next/last and current range; filters/search above the table.
