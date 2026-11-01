# ISSUE-14 — Asset prices have no currency and no acquisition/depreciation data

- **Severity:** P2 (asset register)
- **Category:** Logic sanity
- **Page:** `/asset`
- **Persona:** Super administrator
- **Confidence:** High

## Expectation

An asset register records acquisition cost with a currency, acquisition date, total value (quantity × unit cost where relevant), condition, custodian/office, and depreciation or book value if it is a fixed-asset register.

## Observed

- Table shows `Price` as bare values: **550**, **400**, **90**, **250**, **1200**.
- The `assets` table has **no currency column** at all (`id, serial_number, item, quantity, price, model, condition, office_id, timestamps`).
- No acquisition date, total value column, depreciation or book value.
- For the mouse, quantity 5 × 90 = 450 is not shown; the table treats "price" ambiguously (unit vs total).
- Office/Allocated-to columns are hidden at 1280; three row actions are icon-only (one unnamed? eye/edit/delete appear unnamed).
- "Scroll tables sideways on small screens" appears at desktop width.

## Impact

The register cannot support procurement, valuation, insurance or write-off decisions. A bare 1200 could be USD or AFN and cannot be reconciled to the ledger.

## Evidence

- `review/evidence/asset.png`
- Query: `DESCRIBE assets;`

## Standard comparison

Fixed-asset modules record cost currency, acquisition date, useful life, depreciation method, net book value, location, custodian and status.
