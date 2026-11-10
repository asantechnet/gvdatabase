# LOGIC-20261110-003 — Responsible assignment is stored but cases remain Unassigned

## Severity: High

## Status: Confirmed

## Reproduction

1. Create the three case-bearing contracts.
2. As super admin, open each applicant’s **Edit case** form and assign **Test Admission** (employee 9) in **Responsible employees**.
3. Save the case.
4. Inspect the assignment tables and the Applications list.

## Observed data

For applicants 7, 8, and 9:

- `applicant_responsible_employees` contains employee `9`.
- `applicants.responsible_employee_id` is `NULL`.

The Applications list still displays **Unassigned** for all three rows:

- `#7 CC Private Applicant — Unassigned — New`
- `#8 CC Scholarship Applicant — Unassigned — New`
- `#9 CC Test Offer Applicant — Unassigned — New`

Before adding the Manager role, Test Admission saw **“No applicants found”** even though the private case already had employee 9 in the pivot. After adding Manager, the admission account could see all cases, but the Employee column still said **Unassigned**.

## Expected

Assigning a responsible employee through the supported super-admin form should:

- attribute the case to that employee in the list, and
- make the case visible to that employee under the case-access rules.

## Actual

The assignment is written only to the plural pivot by `ContractGenerationService::syncResponsibleEmployees`. The Applications list and access checks read the singular `applicants.responsible_employee_id` field.

Relevant paths:

- `app/Services/Contracts/ContractGenerationService.php` — writes `applicant_responsible_employees`
- `app/Http/Controllers/ApplicantController.php` — case access uses `responsible_employee_id`
- `app/Support/ContractAccess.php` — access scope uses `responsible_employee_id`
- `resources/ts/pages/applications.vue` / API resource — Employee column reflects the singular field

## Impact

The super-admin assignment does not produce the expected visibility or attribution. The admission user can see the cases only after being given Manager, which bypasses the intended case scope rather than honoring the assignment. Case commission attribution previously did not use the responsible-employee pivot; it used only the contract seller fields. That part is now fixed: responsible employees are included and Test Admission received 4,800 AFN after backfill (LOGIC-20261110-005). The assignment/visibility mismatch described in this issue is separate and remains.

## Evidence

- `evidence/applications-unassigned-after-pivot.png`
- `evidence/admission-no-applications-before-manager.png`
- `evidence/assignment-db.txt`
