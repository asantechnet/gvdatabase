# Google Drive Storage Integration — Plan

**Status:** Planned. Nothing implemented yet.
**Source request:** `docs/Educational New Comments++.md`
> "Google drive connection would be cool in educational DB. there would be a section in settings for storage where the user could link their google drive account to the system and from then on the system would use that drive as storage."

---

## 0. Decisions log

| # | Question | Decision |
|---|----------|----------|
| 1 | Where do private documents live once Drive is linked? | **Local disk is primary; Drive receives a mirror.** Every upload is written locally first, then synced. |
| 2 | Which Google access is requested? | **`drive.file`** — the app creates and fully owns one root folder and can only ever see files it created itself. |
| 3 | What happens to files already stored locally? | **Backfill them into Drive**, tracked per file, with the mapping persisted so records can be served or restored from Drive. |
| 4 | Account model | **One organisation-level connection** linked from Settings (per the request's wording). Per-user drives are explicitly out of scope. |

### Reconciling decision 1 with decision 3

Decision 1 says local stays authoritative; decision 3 says existing records get repointed. These are reconciled as follows, and this is the behaviour the implementation must follow:

- Records keep their current `disk = 'local'` value and continue to be served from local — **reads never get slower or more fragile because Drive is linked.**
- A new `google_drive_files` mapping table records `(disk, path) → drive_file_id + checksum + mirrored_at`. This is what "repoint" means in mirror mode: the record now has a durable second location and a documented restore path.
- The download resolver only falls through to Drive when the local file is **missing** (or when an explicit `--promote` is run). Flipping a record's `disk` to `google_drive` is an opt-in escape hatch, not the default, because a blanket flip would make every document read depend on Google's availability — the exact failure mode decision 1 was chosen to avoid.

If a true Drive-primary install is wanted later, `google_drive_connections.mode` is reserved for it (`mirror` | `primary`) and the resolver already reads it. This is deliberately left unimplemented so there is one code path to get right first.

---

## 1. Verdict

Suitable — the app is document-heavy (applicant files, employee documents, expense documents, receipt and contract snapshots, task and support-ticket attachments, managed files), so durable off-site storage is a real benefit rather than decoration. The codebase is also unusually well prepared:

- All four polymorphic/dedicated file tables already carry a `disk` column, so no record is hard-wired to a single backend.
- Private uploads funnel through a small number of controllers.
- Settings already has a multi-page pattern with a sidebar-driven `SettingsPage` union, so a "Storage" page is a natural addition.

**One carve-out:** the branding artwork stays on the local `public` disk. `website_logo`, `company_signature`, contract/receipt/payslip letterheads, contract document designs and avatars are rendered in the browser and in print — `SettingResource` emits `Storage::url(...)` and avatars are stored as literal `/storage/avatars/…` strings. Drive has no hotlinkable public URL, so moving them would mean proxying every image and rewriting stored URLs. They are **mirrored for backup only**, never repointed.

**Operational caveats that shape the design:**

| Caveat | Consequence |
|---|---|
| A `Testing`-mode OAuth consent screen expires refresh tokens after 7 days | Must publish the app (`drive.file` is non-sensitive, so no verification review) or use an `Internal` app inside a Workspace org. The UI must detect and surface `needs_reauth`. |
| A personal Google account as the org's storage is a single point of failure | Recommend a dedicated company account or Shared Drive. Recovery procedure must be documented. |
| Drive is not a filesystem | Per-file API calls, rate limits, no atomic moves, no public URLs. Needs retry/backoff, folder-id caching, and a local copy that survives a failed upload. |

---

## 2. Scope

**In scope**

- Storage settings page with connect / verify / re-sync / disconnect.
- OAuth 2.0 + PKCE linking flow with `drive.file`.
- A `google_drive` filesystem disk backed by a first-party Flysystem adapter.
- Automatic mirroring of every new private upload.
- Backfill of all existing private files into a mirrored folder tree.
- Per-file mapping, verification and reconciliation.
- RBAC permission, audit logging, i18n (en / fa / ps), RTL + LTR, light + dark, responsive.

**Out of scope (deliberately)**

- Moving or repointing branding artwork and avatars (mirror-only).
- Per-user Google Drives.
- Drive-primary serving mode (reserved via `mode`, not built).
- Google Docs format conversion; files are stored and served byte-for-byte.
- Any revival of invoicing, counterparty companies, commission/outsourcing compatibility aliases.
- Multi-provider abstraction (S3/OneDrive). The connection table is provider-scoped (`provider_key`) so it can be added without a migration, but only Google is built.

---

## 3. Current storage surface (audit)

Verified against the codebase. This is the complete set of writes that must be covered.

| Domain | Writer | Disk | Path pattern | Row stores disk? |
|---|---|---|---|---|
| Managed files | `FileController` | `local` | `managed-files/{folder}/{uuid}.{ext}` | n/a (path only) |
| Applicant files | `ApplicantAttachmentController` | `local` | `applicant-attachments/{applicant_id}/{uuid}.{ext}` | yes — `applicant_attachments.disk` |
| Applicant portal files | `ApplicantPortalController` | `local` | `applicant-attachments/{applicant_id}/{uuid}.{ext}` | yes |
| Task files | `TaskAttachmentController` | `local` | `task-attachments/{task_id}/{uuid}.{ext}` | yes — `task_attachments.disk` |
| Expense / income / staff files | `AttachmentController` | `local` | `attachments/{type}/{id}/{uuid}.{ext}` | yes — `attachments.disk` |
| Support tickets | `SupportTicketController` | `local` | `support-tickets/{ticket_id}/{uuid}.{ext}` | yes — `support_ticket_attachments.disk` |
| Employee documents | `EmployeeController` | `local` | `employee-documents/{employee_id}/{uuid}.{ext}` | **no** — hardcoded `local`, `document_path` only |
| Expense documents | `ExpenseController` | `local` | `expense-documents/{expense_id}/{uuid}.{ext}` | **no** — hardcoded `local`, `document_path` only |
| Company branding | `SettingController` | `public` | `logos/`, `signatures/`, `contract-letterheads/`, `contract-cover-designs/`, `receipt-letterheads/`, `payslip-letterheads/` | n/a (mirror only) |
| Contract designs | `ContractDocumentDesignController` | `public` | `contract-designs/{uuid}.{ext}` | n/a (mirror only) |
| Avatars | `AuthController` | `public` | `avatars/{uuid}.{ext}` → stored as `/storage/...` | n/a (mirror only) |

**Two rows are missing a `disk` column** (`employees`, `expenses`); both have already been copied into the shared `attachments` table by `2026_10_03_000001_create_attachments_table.php`, which remains the canonical location. The legacy single-file columns are left untouched.

---

## 4. Target architecture

### 4.1 Request flow (mirror mode)

```
upload → local disk (authoritative, synchronous, unchanged latency)
       → row inserted with disk = 'local'
       → MirrorDocumentToDrive job queued
                     ↓
       resolve logical folder by id from cache (create if missing)
       upload (multipart < 5 MB, resumable ≥ 5 MB)
       attach appProperties { app, disk, path, checksum }
       upsert google_drive_files row → status = synced
```

Reads are untouched. A read only consults Drive when the local file has disappeared:

```
GET document → local file exists? → serve local
             → missing → google_drive_files row for (disk, path)? → stream from Drive + repopulate local cache
             → neither → 404 resource.file_not_found
```

This keeps the 99% path local, makes Drive a genuine durable copy, and leaves a working application when Google is unreachable.

### 4.2 New components

| Component | Responsibility |
|---|---|
| `App\Support\Storage\GoogleDrive\GoogleDriveClient` | OAuth token exchange/refresh, Drive v3 REST calls, retry/backoff, rate limiting. Built on `Illuminate\Support\Facades\Http` — **Guzzle already ships with `laravel/framework ^12`, so no new composer dependency is introduced.** |
| `App\Support\Storage\GoogleDrive\GoogleDriveAdapter` | `League\Flysystem\FilesystemAdapter` implementation so `Storage::disk('google_drive')` behaves like any other disk. |
| `App\Support\Storage\GoogleDrive\GoogleDriveFolderSchema` | Single source of truth for the folder tree: logical path → Drive folder id, name sanitising, ensure-exists with caching. |
| `App\Support\Storage\DriveMirror` | Orchestrates mirroring one file: skip-if-unchanged by checksum, upload, verify, record, retry bookkeeping. |
| `App\Support\Storage\StorageConnection` | Reads/validates the active connection, refreshes tokens on demand, exposes `mode`, `isConnected()`, `needsReauth()`. |
| `App\Support\Storage\DocumentResolver` | "Serve from local, fall back to Drive, cache back" resolution used by every download endpoint. |
| `App\Providers\StorageServiceProvider` | Lazily registers the `google_drive` disk; throws a clear, typed exception when not connected. |

`GoogleDriveClient` notes:

- Access tokens are refreshed transparently when expiry is within 60 s; a failed refresh (400/401 on `refresh_token`) flips the connection to `needs_reauth`, fires a notification, and never breaks a local write.
- Retry with exponential backoff + jitter on `429`, `403 rateLimitExceeded`, `5xx`; honours `Retry-After`. Max 5 attempts, then `status = failed` with `last_error` recorded.
- A client-side throttle keeps concurrency at the documented ceiling rather than discovering limits by failing.

`GoogleDriveAdapter` notes: implement the full Flysystem v3 interface — `fileExists`, `directoryExists`, `write`/`writeStream`, `read`/`readStream`, `delete`, `deleteDirectory`, `createDirectory`, `listContents` (paginated `files.list`), `move` (parent patch), `copy` (`files.copy`), `lastModified`, `fileSize`, `mimeType`. Visibility is not meaningful in Drive (`drive.file` scope, app-owned files) so visibility methods report `NotSupportedException`/`private` rather than lying; there is no `publicUrl`, which is precisely why branding artwork stays local.

### 4.3 Drive folder schema

Created once, on connect, under one app-owned root. Mirrors the local layout so the two are reconcilable by eye.

```
Educational DB/
└── {short_company_name}/
    ├── Applicants/{applicant_id - full_name}/Documents/
    ├── Employees/{employee_id - name}/Documents/
    ├── Contracts/{contract_number}/
    ├── Incomes/Receipts/{yyyy}/{receipt_number}/
    ├── Expenses/{yyyy}/{expense_id}/
    ├── Tasks/{task_id}/
    ├── Support Tickets/{ticket_id}/
    ├── Managed Files/{folder}/
    └── Company/Branding/{Logos,Signatures,Letterheads,Contract Designs,Avatars}/
```

Rules:

- **Everything resolves by id from `google_drive_folders`, never by name lookup.** Drive permits duplicate names, so name-based resolution is a correctness bug waiting to happen.
- Folder names are sanitised (strip `/ \ : * ? " < > |`, collapse whitespace, cap at 100 chars) and truncated with the numeric id retained in the name for disambiguation.
- A rename on our side does **not** rename the Drive folder. Updating `google_drive_folders.name` on demand is possible but chasing renames adds API calls for no functional gain; the folder id is the contract. A "Refresh folder names" maintenance action can be added later if it is ever wanted.
- Every uploaded file carries `appProperties = { app: 'educational-db', disk, sourcePath, checksum }`. Because `drive.file` only exposes app-created files, reconciliation and orphan detection can rely on these properties rather than on a scan of the user's Drive.

### 4.4 Data model

**`google_drive_connections`**

| Column | Notes |
|---|---|
| `id`, `provider_key` (`google_drive`) | Provider-scoped from day one. |
| `connected_by` | `user_id` → `nullOnDelete`. |
| `status` | `connected` \| `needs_reauth` \| `disconnected`. |
| `account_email`, `account_name`, `account_avatar_url` | Shown on the Storage page. |
| `access_token`, `refresh_token` | **`encrypted` casts** (same pattern as `settings.mail_password`). |
| `token_expires_at`, `scopes`, `token_type` | Refresh scheduling + scope drift detection. |
| `root_folder_id`, `root_folder_name`, `company_folder_id` | The app-owned tree root. |
| `mode` | `mirror` (default) \| `primary` (reserved, unused). |
| `last_verified_at`, `last_synced_at`, `last_error` | Health surface. |
| `quota_bytes`, `quota_used_bytes`, `quota_checked_at` | Quota bar; also the early-warning signal before uploads start failing. |
| timestamps | Single active connection enforced in the model + a partial unique index. |

**`google_drive_folders`** — `id`, `logical_path` (unique), `name`, `drive_folder_id`, timestamps.

**`google_drive_files`** — `id`, `disk`, `path`, `drive_file_id`, `drive_folder_id`, `checksum`, `size`, `mime_type`, `status` (`pending` \| `synced` \| `failed` \| `orphaned`), `attempts`, `last_error`, `mirrored_from` (`upload` \| `backfill`), `mirrored_at`, `verified_at`, timestamps. Unique `(disk, path)`; index on `status`.

Migrations (latest existing is `2026_10_12_000001`):

- `2026_10_12_000100_create_google_drive_connections_table.php`
- `2026_10_12_000101_create_google_drive_folders_table.php`
- `2026_10_12_000102_create_google_drive_files_table.php`
- `2026_10_12_000103_seed_storage_settings_permission.php`

**Deliberate non-decision:** connection tokens are *not* stored on the `settings` table. That table is `Auditable` and serialised through `SettingResource`; tokens there would risk both audit-log leakage and accidental API exposure. A dedicated model, excluded from `Auditable`, with tokens hidden from all resources, is the safe shape.

### 4.5 OAuth and token lifecycle

- Scope: `https://www.googleapis.com/auth/drive.file` only.
- Authorization Code flow with **PKCE (S256)** plus a single-use, 10-minute, server-side `state` bound to the initiating user id. PKCE is what makes an intercepted code useless; `state` is what makes the flow non-forgeable.
- `GET /api/storage/google-drive/connect` (authenticated, `manage_storage_settings`) returns the consent URL. The SPA opens it in the same tab.
- Google redirects the browser to `{APP_URL}/api/storage/google-drive/callback` — a browser redirect carries no bearer token, so this route lives in the **public** route group with `throttle:10,1`. It is protected by the single-use state plus the PKCE verifier, both stored server-side. On success it redirects to `/setup/settings/storage?connected=1`; on failure `/setup/settings/storage?error=<code>`.
- Tokens are encrypted at rest, never returned by any endpoint (the resource exposes `connected`, `account_email`, expiry and scope only).
- Refresh happens transparently 60 s before expiry. A revoked/expired refresh token sets `needs_reauth`, notifies admins, and leaves every local file readable.
- `disconnect` deletes the token material, marks the connection `disconnected`, and **keeps both the local files and the Drive tree** — disconnecting must never destroy data. Deleting the Drive folder is a separate, explicitly typed confirmation action.

### 4.6 Security

| Concern | Control |
|---|---|
| Token theft from the DB | `encrypted` casts; tokens excluded from resources, audit logging and error payloads. |
| CSRF / code injection on the callback | Single-use server-side `state` + PKCE S256 + `throttle:10,1`. |
| Over-broad access | `drive.file` — the app can only see files it created; it cannot enumerate a user's Drive. |
| Who can link/unlink | New permission `manage_storage_settings`; `view_settings` still governs viewing status. Route-level `permission:` middleware, matching every neighbouring settings route. |
| Accountability | Connection and sync state changes written to the audit log (excluding secrets), plus notifications on auth failure and quota exhaustion. |
| Path traversal on the fallback download | Reuse `FileController::validatedPath` semantics; no Drive-supplied name is ever trusted for a filesystem path. |
| Data residency / consent | The Storage page states plainly what is uploaded, where it lives, and that it is the company's Google account — the operator must be able to consent knowingly. |

### 4.7 Reliability and failure modes

| Failure | Behaviour |
|---|---|
| Queue worker not running | `drive:mirror --pending --limit=200` is scheduled every five minutes (`withoutOverlapping()->onOneServer()`, matching `routes/console.php` conventions), so mirroring converges regardless. `GOOGLE_DRIVE_SYNC_MODE=inline` runs small files inline with a time budget and defers the rest. |
| Google unreachable during upload | Local write already succeeded — the user is never blocked. The job retries with backoff; the file shows as `pending`. |
| Token revoked / expired | `needs_reauth` banner on the Storage page; local documents keep working; no data loss. |
| Quota exhausted | `403 storageQuotaExceeded` is detected, surfaced with the exact Drive message and the quota bar, and mirroring pauses instead of retrying forever. |
| Local file deleted but mirrored | Download falls back to Drive and repopulates the local copy. |
| Drive file deleted out of band | `drive:reconcile` marks the mapping `orphaned`; the local file is untouched and re-mirrorable. |
| Duplicate folder names | Never resolved by name — id-based resolution only. |
| Interrupted backfill | Batched, idempotent, resumable by `(disk, path)`; re-running skips already-synced files by checksum. |

---

## 5. Backend work breakdown

### Phase 1 — Foundation

1. Migration set (§4.4) + `GoogleDriveConnection`, `GoogleDriveFile`, `GoogleDriveFolder` models with relations, casts and `$hidden` on secrets.
2. `config/services.php` → `google` block; `config/filesystems.php` → `google_drive` disk definition.
3. `.env.example`: `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_REDIRECT_URI`, `GOOGLE_DRIVE_SYNC_MODE`, `GOOGLE_DRIVE_BATCH_SIZE`.
4. `StorageServiceProvider` registering the disk lazily; `StorageConnection`; typed `StorageConnectionMissingException`.
5. `manage_storage_settings` permission (category `core`) seeded and granted to `super_admin` + `admin`, following `2026_10_07_000013_seed_contract_payment_override_permission.php` exactly (`insertOrIgnore` + role grants; `down()` intentionally a no-op comment).
6. Backend message codes in `lang/{en,fa,ps}/api.php`, resolved through the existing `ApiMessage::forCode` contract.

### Phase 2 — Drive client and adapter

7. `GoogleDriveClient`: consent URL with PKCE + state, code redemption, refresh, `files.list/get/create/copy/delete/update`, multipart and resumable upload, `about.get` for quota.
8. `GoogleDriveFolderSchema` with id caching and sanitisation.
9. `GoogleDriveAdapter` (Flysystem v3) and its contract tests.

### Phase 3 — Connection lifecycle

10. `StorageConnectionController`: `show`, `connect`, `callback`, `disconnect`, `verify`, `folders`.
11. `StorageConnectionResource` — never exposes tokens.
12. Routes in `routes/api.php` (§7). Callback placed in the public group.
13. Notifications for `needs_reauth` and quota exhaustion.

### Phase 4 — Mirroring

14. `DriveMirror` + `App\Jobs\MirrorDocumentToDrive` (`ShouldQueue`; this introduces the repo's first queued job — flagged as a prerequisite in §13).
15. `DocumentResolver`, and the wiring of every writer in §3 to mirror after a successful local write:
    - `FileController`, `ApplicantAttachmentController`, `ApplicantPortalController`, `TaskAttachmentController`, `AttachmentController`, `SupportTicketController`, `EmployeeController`, `ExpenseController`.
    - Mirror-only (never repointed): `SettingController`, `ContractDocumentDesignController`, `AuthController`.
16. Deletion propagation: when a record is deleted, the mapped Drive file is trashed — never the local file's mirror while the local file still exists.
17. `FileController`/download endpoints switch to `DocumentResolver` so a missing local file is served from Drive with a repopulated cache. `employees.document_path` and `expenses.document_path` are normalised to `(disk = 'local', path)` through the resolver so they participate without a schema change.

### Phase 5 — Backfill and maintenance

18. `php artisan drive:verify`
19. `php artisan drive:mirror --pending --limit=200`
20. `php artisan drive:backfill [--from=<domain>] [--limit=100]`
21. `php artisan drive:reconcile [--prune-orphans]` (dry-run by default, matching the repo's careful default)
22. Scheduler entry in `routes/console.php`.
23. `DriveSyncController`: `summary`, `start` (batched, resumable), `progress`, `cancel`, `retry`.

Backfill order: folders and Branding → applicant attachments → employee/expense documents → shared attachments → task attachments → support tickets → managed files. Throttled by batch size and a client-side rate limiter; Drive's 750 GB/day/user upload ceiling and its per-60 s query ceilings are the binding constraints. `docs/scheduled-jobs.md` should be updated alongside.

---

## 6. Frontend work breakdown

1. `resources/ts/api/storage.ts` — typed contract. Unwraps `response.data.data` **in the API layer only**, per the project's API convention, modelled on `api/incomes.ts`.
2. `resources/ts/components/setup/StorageSettingsPanel.vue` — the panel, embedded the same way `OfficeSettingsPanel.vue` is.
3. `resources/ts/pages/setup/settings.vue` — add `'storage'` to the `SettingsPage` union, the `settingsPages` list (`tabler-cloud` / `tabler-brand-google-drive`), `pageFields.storage = []`, the template branch, and keep the footer/`VDivider` exclusion list correct (the office page is excluded from the save bar; storage must be too since it has no `settings` fields).
4. `resources/ts/navigation/horizontal/setup.ts` — no change needed (settings is one nav entry); the Storage page is reached through the settings sidebar.

Panel states:

- **Not connected** — what gets uploaded, what does not (branding stays local), the single `drive.file` scope explained in plain language, "Connect Google Drive".
- **Connecting** — spinner, cancel.
- **Connected** — account chip (name/email/avatar), root folder name + "Open in Drive", mode badge "Local primary · Drive mirror", quota bar, last sync time, buttons: Verify access, Sync now, Disconnect.
- **Needs re-auth** — prominent banner with the 7-day-testing-mode explanation and a reconnect CTA.
- **Sync panel** — counters (total / mirrored / pending / failed), determinate progress with `aria-live="polite"`, failure list with per-row retry, cancel.

Interface requirements (project conventions): RTL **and** LTR using logical properties only (`ms-*`/`me-*`, never `ml-*`/`mr-*`); light **and** dark via Vuetify theme tokens (`text-medium-emphasis`, `variant="tonal"`, no hardcoded hex); responsive `VCol` breakpoints for mobile/tablet/desktop; keyboard-reachable controls with visible focus; `vue-toastification` for toasts (never the legacy library); every new class-based icon must be a real `tabler-*` token picked up by `npm run build:icons` — `icons.css` is generated and must not be hand-edited; dynamic or runtime-sourced names need `icon-allowlist.json` or the remote `@iconify/vue` `<Icon>`.

---

## 7. API surface

All under the existing `auth:sanctum` + `abilities:access` + `active` + `edit-window` group unless noted.

| Method | Path | Handler | Permission |
|---|---|---|---|
| GET | `/storage/google-drive` | `show` | `view_settings` |
| GET | `/storage/google-drive/connect` | `connect` | `manage_storage_settings` |
| GET | `/storage/google-drive/callback` | `callback` | **public**, `throttle:10,1`, state + PKCE guarded |
| POST | `/storage/google-drive/disconnect` | `disconnect` | `manage_storage_settings` |
| POST | `/storage/google-drive/verify` | `verify` | `manage_storage_settings` |
| GET | `/storage/google-drive/folders` | `folders` | `view_settings` |
| GET | `/storage/sync/summary` | `summary` | `view_settings` |
| POST | `/storage/sync` | `start` | `manage_storage_settings` |
| GET | `/storage/sync/progress` | `progress` | `view_settings` |
| POST | `/storage/sync/cancel` | `cancel` | `manage_storage_settings` |
| POST | `/storage/sync/retry` | `retry` | `manage_storage_settings` |

---

## 8. i18n

- Frontend: new `pages.settings.storage.*` keys in `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json`.
- Backend: new codes in `lang/{en,fa,ps}/api.php` — `storage.drive_not_connected`, `storage.insufficient_scope`, `storage.quota_exceeded`, `storage.sync_already_running`, `storage.file_not_found`, `storage.reauth_required`, `storage.invalid_state`.
- No hardcoded English strings in the panel or the controller responses.

---

## 9. Print and document impact

Documents and printing are high-importance in this project, so this plan explicitly changes nothing about them:

- Receipts (A5), payslips (A5) and contracts (A4) continue to render from local `public` artwork and inline data. No print path reads from Drive.
- If a mirrored document must be printed, it is fetched through the local-or-Drive download proxy, which repopulates local first — so the print host always loads a same-origin URL well before the print job starts.
- No new `@page` rules, no new global `@media print` selectors, no changes to `utils/printDocument.ts` or to the namespaced print-mode body classes.
- Regression gate: the contract-print and receipt/payslip layouts must still render identically after any Phase 4 wiring.

---

## 10. Google Cloud setup (owner: operator)

1. Create a project in Google Cloud Console.
2. Enable the **Google Drive API**.
3. Configure the OAuth consent screen. Choose `Internal` if the org is on Workspace (best — no expiry trap, no review). Otherwise choose `External` and **publish** the app: `drive.file` is non-sensitive, so publishing needs no verification review, and it avoids the 7-day refresh-token expiry that `Testing` mode imposes.
4. Create an **OAuth client ID** of type *Web application*.
5. Authorised redirect URI: `{APP_URL}/api/storage/google-drive/callback`.
6. Put the client ID, client secret and redirect URI into `.env` (§5.3).
7. Recommended: use a dedicated company Google account or a Shared Drive, not a personal Drive.

**Prerequisite owned by the operator: the OAuth client credentials and the account choice.** Everything else can be built and tested against `Http::fake()` without them; the end-to-end smoke test cannot.

---

## 11. Testing strategy

Automated (no live Google required — `Http::fake()` + `Storage::fake()`):

| Test | Covers |
|---|---|
| `GoogleDriveClientTest` | Consent URL contains PKCE challenge + state; token exchange; refresh before expiry; 429/5xx backoff; revoked token → `needs_reauth`. |
| `GoogleDriveAdapterTest` | Full Flysystem contract: write/read/exists/delete/listContents/createDirectory/readStream, plus the "not connected" exception. |
| `DriveFolderSchemaTest` | Path building, name sanitisation, id caching, duplicate-name safety. |
| `DriveMirrorTest` | Checksum skip, idempotent re-mirror, failure recording, retry bookkeeping. |
| `DocumentResolverTest` | Local hit; local miss → Drive fallback → cache repopulation; neither → 404. |
| `StorageConnectionTest` | show/connect/callback happy path; bad state; PKCE mismatch; throttle; disconnect wipes tokens and keeps files; permission gates. |
| `StorageSyncTest` | start/progress/cancel/retry, batch limits, resumability. |

Manual smoke checklist against a real Drive: connect → confirm the folder tree exists → upload one file per domain → confirm each lands in the right folder with the right `appProperties` → download → delete locally → confirm the Drive fallback serves it → disconnect → confirm local documents still work.

Regression: `SupportTicketTest`, `SettingsEmailAndFileRemovalTest`, `CompanySettingsTest`, `ContractDocumentDesignTest`, plus `php artisan test` and `npm run typecheck` (never bare `vue-tsc` or `tsc`).

---

## 12. Rollout and rollback

**Rollout**

1. Ship Phases 1–3 with mirroring disabled (connection works, nothing is uploaded).
2. Enable Phase 4 for new uploads; watch `drive:reconcile` for a few days.
3. Run the Phase 5 backfill in batches, starting with one domain, verifying the folder tree by eye before widening.
4. Only then consider `mode = 'primary'` (currently unbuilt).

**Rollback**

- Disconnect: tokens are wiped, files stay on both sides, the app is fully local again. No schema rollback needed and no local file is touched.
- The mapping tables are inert when disconnected; they can be dropped later without losing a single document.
- No migration in this plan mutates or repoints existing file rows, so a failed rollout cannot orphan a record.

---

## 13. Risks and open items

| # | Risk / item | Mitigation / owner |
|---|---|---|
| 1 | OAuth client credentials do not exist yet | Operator (§10). Blocks the live smoke test only. |
| 2 | Consent screen left in `Testing` → refresh token dies after 7 days | Publish the app or use `Internal`; `needs_reauth` banner detects it instead of failing silently. |
| 3 | The repo currently dispatches **no** queued jobs; `database` is the default queue connection but nothing proves a worker runs in production | Scheduler `drive:mirror --pending` every 5 minutes as the convergence guarantee; `GOOGLE_DRIVE_SYNC_MODE=inline` escape hatch; confirm a worker is running as part of rollout. |
| 4 | Dedicated-account dependency | Document the recovery path (re-link with the same or a new account, then `drive:backfill`) and recommend a Shared Drive. |
| 5 | Drive quotas and rate limits | Backoff + client-side throttle + quota bar + pause-on-exhaustion; batched, resumable backfill. |
| 6 | Renames on our side not reflected in Drive folder names | Documented: folder id is the contract, not the name. Optional "refresh folder names" maintenance action. |
| 7 | `employees` / `expenses` lack a `disk` column | Normalised through the resolver; canonical copies already live in `attachments`. No schema change. |
| 8 | DB growth of the mapping table | Unique `(disk, path)` index, status index, prune of `orphaned` rows in `drive:reconcile`. |
| 9 | Mirroring silently stops (worker down, token dead) | `last_synced_at` + pending counter on the Storage page; pending backlog is the visible health signal. |

---

## 14. Progress tracker

Everything below is **not started**.

### Phase 1 — Foundation
- [ ] Migrations: connections, folders, files, permission
- [ ] Models: `GoogleDriveConnection`, `GoogleDriveFolder`, `GoogleDriveFile` (encrypted casts, hidden secrets)
- [ ] `config/services.php` + `config/filesystems.php` + `.env.example`
- [ ] `StorageServiceProvider`, `StorageConnection`, typed exception
- [ ] `manage_storage_settings` permission + grants
- [ ] Backend message codes in `lang/{en,fa,ps}/api.php`

### Phase 2 — Drive client and adapter
- [ ] `GoogleDriveClient` (OAuth + Drive v3 + retry/backoff + rate limit)
- [ ] `GoogleDriveFolderSchema`
- [ ] `GoogleDriveAdapter` (Flysystem v3)
- [ ] `GoogleDriveClientTest`, `GoogleDriveAdapterTest`, `DriveFolderSchemaTest`

### Phase 3 — Connection lifecycle
- [ ] `StorageConnectionController` (show/connect/callback/disconnect/verify/folders)
- [ ] `StorageConnectionResource`
- [ ] Routes in `routes/api.php` (callback in the public group)
- [ ] Re-auth / quota notifications
- [ ] `StorageConnectionTest`

### Phase 4 — Mirroring
- [ ] `DriveMirror` + `MirrorDocumentToDrive` job
- [ ] `DocumentResolver` + download fallback
- [ ] Wire `FileController`, `ApplicantAttachmentController`, `ApplicantPortalController`, `TaskAttachmentController`, `AttachmentController`, `SupportTicketController`, `EmployeeController`, `ExpenseController`
- [ ] Mirror-only wiring: `SettingController`, `ContractDocumentDesignController`, `AuthController`
- [ ] Deletion propagation
- [ ] `DriveMirrorTest`, `DocumentResolverTest`

### Phase 5 — Backfill and maintenance
- [ ] `drive:verify`, `drive:mirror`, `drive:backfill`, `drive:reconcile`
- [ ] Scheduler entry in `routes/console.php` + `docs/scheduled-jobs.md`
- [ ] `DriveSyncController` (summary/start/progress/cancel/retry)
- [ ] `StorageSyncTest`

### Frontend
- [ ] `resources/ts/api/storage.ts`
- [ ] `StorageSettingsPanel.vue`
- [ ] Wire the `storage` page into `pages/setup/settings.vue`
- [ ] i18n keys in `en.json`, `fa-AF.json`, `ps-AF.json`
- [ ] RTL/LTR + dark/light + responsive pass
- [ ] `npm run build:icons` if any new icon token is introduced

### Verification
- [ ] `php artisan test`
- [ ] `npm run typecheck`
- [ ] Print regression (contract A4, receipt/payslip A5)
- [ ] Live Google Drive smoke checklist (§11)
- [ ] Docs: this file's tracker updated + `docs/scheduled-jobs.md` + `README`/`.env.example` notes

---

*Progress should be recorded in this file as work lands, per the project's plan-document convention.*
