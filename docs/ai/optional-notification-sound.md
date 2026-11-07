## Task: Make the notification sound optional from account notification preferences

- **Task ID:** optional-notification-sound
- **Created:** 2026-11-05
- **Last updated:** 2026-11-05
- **Status:** COMPLETED
- **Outcome:** The notification chime is off by default and a user can enable it in Account settings → Notification preferences; the choice is stored on their account and respected by both the heartbeat chime and the app-launch chime, while in-app notification delivery is unchanged.

### 1. Requirements and acceptance criteria
- [x] Account settings → Notification preferences shows a sound toggle next to the per-event in-app checkboxes.
- [x] Turning the toggle off and saving persists the preference on the user's account.
- [x] After saving, this browser session plays no notification chime (heartbeat or launch) without a reload.
- [x] A later login/refresh on any device reads the saved preference before the chime can play.
- [x] Turning the toggle back on restores the chime; the preference survives saving the event matrix without the sound field.
- [x] Notification rows are still delivered, listed and marked unread exactly as before; only audio is suppressed.
- [x] Saving the notification preferences actually reaches the server (see findings: the generic client validator currently rejects it).

### 2. Scope and non-goals
- In scope: `PUT /auth/account/notifications` payload/validation, `account_settings.notification_sound`, `Account` API type, chime gating in `useNotificationHeartbeat`, hydration at layout mount and account refresh, the notification preferences card, the client-side API validation schema for this one endpoint, en/fa-AF/ps-AF strings, one focused feature test.
- Out of scope: per-event sound preferences, a sound preview/test button, browser Notification API or push, notification frequency/email settings, service-worker or audio asset changes, websocket work.

### 3. Project findings
- The chime is played only from `resources/ts/composables/useNotificationHeartbeat.ts`: once per heartbeat batch and once at app launch through `playLaunchDingOnce`, which `NavBarNotifications.vue` calls after the initial inbox load.
- `NavBarNotifications` is mounted by `DefaultLayoutWithVerticalNav.vue`, `DefaultLayoutWithHorizontalNav.vue` and `ApplicantPortalLayout.vue`; it starts the heartbeat on mount.
- Preferences are stored per user in `users.account_settings` JSON. `AuthController::accountData()` merges `account_settings` at the top level of `/auth/me` (and the login/`me` payloads are written to the `userData` cookie), so a top-level `notification_sound` is available at startup without another request.
- `AuthController::updateNotifications()` requires `notifications` (array) with `notifications.*.in_app` booleans and replaces the whole `notifications` map. It does not accept any sound flag today.
- `AccountSettingsNotification.vue` renders a table of five event keys with `in_app` checkboxes, loads from `AuthApi.me()`, and saves through `AuthApi.updateNotifications()`.
- **Pre-existing bug confirmed by executing the validator:** `resources/ts/utils/apiValidation.ts` declares `notifications: ['required','array']` and `notifications.*.email: ['required','boolean']` for `PUT /auth/account/notifications`. The page posts a keyed object map, so the interceptor rejected every save with `notifications` ("Choose at least one item.") and `notifications.*` ("Required."). Fixing this endpoint's client schema is required for the new toggle (and the existing checkboxes) to save at all.
- `AccountSettingsAccount.vue` already updates the `userData` cookie after saving an account preference, which is the pattern to follow.

### 4. Graph and impact review
- Tools actually used: `graphify-out/graph.json` queried for nodes/edges around `AccountSettingsNotification`, `NotificationPreferences`, `AuthController::updateNotifications` and `Notification::deliverTo`; the checked-in graph predates `useNotificationHeartbeat`, so its missing edges were traced with `code_search`/`read_files` instead (fallback).
- Dependencies/callers examined: `AuthApi.updateNotifications` is called only by `AccountSettingsNotification.vue`; `playNotificationDing`/`playLaunchDingOnce` are called only by `useNotificationHeartbeat.ts` and `NavBarNotifications.vue`; `Notification::deliverTo` reads `notifications.<key>.in_app` and is unaffected by an extra top-level account setting.
- Downstream effects checked: `AccountSettingsAccount.vue` (cookie shape), `UserProfile.vue` (`AuthApi.me()` refresh point), `login.vue` (cookie write), `useAuditHeartbeat` (no audio), locale catalogs, the apiValidation schema.
- No other consumer of the notification preferences payload or of the chime exists.

### 5. Clarifications, decisions, and assumptions
- Open questions: none material. The request names account settings → notification preferences and "the notification sound", which is one global chime, not per-event audio.
- Decision: store the flag as `account_settings.notification_sound` (top level, next to `ui_scale`) rather than inside the event map, because `updateNotifications` replaces and validates the event map wholesale and the UI iterates it as events.
- Decision: default is sound **off** (opt-in). `accountData()` reports `notification_sound: false` when no preference is stored, and every client read treats only `true` as enabled.
- Decision: gate audio only. `Notification::deliverTo`, the inbox, unread flags and `useForegroundBatch` stay untouched.
- Decision: fix the client validation schema to `notifications: ['required']` plus `notification_sound: ['boolean']`; per-event booleans cannot be checked by the generic validator (it cannot traverse an object map with `*`) and remain server-validated as before.
- Assumption: the `userData` cookie is an acceptable startup source for the preference; the existing `AuthApi.me()` refresh in `UserProfile.vue` reapplies the authoritative server value.

### 6. Implementation plan
- [x] `app/Http/Controllers/AuthController.php` — accept optional `notification_sound` (boolean), persist it without clearing it when omitted, keep the existing notification-frequency cleanup.
- [x] `resources/ts/api/auth.ts` — add `notification_sound?: boolean` to `Account` and the update payload type.
- [x] `resources/ts/composables/useNotificationHeartbeat.ts` — module-level enabled flag + `setNotificationSoundEnabled()`; skip both chime calls when disabled.
- [x] `resources/ts/views/pages/account-settings/AccountSettingsNotification.vue` — sound switch with load/save/discard semantics, live flag update and `userData` cookie update.
- [x] `resources/ts/layouts/components/NavBarNotifications.vue` and `UserProfile.vue` — hydrate the flag from the cookie before polling and from the refreshed account payload.
- [x] `resources/ts/utils/apiValidation.ts` — make the client schema match the endpoint so preferences can be saved.
- [x] `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` — add `notificationPreferences.sound` and `soundDescription`.
- [x] `tests/Feature/AccountNotificationPreferencesTest.php` — persistence, survival when omitted, boolean validation.
- [x] `AGENTS.md` — note that the chime is user-disableable and delivery is unaffected.

### 7. Validation plan and results
- [x] `php artisan test --filter=AccountNotificationPreferencesTest` — endpoint persistence/validation.
- [x] `npm run typecheck` — Vue/TS changes.
- [x] Executed `validateApiRequest` payload check for `PUT /auth/account/notifications` — must return no errors for the exact page payload.
- [x] Locale JSON parse check for all three catalogs.
- Results:
  - `php -l` on `AuthController.php` and the new test: no syntax errors.
  - `php artisan test --filter=AccountNotificationPreferencesTest`: 4 passed (12 assertions), including the disabled-by-default case.
  - `npm run typecheck`: exit 0.
  - Executed `validateApiRequest` with the exact page payload: no errors. A non-boolean `notification_sound` is rejected. All three catalogs expose `notificationPreferences.sound` and `notificationPreferences.soundDescription`.
- Checks not run and why: no browser session was driven, so the switch was not visually inspected in RTL/layouts and the audio gate was not listened to. The chime gate is a module-level boolean with no automated audio test.

### 8. Risks and mitigations
- Existing accounts with no key — the chime is now off by default; `accountData()` reports `false` and the client reads `=== true`.
- Sound disabled but user expects unread behavior — audio-only gate; delivery untouched and covered by the existing notification tests.
- Stale `userData` cookie after saving — update the cookie on save and reapply on `AuthApi.me()`.
- Removing client validation rules could mask bad input — the server still validates `notifications.*.in_app` and `notification_sound`; only the broken client rules are replaced.
- Locale files already carry unrelated working-tree edits — insert only inside the top-level `notificationPreferences` block.

### 9. Progress log
- 2026-11-05 — DISCOVERY: traced the chime, heartbeat, account settings page, `account_settings` storage, `accountData()` shape, cookie hydration, layouts that mount `NavBarNotifications`, locale catalogs and graph edges. Confirmed by execution that the existing notification-preferences save is rejected client-side.
- 2026-11-05 — PLAN: record written before implementation.
- 2026-11-05 — PROGRESS: implemented the controller flag, API type, chime gate, cookie/account hydration, settings switch, client-validation fix, translations and feature test.
- 2026-11-05 — VALIDATION: `php artisan test --filter=AccountNotificationPreferencesTest` passed 3 tests / 10 assertions; `npm run typecheck` exit 0; executed validator and locale checks passed; `php -l` clean.
- 2026-11-05 — FOLLOW-UP: moved the sound switch beside its title/description on the leading side (left of the text in LTR, right in RTL) and changed the chime default to off (opt-in), including the served `notification_sound` value and all client reads.

### 10. Completion summary
- Delivered: `account_settings.notification_sound` accepted by `PUT /auth/account/notifications` (optional, preserved when omitted), a sound switch in Account settings → Notification preferences, a session-wide audio gate in `useNotificationHeartbeat`, startup hydration from the `userData` cookie and `AuthApi.me()`, corrected client validation for this endpoint, en/fa-AF/ps-AF strings, a feature test, and an AGENTS.md note.
- Acceptance criteria satisfied/not satisfied: all satisfied; delivery, unread flags and the inbox are untouched because only the two chime calls are gated.
- Tests/checks passed: focused PHP feature test (4 passed, 12 assertions), `npm run typecheck`, PHP syntax lint, one-off validator/locale checks.
- Remaining limitations or follow-up work: the switch has not been visually reviewed in a running browser (LTR/RTL, light/dark); no automated assertion covers the actual audio element or the persisted browser cookie.
