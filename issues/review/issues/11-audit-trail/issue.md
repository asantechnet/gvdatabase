# ISSUE-11 — Audit trail is not readable; stage history omits the actor

- **Severity:** P2 (auditability)
- **Category:** Logic sanity / security
- **Pages:** Audits, Case timeline, Notifications
- **Persona:** Super administrator
- **Confidence:** High

## Expectation

An audit entry states the record, the field that changed, the old and new values, the actor, the time and (where available) the source. A case timeline names who moved it and why.

## Observed

- After moving case #1, `/audits` showed:
  - Action: Changed
  - Record: **Applicants · Ahmad Wali**
  - What changed: **"Other information: 5 → 6"**
  - Changed by: Demo Admin
  - When: Oct 29, 2026
  This is the internal stage id change, not "University Selection → University Application". No field label, no note, no IP.
- The case Timeline entry shows only **"University Selection → University Application"** and a timestamp. `applicant_stage_histories` stores `changed_by` and `note`, but the UI does not show either.
- Notifications say **"Applicants Ahmad Wali was changed."** with no stage, actor or action.
- 550 of the audit rows are seeded "System" creates all marked "Needs audit", swamping the real event.
- The Audits table clips its right-hand columns at 1280 and wraps record names into 4-line cells.

## Impact

An auditor cannot reconstruct what happened from the UI. A super admin cannot tell who advanced a case or why, which matters for document/visa disputes and commission eligibility.

## Evidence

- `review/evidence/audits.png`
- `review/evidence/notifications.png`
- `review/evidence/case1-after-stage.png`
- Source: `ApplicantStageHistory` creation in `ApplicantController.php:330` (writes changed_by/note), UI omits them.

## Verification

1. Change a case stage with a note.
2. Check the timeline shows actor + note.
3. Check Audits shows field name and readable old/new values (stage names, not ids).
4. Confirm seeded/system entries can be filtered out of the review queue.
