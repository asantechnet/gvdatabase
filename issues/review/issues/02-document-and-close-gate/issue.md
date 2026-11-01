# ISSUE-02 — Document gate and close reason are claimed but not enforced

- **Severity:** P1 (business control bypass)
- **Category:** Logic sanity / security control
- **Pages:** Case detail (`/applicants/1`)
- **Persona:** Super administrator
- **Confidence:** High for the document gate (reproduced); high from source for the close reason (not executed because Cancelled is terminal)

## Expectation

The page itself states: *"Add a labeled document before progressing the case or creating a contract."* Moving a case forward should be blocked until at least one labeled document exists, and closing a case should require the stage note the field label promises.

## Observed

- Case #1 had **0** applicant documents and was still moved from **University Selection → University Application** through the normal UI. Toast: "Stage change recorded."
- `app/Services/ApplicantDocumentReadiness.php` exists but has **no call sites**; `changeStage()` validates only `stage_id` and `note => nullable`.
- The stage note label says "required to close a case", and the Cancelled/Withdrawn options are offered with an empty note. The API would accept the close without a note.
- The case page's two service lines stayed at New, so the "progress" is recorded only on the applicant (see ISSUE-01).

## Impact

Contracts or visa steps can proceed without the documents the workflow claims to require, and a case can be closed with no reason. Both undermine auditability and dispute handling.

## Evidence

- `review/evidence/case1.png` (before, zero documents)
- `review/evidence/case1-after-stage.png` (after, still zero documents)
- Source: `app/Http/Controllers/ApplicantController.php:318-341`; `app/Services/ApplicantDocumentReadiness.php`; locale key `educational.caseUi.applicantDocumentsNeeded` in `resources/ts/plugins/i18n/locales/en.json:5192`.

## Verification for implementers

1. With a case that has no documents, attempt the next stage in the UI and directly through the normal form; it must be refused with a field-level message.
2. Attach one labeled document, then confirm progression succeeds.
3. Attempt Cancelled/Withdrawn with a blank note; it must be refused, and the UI must indicate that the reason is mandatory for terminal stages.
