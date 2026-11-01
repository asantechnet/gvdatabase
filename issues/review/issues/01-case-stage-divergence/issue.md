# ISSUE-01 — Case stage is stored twice and pages disagree

- **Severity:** P1 (business integrity)
- **Category:** Logic sanity / workflow
- **Pages:** Agency overview, Applications, Case detail, Applicant pipeline, Tasks, Commissions
- **Persona:** Super administrator
- **Confidence:** High — reproduced and verified in MySQL

## Expectation (standard ERP)

A case has one authoritative current stage. The list, board, dashboard, filters, task generation and commission rules use that same stage. Extension/renewal lines never make one case appear as two active cases unless explicitly presented as a line with its own identity.

## Observed

- `applicants.current_stage_id` for case #1 = **6 (University Application)**.
- Both `applicant_contract` lines for case #1 = **1 (New)**. All eight service lines across four applicants sit in each service's New stage.
- Applications list shows case #1 as **University Application**; pipeline shows the same case **twice under New** (CTR-26-001 and CTR-26-002).
- Pipeline header says **Active: 2 / All: 2** while three cards are visible.
- Dashboard tile **"New Scholarship 2"** links to `/applications?stage=new&service=scholarship`, which returns two rows whose displayed stages are **Consultation** and **University Application**. The "All applications" tab stays selected and only a small "1 Active Filters" hint appears.

## Impact

Stage filters, counts, SLA/task triggers, commission eligibility and dashboard KPIs can each refer to a different truth. A super admin cannot reconcile "5 active cases", "2 active" and "2 New Scholarship", and cannot trust the pipeline as an operational queue.

## Evidence

- `review/evidence/applications.png`
- `review/evidence/apps-new-filter.png`
- `review/evidence/pipeline.png`
- `review/evidence/case1.png`
- `review/evidence/case1-after-stage.png`

## Likely locations (read-only pointers)

- `app/Http/Controllers/ApplicantController.php:318-341` (`changeStage` updates only `applicants.current_stage_id`)
- `app/Http/Controllers/ApplicantContractStageController.php`
- `applicants.current_stage_id` vs `applicant_contract.current_stage_id`

## Verification for implementers

1. From `/agency-overview`, click "New Scholarship 2" and compare the rows' Stage column with the filter.
2. The same case must not be countable twice in the pipeline; state whether the unit is a case or a service line everywhere.
3. Filter by each stage and assert every visible row matches the selected stage.

```
SELECT current_stage_id FROM applicants WHERE id=1;              -- 6
SELECT id,service,current_stage_id FROM applicant_contract;      -- all New stage ids
```
