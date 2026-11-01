# ISSUE-13 — Mobile dashboard overflows; mobile application cards lose all context

- **Severity:** P2 (responsive usability)
- **Category:** UI/UX
- **Pages:** Agency overview, Applications, Visa reports, dark mode
- **Persona:** Super administrator
- **Confidence:** High at 390×844 and 1280×720

## Expectation

At 390px, KPI grids wrap without clipping, list rows expose the fields needed to triage, navigation remains reachable, and the dark theme keeps branding legible.

## Observed

- **Dashboard (390):** KPI cards stay in a wide 2-column row. "Action items" is cut at the right edge and "University offers received" is off-screen entirely. There is no horizontal scroll indicator.
- **Applications (390):** each case becomes a card showing only the applicant name and a 3-dot menu. Stage, service, assignee, status, payment and dates disappear, so cases cannot be triaged without opening each one.
- **Applications sub-navigation (390):** the three buttons overflow; "Visa reports" is clipped.
- **Dari (1280):** number digits mix Persian and Western on the same screen; country names and notification strings remain English (i18n console warnings for missing keys).
- **Dark mode:** the "Educational Visa" logo is near-invisible (dark text/asset on dark background).

## Evidence

- `review/evidence/dashboard-mobile.png`
- `review/evidence/apps-mobile.png`
- `review/evidence/dashboard-dari.png`
- `review/evidence/dashboard-dark.png`

## Standard comparison

Mobile ERP lists show at least identifier, stage, owner, amount/status, and a kebab action; dashboards stack KPIs cleanly; theme-specific logo assets are provided.
