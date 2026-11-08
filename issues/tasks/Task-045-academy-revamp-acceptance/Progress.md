# Progress — Task-045

## Status snapshot
- Status: `Blocked`
- Owner: `QA and integration reviewer`
- Last updated: `2026-11-08`
- Current focus: `Academy 036-044 verified; Task-046 dependency remains`
- Blocker: `Task-046 is not complete: retired test-offer/academy stage surfaces, pipeline filtering and several plural-responsibility UI surfaces remain.`
- Next action: `Complete Task-046, then run the remaining browser, build, migration-rehearsal and native-copy acceptance checks.`

## Evidence recorded
- Focused Academy suites: 50 tests / 324 assertions passed.
- Academy API route tree: exit 0, 22 routes.
- Print regressions: attendance 7/7, certificate 9/9, slip passed.
- Academy locale keys exist in all three frontend locales; Academy business codes exist in all three PHP catalogs.
- `npm run typecheck` exits 2 from known pre-existing files only; no Academy errors.
- `npm run i18n:check` exits 1 from pre-existing unrelated missing keys only.
- Not run: production build, browser viewport/RTL/light-dark pass, native Dari/Pashto review, disposable working-data migration rehearsal.

## Acceptance criteria progress
- [x] Focused backend suites and print regressions pass.
- [x] Academy route/nav wiring is present.
- [x] Academy locale/catalog parity for the new keys.
- [ ] Full frontend build and browser acceptance. Evidence: not run.
- [ ] Working-data migration/backfill rehearsal. Evidence: not run.
- [ ] Task-046 case-free stage and plural-responsibility acceptance. Evidence: blocked.
- [ ] Native-language review. Evidence: reviewer unavailable; limitation recorded.
