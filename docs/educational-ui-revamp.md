# Educational workspace revamp

The page layout follows `educational-new-design.pdf`: compact title and breadcrumbs opposite a service subnavigation, alternating table rows, labels above equal-width fields, red required markers, red Save and neutral Cancel. Admissions use four field columns; academy and test use three. Grids collapse for smaller screens and use logical CSS properties for RTL. Extra contact and legacy fields are in optional sections.

Contracts, applicants, visitors/CRM and dashboards share the header and date-range controls. Date endpoints are inclusive, accept either bound and reject reversed or invalid ranges. Applicants filter creation date, contracts filter start date, visitors filter visit date; dashboard retains its existing date semantics. CRM searches and pagination run against the backend.

Private is a fourth built-in service with academic details, subjects, application and visa charges. Scholarship adds a scholarship selector; academy adds a class selector; test applicants add backup phone. The new scholarship options (educational_scholarships, separate from retired scholarship sales storage) and academy classes are managed in Educational Data using the existing permission families. Example records are included in EducationalReferenceSeeder.

Itemised fees remain on applicant_contract. A submitted total must equal the charges and inappropriate charges for another service are rejected. Legacy fee-only contracts remain accepted. Issued snapshots and receipt numbering are preserved; private admissions participate in reports, destination aggregates, applicant portals and case filters.

## Activation

Apply the pending migrations through the normal database deployment process. The new migration is `2026_10_08_000011_add_private_admissions_and_service_fields.php`. Populate the scholarship and class catalogs in Educational Data. Configure and activate the private contract template and its language/country versions in the contract editor before issuing private contracts. Existing template validation remains in force.

## Verification

- TypeScript: `npm run typecheck` passed.
- Backend: 61 tests passed (664 assertions) covering contract generation/payment, admissions, catalogs, portals, reporting, date ranges and retired catalog boundaries using an in-memory SQLite database.
- `node scripts/service-contract-regression.mjs`: payload normalization, permission mappings, Vue template compilation and translation checks.
- `node scripts/receipt-print-regression.mjs`: namespaced print lifecycle and paper configuration checks.
- `npm run build:icons`: generated icon bundle without missing icon warnings.
- `node scripts/educational-ui-regression.mjs`: standalone preview harness compiles the actual service forms and shared components. Add `--browser` for desktop/mobile, RTL/LTR and theme checks when Chromium is available.

This environment could not launch Chromium, including outside the sandbox, so browser layout assertions and visual screenshots have not been verified. The complete Vite build is also blocked by missing Linux Rollup/esbuild binaries and unavailable dependency-download DNS. No live database migration was performed.
