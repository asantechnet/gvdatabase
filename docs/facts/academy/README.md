# Academy

Current behavior reviewed 2026-11-11. Source owners are the Academy controllers, `AcademyEnrollmentService`, `AcademySlipService`, `AcademyCertificateService`, `CommissionService`, `app/Support/BuiltInService.php`, and the routes under `routes/academy/`.

## Feature gate and product position

- Academy is a built-in service value (`academy`) and a case-free agreement. It has no applicant stage workflow and no case history.
- `settings.academy_enabled` gates the entire Academy API tree through `EnsureAcademyEnabled`. When disabled, the routes are not available; navigation and frontend feature guards also hide the section.
- `BuiltInService::enabled()` includes Academy only while the setting is on. Academy is sold as a direct agreement, not as an applicant case.
- The Academy UI is `/academy` with classes, students, catalog, attendance, and certificates pages.

## Contract and enrollment

An Academy sale is a contract plus one `applicant_contract` service line, plus an optional durable student/enrollment record.

`AcademyEnrollmentService` is the single writer for terms and enrollments:

- The enrollment start is the contract's `start_date`. The end is the selected class's `ends_on`. No client-supplied term is accepted.
- If the class has no end date, or its end precedes the start, creation fails with `business.academy_term_undefined`.
- The class's `capacity` is enforced against active enrollments. The service locks the class row and re-counts before creating/updating.
- One student cannot hold a second active or completed enrollment in the same class. Withdrawn/cancelled attempts may be retried; soft-deleted rows do not block.
- The service reuses the applicant's durable `students` row (or creates it), updates identity from the applicant, generates a compact `STU-{yy}-{id}` student number when absent, and keeps the legacy academy detail's `student_id` aligned.
- Creating an enrollment sets the contract's `end_date` to the class end.
- Enrollment statuses are `active`, `completed`, `withdrawn`, and `cancelled`. Terminal statuses cannot be updated through the normal enrollment editor.
- An Academy contract may be created without a pre-existing student; the enrollment creates the student identity from the applicant. Direct Academy students can exist without an applicant or contract; the student record is the certificate identity.

## Classes

`academy_classes` fields include name, description, active flag, program, start/end date, completion timestamp, weekdays, class times, capacity, and fee. Teachers are a many-to-many relation (`academy_class_teacher`).

- `GET /academy/classes` and `/academy/classes/{class}` require `view_academy_attendance`; create/update/delete require `manage_academy_classes`; completion requires `manage_academy_attendance`.
- A teacher with only `view_academy_attendance` sees only assigned classes. A holder of `view_academy_contracts` sees the full class list.
- A class with enrollments cannot be deleted (`business.academy_class_in_use`).
- Completing a class is idempotent: it sets `completed_at` once and calls `CommissionService::syncForAcademyClass()`.
- A teacher may complete only a class assigned to them. A holder of `manage_academy_classes` may complete any class.
- `GET /academy/classes/{class}` returns the roster with enrollment, student/applicant, contract line, payment balance, and class details.

## Attendance

- `GET /academy/attendance` requires `view_academy_attendance`; save/delete require `manage_academy_attendance`.
- The month grid accepts `class_id` and `month=YYYY-MM` and returns every date in the month, the active/completed enrollments overlapping the month, and a cell per enrollment/date.
- Attendance statuses are exactly `present`, `absent`, and `leave`.
- Saving upserts one `academy_attendances` row per enrollment/date. For `present`, blank check-in/out fall back to the class `starts_at`/`ends_at`; non-present rows clear times.
- Deleting a cell removes that attendance row.
- A roster row includes the student number, name, contact, term, enrollment status, and the contract line's remaining balance/currency.
- Academy attendance is separate from staff HR attendance. Do not apply Friday/missing-policy rules from [HR](../hr/README.md) to Academy classes.

## Certificates

- `GET /academy/certificates` and `/certificates/{certificate}` require `view_academy_contracts`; issuing and revoking require `issue_academy_certificates`.
- A certificate can be issued only for a `completed` enrollment with a durable student. A duplicate certificate for the same enrollment is refused, including soft-deleted duplicates.
- The active certificate template is selected by id or the first active template. The template's title, body, signatory title and version are snapshotted at issue time.
- Certificate fields are snapshotted: student name/father name/student number, program, class, teachers, dates, certificate number, issue date, signatory and rendered body. The snapshot is immutable after issue; missing optional values render as `—`.
- Certificate numbers use the `CERT-{yy}-{000}` format.
- `POST /academy/certificates` supports a single enrollment or a bulk `class_id` + `enrollment_ids` issuance. Bulk issuance skips existing certificates and reports created/skipped.
- Revoking requires a reason and sets `status=revoked`, `revoked_at`, `revoked_by`, and `revocation_reason`. An already revoked certificate cannot be revoked again.
- Certificate templates expose tokens; `PATCH /academy/certificate-templates/{template}` validates token names before saving.

## Slips and printing

- `GET /academy/enrollments/{enrollment}/slip` returns the student-facing enrollment slip.
- `GET /contracts/{id}/academy-slip` returns the contract-level Academy slip.
- Both use A5 receipt-like output with company branding, student/applicant identity, program/class/teachers/term, service line fee/discount/paid/balance, payment status and signature.
- The contract-level slip uses the contract's academy service line; the enrollment slip falls back to the class fee when no line exists.
- Use the namespaced A5 workflow in [Printing](../printing.md). Do not add an Academy-specific global print stylesheet.

## Payments and commission

- Academy contract lines use the same `applicant_contract` economics and payment state as other services. Receipts attached to a line update `amount_paid` and `payment_status` through `ApplicantContractPaymentService`.
- A fee paid at signing is a real approved income, created only when the actor also has `create_incomes`. It is linked to the academy line and appears in the receipt ledger.
- Academy contract lines do **not** earn counselor commission. `CommissionService` explicitly skips Academy lines.
- A completed Academy class earns teacher commission for teacher-position employees only, using the class fee and the employee's Academy service percentage. The accrual is keyed by class + employee and is idempotent.
- Commission settlement and payout behavior is shared with [Finance](../finance/README.md).

## Permissions

| Permission | Allows |
| --- | --- |
| `view_academy_attendance` | Class list/show, teachers list, attendance month grid. |
| `manage_academy_classes` | Create/update/delete classes, create students, update enrollments, complete any class if also holding this grant. |
| `manage_academy_attendance` | Save/delete attendance and complete assigned classes. |
| `view_academy_contracts` | Enrollment list/show/slip, student finance, certificates/templates, full class list. |
| `issue_academy_certificates` | Issue/revoke certificates and edit certificate templates. |
| `view_applicant_financials`, `view_all_finance_records`, `view_incomes` | Student finance endpoint (one of). |

## UI surfaces

| Page | Route | Purpose |
| --- | --- | --- |
| Classes | `/academy/classes` | Class catalogue, roster, completion. |
| Students | `/academy/students` | Student/enrollment records, finance, slips. |
| Catalog | `/academy/catalog` | Academy master data/programs/classes. |
| Attendance | `/academy/attendance` | Monthly class attendance grid. |
| Certificates | `/academy/certificates` | Issue/revoke certificates and templates. |

## Known limits and invariants

- Academy is case-free; it must never be given applicant stages or case commission.
- Enrollment terms are derived; never accept client dates.
- A student is unique per class among active/completed enrollments.
- Certificate snapshots and issued certificate numbers are immutable.
- Deleting a class with enrollments is refused; preserve enrollment history.
- The feature gate is evaluated per request; do not expose Academy routes when `academy_enabled` is false.

## Source map

- `routes/academy.php`, `routes/academy/classes.php`, `attendance.php`, `enrollments.php`, `certificates.php`
- `app/Http/Controllers/AcademyClassController.php`
- `app/Http/Controllers/AcademyEnrollmentController.php`
- `app/Http/Controllers/AcademyAttendanceController.php`
- `app/Http/Controllers/AcademyCertificateController.php`
- `app/Http/Controllers/AcademyStudentController.php`
- `app/Http/Controllers/AcademyTeacherController.php`
- `app/Services/AcademyEnrollmentService.php`
- `app/Services/AcademySlipService.php`
- `app/Services/AcademyCertificateService.php`
- `app/Services/CommissionService.php`
- `app/Models/AcademyClass.php`, `AcademyEnrollment.php`, `AcademyAttendance.php`, `AcademyCertificate.php`, `AcademyCertificateTemplate.php`, `Student.php`
- `resources/ts/pages/academy/classes.vue`, `students.vue`, `catalog.vue`, `attendance.vue`, `certificates.vue`
