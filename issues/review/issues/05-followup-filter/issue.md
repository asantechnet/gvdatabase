# ISSUE-05 — Follow-up list is not filtered

- **Severity:** P2 (feature does not do what its name says)
- **Category:** Logic sanity
- **Page:** `/visitor?list=follow-up`
- **Persona:** Super administrator
- **Confidence:** High

## Expectation

The Follow-up list shows only visitors marked for follow-up (or otherwise due), with a visible active-filter state and an empty state when nothing is due.

## Observed

- All six visitors have `follow_up = 0` in the database.
- The Follow-up list shows **five** unconverted visitors — the same set as the default Visitors list (converted Hamid Noorzai is excluded, correctly).
- The page title remains "Visitors"; the nav item is not shown as active; no filter chip or follow-up column is visible.
- The API filters only when a `follow_up` parameter is present (`VisitorController.php:48`), but the Follow-up link uses `?list=follow-up`.

## Impact

A "follow-up" queue that shows everyone is not a queue; users cannot see who actually needs follow-up, and the star action has no visible effect in this view.

## Evidence

- `review/evidence/crm.png`
- `review/evidence/followup.png`
- DB: `SELECT COUNT(*) total, SUM(follow_up=1) follow FROM visitors;` → 6 total, 0 follow.

## Verification for implementers

1. Open the Follow-up list with zero flagged visitors — it should be empty with an explanation.
2. Star one visitor; it should appear in the Follow-up list and be marked active in navigation.
3. Unstar; it should disappear.
