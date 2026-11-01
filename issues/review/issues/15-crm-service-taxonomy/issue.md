# ISSUE-15 — Visitor service is free text; qualification data is ambiguous

- **Severity:** P2 (data integrity / reporting)
- **Category:** Logic sanity
- **Pages:** CRM Visitors, Add Visitor
- **Persona:** Super administrator
- **Confidence:** High

## Expectation

The service a visitor is interested in uses the same controlled vocabulary as cases and reports (Scholarship, Private, Test offer, Academy). Qualification scores have a defined meaning and range.

## Observed

- Add Visitor's **Service *** is a plain textbox with placeholder "Enter service"; no suggestions or validation.
- The Visitors table and dashboard use the same four services as tags. Any typed value ("sch", "Scholarship ", "VIP") will fragment filters and reports.
- **Percentage** has no label explaining whether it is a grade, English score or probability; the table renders "PhD 90.00%" with no column header for the number.
- "Age" is a free numeric input with no min/max/units shown.
- The Add dialog's notes field sits under the sticky footer at 720px; "Add" is a low-emphasis text button compared with the teal primaries elsewhere.

## Impact

Referential integrity and service-level reporting cannot be trusted; staff cannot interpret the percentage.

## Evidence

- `review/evidence/crm-scrolled.png`
- `review/evidence/crm.png`

## Standard comparison

CRM lead forms use a service picklist from the same master data as the case module, with defined qualification fields and ranges.
