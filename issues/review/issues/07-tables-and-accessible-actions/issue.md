# ISSUE-07 — Key columns and actions are hidden; row actions are unnamed

- **Severity:** P2 (usability / accessibility)
- **Category:** UI/UX
- **Pages:** Contracts, CRM Visitors, Staff directory, Payrolls, Applications
- **Persona:** Super administrator
- **Confidence:** High at 1280×720 and 390×844

## Expectation

ERP tables keep status and actions visible (or provide a visible, discoverable horizontal scroll), expose a single labelled row-action menu, and give every icon button an accessible name. WCAG 2.2 AA 4.1.2 requires a name for controls.

## Observed

- **Contracts:** at 1280 wide, only columns through Start Date are visible; Status and Actions are off-screen. No scrollbar/affordance. One action has a name ("View full details"); **three are unnamed**.
- **CRM Visitors:** Consultant and Actions off-screen; **all four row icon buttons are unnamed**.
- **Staff directory:** Status and Actions off-screen; **five of eight icon buttons have no name**; seven controls per row.
- **Payrolls:** Actions column entirely off-screen at 1280; the hint "Scroll tables sideways on small screens" appears on desktop.
- **Login:** password visibility button is named "Appended action"; email/password accessible names duplicate ("Email Email", "Password Password").
- **Dialogs:** close (X) buttons are unnamed on the task and contract dialogs; the Income gear is unnamed.

Horizontal scrolling does work with a trackpad/shift-wheel, but the initial view gives no indication that status/actions exist. At 390px the Applications list replaces the table with cards that show only the applicant name and a 3-dot menu, losing stage/service/assignee/status.

## Impact

Users cannot see contract status or find row actions without experimenting; screen-reader users hear anonymous buttons. At common 1366×768 laptop width this is the default experience, not an edge case.

## Evidence

- `review/evidence/contracts.png`, `review/evidence/contracts-scrolled.png`
- `review/evidence/crm.png`, `review/evidence/crm-scrolled.png`
- `review/evidence/staff-list.png`
- `review/evidence/payrolls.png`
- `review/evidence/apps-mobile.png`

## Recommendation (no code change made)

Make the action/status columns sticky or fit at 1280; add a visible horizontal-scroll affordance; move row actions into one menu with text or aria-labels; remove duplicate accessible labels; name dialog close buttons.
