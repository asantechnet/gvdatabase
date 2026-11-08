# Delete list — academy revamp

Scope: the academy surface reviewed while planning the Academy revamp (2026-11-07).
Rule: a thing belongs here only if it is **strictly useless** — no reachable route,
API, print path, test, snapshot, or client-approved feature depends on it.

No deletions were performed. The client explicitly approved the current academy
slip and academy contract and asked for no destructive actions, so this list is
deliberately conservative.

## Verdict

No strictly useless academy logic was found. Every academy-specific file is
reachable and in use:

- `app/Services/AcademySlipService.php` → `GET /contracts/{id}/academy-slip` →
  `resources/ts/components/academy/AcademySlipPrinter.vue` → the academy-row
  action in `resources/ts/pages/service-contracts.vue`.
- `database/seeders/AcademyContractTemplateSeeder.php` → registered in
  `DatabaseSeeder`; `ContractGenerationService` aborts without the hidden
  academy template.
- `resources/ts/pages/academy-data.vue` → `/contracts/academy-data` → Options →
  Contracts → Academy data.
- Academy catalogs (`programs`, `times`, `durations`, `teachers`,
  `academy_classes`) → required by `StoreServiceContractRequest` and the
  contract wizard.
- Academy stages (`new → enrolled → in_class → completed / cancelled /
  withdrawn`) → `ApplicantStageController` and the academy pipeline.
- `view_academy_contracts` / `ServiceBoard` → contract list, Kanban access and
  report scoping.
- `tests/Feature/AcademySlipTest.php`,
  `scripts/academy-slip-print-regression.mjs` → active academy regression
  coverage.

## Fix, do not delete

These are wrong or stale, but not useless:

1. `resources/ts/plugins/1.router/routes.ts:244` — the academy route sets
   `navActiveLink: "private-contracts"`, so the sidebar highlights Private
   Contracts while the user is in Academy. Point it at the new Academy nav item.
2. `app/Http/Controllers/EducationalMasterDataController.php:237,240` — the
   `classes` catalog is authorized as `program` (`create_program`,
   `edit_program`, …). That is a permission mismatch, not dead code. Give
   academy classes their own academy permission instead of reusing programs.
3. `resources/ts/plugins/1.router/routes.ts:359` — `/contracts/academy-data` is
   guarded by `view_country`, which is unrelated to academy operations. Move it
   under the Academy section and its permissions.
4. `resources/ts/pages/academy-data.vue` header comment — says the page lives
   “under the contracts submenu”; stale after the Academy navigation move.
   Update the comment, keep the page.

## Becomes redundant only after the enrollment split

Do not remove any of this before the new `academy_enrollments` rows are
backfilled, read paths are switched, and the print/regression tests pass.

- `academy_applicant_details.program_id`, `time_id`, `duration_id`, `class_id`
  once the course lives on `academy_enrollments`. The same table remains the
  per-case course detail and keeps `student_id`; it is not a general student
  profile.
- `ApplicantServiceDetails` academy write path currently
  `updateOrCreate([], …)` on the profile. It is correct for the 1:1 profile
  today; after the split it must stop writing course fields. Not dead now.
- `academy_applicant_teacher` pivot — becomes a per-student override once the
  class owns its teachers. Keep it as the fallback for existing data and
  historical printing.
- The unique `academy_applicant_details.applicant_id` index must stay: the table
  keeps the legacy/first course detail for each applicant and is not repurposed
  as a multi-course profile. Multiple courses belong in `academy_enrollments`,
  not in multiple detail rows.

## Cleanup candidates, not strictly useless

- `AcademySlipCompany.signature_url`, `short_name` and `website` are returned by
  `AcademySlipService` but not read by `academy_slip_template.vue`; the template
  uses the top-level `signature.url` and `company.logo_url`. Removing them
  changes a typed API payload, so leave them until the slip API is versioned.
- `ContractTemplateController@index` validates `include_generic` but never
  applies it to the query. The frontend method exposes it and no caller passes
  it. This is an unimplemented filter, not confirmed dead code, and it is
  outside the academy scope. Leave it unless the contract-template owner
  confirms it is abandoned.
- `service_label` on the academy slip duplicates `name` by design (it matches
  the receipt payload), and the regression script consumes it. Keep.

## Must keep (client-approved or actively used)

- The A5 academy slip: `AcademySlipService`, `pages/templates/academy_slip_template.vue`,
  `AcademySlipPrinter.vue`, `utils/printAcademySlip.ts`, the regression script
  and its test.
- The hidden static academy contract template and its seeder. The client
  approved the academy contract; it is generated for every academy sale.
- Academy catalogs and `academy-data.vue`. They are the current source of
  programs, times, durations, teachers and classes.
- Academy stages, `view_academy_contracts`, `ServiceBoard` and the education
  dashboard/report academy aggregation.
- `student_id` on the academy student profile. It is required by the contract
  wizard and belongs to the person, not to an individual course.

## Safety notes for the implementation

- No `DROP`, rename or data deletion before the new enrollment rows are
  backfilled and verified against `academy_applicant_details`.
- The split is additive: create `academy_enrollments`, copy each existing course
  row into one enrollment, keep the profile row, then switch reads. Old course
  columns stay until the old read paths and print paths are gone.
- Before any future removal: update `tests/Feature/AcademySlipTest.php` and
  `scripts/academy-slip-print-regression.mjs`, run the academy print
  regression, and confirm the issued-contract snapshots still render.
