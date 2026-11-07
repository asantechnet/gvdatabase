## Task: University and country contact details + visa-case quick contact

- **Task ID:** university-country-visa-contacts
- **Created:** 2026-11-05
- **Last updated:** 2026-11-05
- **Status:** COMPLETED
- **Outcome:** Universities and countries store optional phone, email and portal address. The visa case page offers quick contact for the destination country/embassy and for every tracked university, plus editable notes per university application. Portal addresses accept any typed URL shape and are turned into one canonical link only at render time.

### 1. Requirements and acceptance criteria
- [x] `universities` and `countries` accept optional `phone`, `email` and `portal_url`; values round-trip through the master-data API.
- [x] `portal_url` has no URL/format validation and stores exactly what the user typed (including values without a scheme).
- [x] The Educational master-data editor exposes the three fields for both catalogues.
- [x] The visa case page shows a quick-contact option for the case's destination country/embassy (phone, email, website) when a country is set.
- [x] Each tracked university application on the visa case shows quick contact for that university (phone, email, portal) when those values exist.
- [x] Portal links are rendered as a plain `<a href>` opening in a new tab.
- [x] Each tracked university application has its own editable notes on the visa case page.
- [x] A shared frontend formatter converts common URL shapes (`https://…`, `http://…`, `//host/path`, `host/path`, `www.host`) into one canonical `https` link; it never emits a non-web scheme.
- [x] All new copy exists in `en`, `fa-AF` and `ps-AF`; layouts stay RTL/LTR and dark/light safe (wrapping flex rows, logical CSS properties, theme variables).

### 2. Scope and non-goals
- In scope: migration, models, master-data validation, frontend API type, master-data editor, visa case page contact/notes UI, URL formatter, locale keys, focused backend tests, task record.
- Out of scope: renaming/removing the existing university `website` field, new permissions, changes to `service-case.vue` (it has no university section), changes to visa reports, CRM interaction logging for universities/countries, print documents.

### 3. Project findings
- `countries` and `universities` are created in `database/migrations/0001_06_01_000006_create_reference_tables.php`; `universities` already has `city`, `website` (validated as `url`) and `description`. `countries` had only `name`, `code`, `is_active`.
- `EducationalMasterDataController` is the single CRUD adapter for both catalogues; validation is a `match` block and soft-delete restore is shared.
- `resources/ts/pages/educational-master-data.vue` renders one tab per catalogue and one shared dialog; previously only `universities` got `country`, `city`, `website` fields.
- `resources/ts/pages/applicant-case.vue` is the visa case workspace (`/applicants/:id`). It loads `universities`, `countries` and `semesters` master data on mount, and renders tracked applications from `applicant.universities`.
- `applicant_universities` already has a `notes` column and the API already accepts/updates it; the page previously only wrote notes when adding an application and showed them read-only in the summary.
- `ContactActionMenu` is the established quick-contact component (phone: WhatsApp/Telegram/call, email: mailto/copy). It only logs CRM interactions when `contactable-type`/`contactable-id` are supplied, so it was reused read-only for universities/countries.
- `MasterDataRecord` in `resources/ts/api/educationalMasterData.ts` is the shared master-data type.
- Locales are checked by `npm run i18n:check`; every used key must exist in all three locale files.

### 4. Graph and impact review
- No `graphify` skill/artifacts were available in this session; dependency tracing used repository search, symbol/reference lookup and reading call sites.
- Backend callers examined: `EducationalMasterDataController` (CRUD), `Country`/`University` models, `ApplicantUniversityController` (notes already validated), `ApplicantController` relations (details/country loaded), seeders.
- Frontend callers examined: `educational-master-data.vue`, `applicant-case.vue`, `educationalMasterData.ts`, `applicants.ts`, `ContactActionMenu.vue`, locale files.
- Potential downstream effects: master-data list payload grows by three nullable fields (additive); no existing consumer destructures exhaustively. Migration is additive and reversible.
- Limitation: no browser/Playwright run was performed; layout safety is structural (wrapping flex, logical props) rather than visually verified.

### 5. Clarifications, decisions, and assumptions
- Open questions: none blocking.
- Assumption: "per university notes" means notes attached to each tracked university application inside the visa case, not a shared catalogue note. The schema already models exactly this (`applicant_universities.notes`), so the task exposes/edits that field rather than adding a second notes concept.
- Assumption: "portal address" is a dedicated field (`portal_url`) next to the existing general `website`; existing website data and behaviour are untouched.
- Decision: `portal_url` is a nullable `text` column with `nullable|string` validation (no format or length constraint) so any typed URL shape is preserved; normalization happens only when rendering the anchor.
- Decision: the formatter rejects non-web schemes by prefixing `https://` rather than passing them through, so a stored `javascript:` value cannot become an executable href.
- Low-risk convention: read-only quick contact for universities/countries omits CRM `contactable-*` props, because those records are not CRM contacts; the existing menu still exposes call/mail/copy/WhatsApp actions.

### 6. Implementation plan
- [x] Added `database/migrations/2026_11_05_000002_add_contact_fields_to_countries_and_universities.php` (nullable `phone`, `email`, `portal_url` text; additive `up`, column-only `down`).
- [x] Added the fields to `Country` and `University` fillable, and to `EducationalMasterDataController` validation for both catalogues.
- [x] Added `phone`/`portal_url` to `MasterDataRecord`, and the three inputs to the master-data dialog for both catalogues (plus contact in the details column).
- [x] Added `resources/ts/utils/externalUrl.ts` with one canonical formatter.
- [x] Updated `applicant-case.vue`: destination country/embassy contact block, per-university quick contact, portal anchors and per-university notes dialog/save.
- [x] Added locale keys to `en.json`, `fa-AF.json`, `ps-AF.json`.
- [x] Extended `EducationalMasterDataTest` (contact round-trip, scheme-less/protocol-relative portal accepted) and `ApplicantUniversityTest` (per-application notes update).

### 7. Validation plan and results
- [x] `php artisan test --filter='EducationalMasterDataTest|ApplicantUniversityTest'` — 8 passed, 78 assertions.
- [x] `npm run typecheck` — exit 0.
- [x] `npm run i18n:check` — exit 0; no missing keys, only the repository's existing unused-key count (1755).
- [x] Formatter executed with `npx tsx`: https, http, `//host`, bare host/path, `www.`, padded whitespace, `javascript:` and empty/null inputs all produced the expected href.
- [x] Locale JSON parsed in Node for all three locales and the new keys were asserted present.
- [x] `npx eslint` on the new utility/API files — clean. The two large pre-existing Vue pages report existing style violations independent of this change (101 errors/680 warnings across `applicant-case.vue` and `educational-master-data.vue`); `npm run lint` was not run because it rewrites the repository.
- Checks not run: no browser/visual pass and no full test suite; the change is additive and covered by focused API tests plus typecheck.

### 8. Risks and mitigations
- Existing university `website` remains restricted to `url`; users could confuse it with `portal_url` — mitigation: distinct labels and a hint stating the portal address is stored as typed and linked from the case.
- Quick-contact actions add width to already dense case rows — mitigation: rendered in a wrapping sub-row, not in the append actions.
- `javascript:`/data URLs stored in `portal_url` — mitigation: formatter only allows `http(s)` and prefixes anything else with `https://`.
- Additional nullable columns are safe for existing rows and the down migration drops only the new columns.

### 9. Progress log
- 2026-11-05 — DISCOVERY: inspected reference migration, master-data controller/models/page, visa case page, university application controller/test, `ContactActionMenu`, applicant API and all three locale files; no graph tooling available, fallback search used.
- 2026-11-05 — PLAN: plan written before implementation.
- 2026-11-05 — PROGRESS: migration, model fillable, validation, API type, master-data form, external URL formatter, visa-case country/university quick contact, per-university notes dialog and locale keys implemented.
- 2026-11-05 — VALIDATION: 8 focused PHP tests passed (78 assertions); `npm run typecheck` exit 0; `npm run i18n:check` exit 0; formatter and locale-key assertions passed.

### 10. Completion summary
- Delivered: optional phone/email/portal address on both catalogues; quick contact and portal anchors on the visa case for the destination country/embassy and every tracked university; editable per-university notes; one canonical URL formatter.
- Acceptance criteria satisfied: all items in section 1.
- Tests/checks passed: `EducationalMasterDataTest` + `ApplicantUniversityTest` (8 tests), typecheck, i18n check, formatter assertions, locale JSON assertions.
- Remaining limitations or follow-up work: no browser visual pass; university `website` remains a separate strict-URL field by design; `service-case.vue` has no university/country section so it was not changed.
