# Progress — Task-010

## Status snapshot
- Status: `Review`
- Owner: `Full-stack Laravel/Vue implementer`
- Last updated: `2026-10-31`
- Current focus: `Implementation and automated verification complete; browser/screen-reader and notification-bell checks remain.`
- Blocker: `None for the code. The 550 seeded system rows were not present in this repository, so their exact discriminator is assumed from nullable user_id.`
- Next action: `Review the diff and run the manual audit-page/notification checks listed under Handoff notes.`

## Acceptance criteria progress
- [x] Given a stage change from University Selection to University Application, when the audit row renders, then it shows the field label and both stage names, not numeric ids or "Other information". — Evidence: `AuditReadabilityTest::test_stage_changes_expose_field_and_stage_labels` (field `currentStageId`, field_label `Stage`, before/after and old/new labels both stage names). `AuditPresenter` resolves `current_stage_id` through a cached `ApplicantStage` lookup.
- [x] Given an audit entry for a known column, when it renders, then the field label comes from the audit/common translation and the raw column name is used only when no label exists. — Evidence: `fieldLabel(change)` in `audits.vue` checks `pages.audits.fields.*`, then `common.fields.*`, then `change.field_label`, then a humanized column name. The old "Other information" fallback was removed.
- [x] Given the audit detail, when it is opened, then actor, IP address, request method and timestamp are visible. — Evidence: the presenter now serializes `ip_address`, `method` and `url`; the dialog shows IP, method and the full timestamp beside the actor. `test_detail_includes_actor_ip_method_and_timestamp` passes.
- [x] Given the review queue contains seeded system rows, when the page opens, then genuine pending changes are distinguishable and can be viewed without the seeded rows, and no audit history is deleted. — Evidence: `GET /audit-logs` accepts `source=people|system`, filtered by nullable `user_id`; the page defaults to People and offers People/System/All. `test_system_rows_can_be_separated_from_human_changes` passes. No delete or scope rewrite is performed.
- [x] Given a stage change notification, when it is delivered, then it names the applicant, the from and to stages and the actor, and no generic "was changed" copy remains for this event. — Evidence: `applicantStageChanged()` builds both messages from the history's old/new stage and changer, and stores `from_stage`, `to_stage` and `actor` in the notification data. `test_stage_notification_names_the_applicant_stages_and_actor` passes; all three locales contain the three placeholders.
- [ ] Given a 1280 px viewport, when the audit table renders, then the change and actor columns are visible and record names wrap to a bounded number of lines without pushing actions off-screen. — Evidence: CSS clamps record names to two lines and change summaries to three, and makes the actions column sticky. NOT VERIFIED: no 1280 px browser render was executed.
- [x] Given undo/revert on an updated audit row, when it is submitted, then it still reverts the raw stored value exactly as before. — Evidence: the existing `AuditManagementTest::test_undo_restores_the_record_without_deleting_or_duplicating_the_audit` passes unchanged; labels are additive fields and raw before/after values remain in the payload.

## Work completed with evidence
- Extended `AuditPresenter` (the single serializer used by list, detail and heartbeat) with `field_label`, `old_label`, `new_label`, `ip_address`, `method` and `url`. Raw `field`/`before`/`after` remain for undo and forensics.
- Added cached foreign-key resolution for `current_stage_id`, `old_stage_id`, `new_stage_id` and `stage_id` to stage names, using the existing related-label cache (no per-row query loop).
- Added the `source` filter to `GET /audit-logs` (`people` = non-null `user_id`, `system` = null) and a default-People source toggle on the page with All and System available.
- Updated the audit detail to show IP address, request method and full timestamp; kept the actor and audit status.
- Removed the "Other information" fallback and made the page prefer translated field labels, then the API label, then a humanized raw field.
- Rewrote stage-change notifications to say `"{applicant} moved from {from_stage} to {to_stage} by {actor}."` (and the applicant copy) and added the same structured data for the notification UI.
- Bounded record/change columns with two/three-line clamps and made the action column sticky at 1280 px.

## Files changed and why
| File | Change |
|---|---|
| `app/Support/AuditPresenter.php` | Shared serializer: labels, stage resolution, IP/method/url. Not in the task's exclusive list but is the existing single serializer the contract requires. |
| `app/Http/Controllers/AuditLogController.php` | Validated `source` filter. |
| `app/Services/EducationalWorkflowNotificationService.php` | Stage notification from/to/actor copy and data. |
| `resources/ts/pages/audits.vue` | Field labels, source filter, detail context, bounded columns, sticky actions. |
| `resources/ts/api/auditLog.ts` | Typed change labels, request context and source parameter. |
| `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` | Audit source/field/context labels and stage notification placeholders. |
| `tests/Feature/AuditReadabilityTest.php` | New serialization/filter/detail/notification coverage (not in the exclusive ownership list). |

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l` on changed PHP files | Local | PASS | Terminal output | No syntax errors. |
| 2026-10-31 | `php artisan test --filter=AuditReadabilityTest` | Local, RefreshDatabase | PASS | Terminal output | 4 tests, 26 assertions. |
| 2026-10-31 | `php artisan test --filter=AuditManagementTest` | Local, RefreshDatabase | PASS | Terminal output | 7 tests; undo/review/scope regressions unchanged. |
| 2026-10-31 | `php artisan test --filter=RealtimeNotificationTest` | Local, RefreshDatabase | 8/9 PASS | Terminal output | Unrelated pre-existing failure: the contract-generation test helper sends `admission_employee_id` for `academy`, which `StoreServiceContractRequest` prohibits. Neither file was changed by this task. |
| 2026-10-31 | `npm run typecheck` | Local | PASS | Terminal output | No type errors. |
| 2026-10-31 | Locale path/copy check (all three locales) | Local | PASS | Terminal output | `pages.audits.*` labels and stage notification placeholders present. |
| 2026-10-31 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local | PASS | Terminal output | 20 tasks, 0 errors. |
| 2026-10-31 | `git diff --check` | Local | PASS | Terminal output | No whitespace errors. |
| 2026-10-31 | Browser at 1280 px, notification bell, screen-reader traversal | Browser | NOT RUN | — | Manual verification still required; no visual or accessibility pass is claimed. |

## Decisions and deviations
- Labels are resolved in `AuditPresenter`, not duplicated in Vue, so list, detail, heartbeat and future consumers share one reading. The task named an `AuditLogResource`, but the repository uses `AuditPresenter`; creating a second serializer would violate the task's one-serializer rule.
- Stage lookups use the presenter's per-request cache and the existing `AuditRegistry::subjectLabel`; raw ids are never removed from the audit payload.
- `source=system` means `user_id IS NULL`. The seeded 550 rows described by the task are not present in this repository, so this discriminator is an assumption to validate against the demo database. The filter is non-destructive and All remains available.
- Field labels prefer UI translations, then the API's English label, then a humanized column key. The "Other information" fallback is gone.
- Notification actor comes from the history's changer (falling back to the actor id, then System), and both stage names come from the history relations rather than re-querying inside the locale string.
- `AuditPresenter` and the notification service are shared files outside the four owned files; edits are additive and documented here. The new test file is also outside the exclusive list.

## Blockers and open questions
- Confirm the seeded rows' discriminator against the demo database. If they carry a system user id instead of a null `user_id`, adjust the `source` filter before calling the queue clean.
- `RealtimeNotificationTest::test_contract_generation_notifies_the_admission_employee` remains red for the unrelated academy/admission payload mismatch; it was not changed or weakened by this task.

## Handoff notes
- Ready for review; not marked Done.
- Manual checks: create a real stage change, confirm the table row shows "Stage · University Selection → University Application", open details for actor/IP/method/time, switch People/System/All, confirm the review queue count changes without deleting rows, and inspect the notification bell copy.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issue 11; implementation has not started.
- `2026-10-31 Asia/Kabul` Implemented audit labels/stage resolution, source filtering, detail context and stage notification copy; added tests. Automated checks pass except one unrelated contract-notification regression; moved to `Review`.
