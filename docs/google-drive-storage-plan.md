# Google Drive Storage Integration — Plan

**Status:** Revised (v2). **Not implemented yet.** Planning only, per the operator's instruction to firm the plan before code.
**Source request:** `docs/Educational New Comments++.md`
> "Google drive connection would be cool in educational DB. there would be a section in settings for storage where the user could link their google drive account to the system and from then on the system would use that drive as storage."

**Related plan:** `docs/ai/multi-tenancy-per-office.md` (Phase 5 folds this plan into the office migration set). This document is deliberately written **office-scoped from day one** so that plan does not have to rework it.

---

## 0. Revision log

| Rev | Date | Change |
|---|---|---|
| 1 | 2026-10 | Original plan: **local disk primary, Drive as a mirror**. Written from an availability-first standpoint. |
| 2 | 2026-10-15 | **Reversed the storage model to Drive-first.** The operator's binding constraint is *server disk*, not availability: the point of the feature is that case attachments and other documents stop living on the server. Local disk is demoted from "authoritative" to **short-lived staging plus a bounded unsynced backlog**. Added: office scoping, promotion pipeline, backlog ceiling, restore path, and an explicit risk statement that documents are now unavailable while Google auth is broken. |
| 3 | 2026-10-15 | **Operator resolved the four open decisions.** Attachment features are **gated behind a Drive connection**; the unsynced-backlog ceiling is **1 GB per office**; existing local documents stay readable but are promoted and freed once a Drive is linked; promotion is **inline for small files, background for the rest**. Sections §2, §4.11, §6, §11, §13, §15 updated. |

### 0.1 Why the original verdict was amended

Rev 1 chose local-primary to keep reads fast and to survive a Google outage. The operator has now stated the actual requirement:

> "my bottleneck is server storage, it is limited. so google drive implementation is there for customers who want to use the storage features like case attachments and all. they connect their own google drive to their own office."

That inverts the trade: **holding the bytes locally is the failure mode to avoid**, so local-primary would defeat the feature. Drive-first is therefore adopted, with the availability cost paid down by explicit controls (§4.6) rather than by keeping every byte on the server.

---

## 1. Verdict on the Drive-first request

**Practical — yes, with four hard truths that must be designed around, not wished away.**

| # | Hard truth | How this plan handles it |
|---|---|---|
| 1 | **The document-serving path now includes a Google round trip.** If the token is dead or Google is down, `disk = 'google_drive'` documents cannot be opened. There is no local copy by design. | Proactive refresh, `needs_reauth` surfacing, health verification, a per-connection "documents unavailable" state in the UI, and a documented recovery path. This is a *stated consequence* of the model, not a bug. |
| 2 | **Deleting the local copy is the irreversible step**, and it is what actually frees the disk. | A strict, one-way gate: upload → verify size + md5 → record the mapping → flip the record's `disk` → **only then** delete local. Any failure keeps the local file. Local deletion never happens before verification, and never on a record with no verified mapping. |
| 3 | **Uploads must land somewhere before they reach Google**, so transient server usage is unavoidable. | Bounded by the existing validation caps (5 MB for expense/employee/support documents, 10 MB elsewhere, 8 MB for artwork). Maximum transient exposure is `in-flight uploads + unsynced backlog`, and the backlog has a hard ceiling (§4.6). |
| 4 | **A Google account is now part of the data.** Losing access to the linked account means losing the documents; `drive.file` makes the mapping a capability, not a copy. | Recommend a dedicated company account or a **Shared Drive**, surface account identity permanently on the Storage page, document `drive:restore-local` as the "get everything back before you touch the account" tool, and keep mappings across a disconnect/reconnect. |
| 5 | **Storage becomes a prerequisite feature.** Gating attachments behind a link (decision 6) means an office that never links a Drive cannot attach a document anywhere — a functional change for existing installs, not just a storage change. | The gate covers create/upload only (reads and deletes stay open), vital artwork is never gated, it lands behind one overridable flag, and §12 sequences it so the gate only turns strict after a real Drive is connected on the live office. |

**Evaluated and rejected: browser-direct uploads to Drive** (create a resumable session server-side, `PUT` the bytes from the browser straight to Google). It would remove even the transient server usage, but: the resumable session URI's CORS behaviour is not a documented contract, the bytes would bypass server-side MIME/extension validation and any future scanning, and it forks the code path for one benefit that the 10 MB caps make small. **Not now.** Revisit only if per-file caps grow to hundreds of MB.

**The disk win, quantified:** with Drive-first, steady-state server usage per office is `branding artwork + avatars (a few MB) + unsynced backlog (~0)`. Case attachments, employee documents, expense documents, task and support attachments, and managed files all leave the server once verified.

---

## 2. Decisions log (authoritative)

| # | Question | Decision |
|---|---|---|
| 1 | Where do documents live once Drive is linked? | **Drive is primary.** A record's bytes are uploaded to Drive, verified, and the local copy is then deleted. |
| 2 | What is the local copy, then? | **Staging + unsynced backlog**, never a shadow copy of everything. It exists only until a verified Drive upload exists. |
| 3 | Which Google access is requested? | **`drive.file`** — the app creates and fully owns one root folder per office and can only ever see files it created. |
| 4 | What happens to files already stored locally? | **Still readable before linkage** (no data loss, no surprise 404s), then **backfilled into Drive and removed locally** once a Drive is linked — tracked per file, batched and resumable, with reclaimed bytes reported. Dry-run first. |
| 5 | Account model | **One connection per office** (today the office is a singleton, so one connection in practice). Per-user drives are out of scope; per-office is the target, matching `docs/ai/multi-tenancy-per-office.md`. |
| 6 | What if Drive is not connected? | **Attachment features are disabled.** No new document attachment can be created anywhere until an office links its Drive; upload controls are disabled with a link to Settings → Storage. Only **vital, lightweight artwork** keeps working (§4.11). |
| 7 | Can an install keep local copies anyway? | Yes — `google_drive_connections.mode = 'mirror'` copies to Drive and keeps local primary (the rev-1 behaviour). Default when connected is `'drive_first'`. |
| 8 | Delete semantics in Drive | `trashed = true`, never immediate permanent deletion, so an accidental record deletion is recoverable for 30 days. |
| 9 | Branding artwork and avatars | **Stay local on the `public` disk, always.** They must be hotlinkable same-origin URLs for the SPA and for print; Drive has no public URL. Not uploaded at all by default. |
| 10 | Multi-tenancy | **Office-scoped from day one**: `office_id` on all three tables, office-scoped Drive root, office-scoped cache keys, office-aware jobs and commands. No schema rework when tenancy lands. |
| 11 | The gate's escape hatch | The gate is one service with one decision method, so an install that will never use Drive can be unblocked by a documented config flag (`storage.require_drive_for_attachments`, default `true`). The default is the strict behaviour the operator asked for; the flag exists so a deployment cannot be permanently locked out of its own attachments. |
| 12 | Unsynced-backlog ceiling | **1 GB per office** (`GOOGLE_DRIVE_BACKLOG_LIMIT_MB=1024`). Per office, not per install, so one office's backlog cannot starve another once tenancy lands. |
| 13 | Promotion trigger | **Inline for small files, background for the rest.** A small file is on Drive before the upload response returns, so the server disk is freed immediately; anything above the inline ceiling or over the time budget falls to the queue and the 5-minute sweep. |

### 2.1 Reconciliation with `docs/ai/multi-tenancy-per-office.md`

That plan (Phase 5) requires the Drive tables to live in the office migration set, the Drive root folder to be office-scoped, the callback to be office-aware, and the `drive:*` commands to accept an office selector. This revision satisfies all four **now**, while the app is still single-database:

- Tables carry `office_id` (nullable today, filled from the singleton office row when one exists) and every uniqueness constraint is office-scoped.
- The Drive root is `Educational DB/{office-name}-{office-uuid}/…`. Today there is no uuid on the `offices` row (the registry uuid arrives with tenancy), so the folder schema builder takes its folder segment from a single injectable `StorageScope` that returns `''` when no office context is bound.
- `drive:*` commands accept `--office=` today and simply run for the only office when it is omitted.
- When the office migration set exists, these migrations move into it unchanged.

---

## 3. Current storage surface (audit, verified against the code)

This is the complete set of writes that must be covered. Verified by reading each controller.

| Domain | Writer | Disk today | Path pattern | Row stores disk? | Drive-first treatment |
|---|---|---|---|---|---|
| Managed files | `FileController` | `local` | `managed-files/{folder}/{uuid}.{ext}` | n/a (path only) | promote |
| Applicant files | `ApplicantAttachmentController` | `local` | `applicant-attachments/{applicant_id}/{uuid}.{ext}` | yes — `applicant_attachments.disk` | promote |
| Applicant portal files | `ApplicantPortalController` | `local` | `applicant-attachments/{applicant_id}/{uuid}.{ext}` | yes | promote |
| Task files | `TaskAttachmentController` | `local` | `task-attachments/{task_id}/{uuid}.{ext}` | yes — `task_attachments.disk` | promote |
| Expense / income / staff files | `AttachmentController` | `local` | `attachments/{type}/{id}/{uuid}.{ext}` | yes — `attachments.disk` | promote |
| Support tickets | `SupportTicketController` | `local` | `support-tickets/{ticket_id}/{uuid}.{ext}` | yes — `support_ticket_attachments.disk` | promote |
| Employee documents | `EmployeeController` | `local` | `employee-documents/{employee_id}/{uuid}.{ext}` | **no** — hardcoded `local`, `document_path` only | promote via the shared `attachments` row |
| Expense documents | `ExpenseController` | `local` | `expense-documents/{expense_id}/{uuid}.{ext}` | **no** — hardcoded `local`, `document_path` only | promote via the shared `attachments` row |
| Company branding | `SettingController` | `public` | `logos/`, `signatures/`, `contract-letterheads/`, `contract-cover-designs/`, `receipt-letterheads/`, `payslip-letterheads/` | n/a | **never moved** |
| Contract designs | `ContractDocumentDesignController` | `public` | `contract-designs/{uuid}.{ext}` | n/a | **never moved** |
| Avatars | `AuthController` | `public` | `avatars/{uuid}.{ext}` → stored as `/storage/...` | n/a | **never moved** |

Facts confirmed while auditing, which the design depends on:

- Every writer hardcodes `'local'` (the legacy `'public'` default on `task_attachments.disk` is dead — the controller sets `'local'`).
- **Upload caps are small and uniform:** 10 MB (`FileController`, `ApplicantAttachmentController`, `ApplicantPortalController`, `TaskAttachmentController`, `AttachmentController`), 5 MB (`SupportTicketController`, `ExpenseController`, `EmployeeController`), 8 MB (contract design artwork). This is what makes a staging buffer acceptable.
- **Every download today is `Storage::disk($row->disk)->exists(...)` + `->download(...)`**, plus `FileController::viewFile` which streams inline with `readStream`. There is no other serving mechanism, so a working `google_drive` disk keeps all of these functioning.
- **Also read/written outside controllers:** `Task::deleting` (cascade deletes attachment files), `TaskAttachmentController::destroy`, `AttachmentController::destroy`, and the `attachments`/`documents` clean-up paths in `EmployeeController`/`ExpenseController`. Deletion propagation must cover the model-level cascade too.
- **No print path reads an uploaded document.** Receipts (`IncomeReceiptService`), payslips, academy slips (`AcademySlipService`) and contract snapshots reference `Storage::url(...)` **artwork on the `public` disk only** (`SettingResource`, `ContractResource` document-design snapshot, `app/views/application.blade.php`). Print therefore cannot be affected by this feature, which is the carve-out that keeps §9 clean.
- **The "applicant needs a document" rule is not actually enforced.** `App\Services\ApplicantDocumentReadiness::require()` exists and throws `business.applicant_document_required`, but **nothing in `app/` calls it** — it is dead code, and `ContractController` never checks attachments. Tests seed documents defensively from an earlier enforcement that has since been removed. **Consequence for the gate: disabling attachments before linkage does not block contract creation or any other workflow.**
- `offices` is a **singleton** (`is_singleton` unique column) used only for the geofence; `office_id` exists on `employees`, `attendances`, `assets` only. So "per office" today means the one office.
- New permissions are added in **two** places: `PermissionSeeder::CATALOG` (canonical, upserted, never pruned) **and** a seed migration for existing installs — the pattern used by `override_contract_payments`.

---

## 4. Target architecture

### 4.1 Lifecycle of one uploaded file

```
upload request
  │  validate (unchanged caps/rules)
  ▼
1. write to local disk, same paths as today                    ← bytes exist somewhere durable
2. insert row with disk = 'local'                              ← readable immediately, app works
3. respond 201 to the user                                     ← never blocked by Google
  │
  └─► promote (inline attempt with a time budget; else queued job)
        ├ resolve folder id (per office, cached; create if missing)
        ├ upload  ─ < 5 MB: uploadType=multipart
        │          ─ ≥ 5 MB: uploadType=resumable (single/few chunks)
        ├ verify  ─ size match + md5Checksum match (one retry on mismatch)
        ├ record  ─ google_drive_files: status='synced', verified_at=now()
        ├ flip    ─ record.disk = 'google_drive'        [mode = drive_first only]
        └ free    ─ delete the local file               [only after verification]
```

Failure at any step after (3) leaves `disk = 'local'` and the local file **in place**, records `status='failed'` + `last_error` + `attempts`, and retries with backoff. The file remains fully usable throughout.

### 4.2 Serving a document (the only reader)

```
GET download/view (disk, path, name)
  │
  ├─ record's disk readable AND file exists?  → serve it (local: BinaryFileResponse;
  │                                             Drive: streamed proxy, Range-aware)
  ├─ else mapping row for (connection, disk, path) with status='synced'?
  │      → stream from Drive through the proxy   (no local write; Range passed through)
  └─ else → 404 resource.file_not_found
```

Two deliberate choices:

- **No local write-back on read.** Rev 1 cached Drive files back to local; under Drive-first that would silently re-consume the very disk we are trying to free. If a local file is missing but Drive has it, we **stream** from Drive. (`drive:fetch` can still pull specific files back on demand for an operator who wants a local copy.)
- **Range is passed through to Drive.** Inline previews (the managed-file viewer) and any future PDF seeking depend on it, and passing the client's `Range` header through to `files.get?alt=media` is a handful of lines. This is why the proxy exists rather than leaning on `Storage::download` for the Drive case.

### 4.3 Two-layer modes

`google_drive_connections.mode` decides behaviour and is read by one resolver, so there is exactly one code path to reason about:

| Mode | Bytes | Local copy | Use |
|---|---|---|---|
| *(no connection)* | none — **attachment features are disabled** | only vital artwork, on `public` | decision 6 / §4.11; readable existing documents are unaffected |
| `drive_first` *(default on connect)* | Drive | deleted after verification | **the requested model** — frees the server |
| `mirror` | Drive | kept, local is primary | cautious install; rev-1 behaviour; also the safe stepping stone for a first deployment |

### 4.4 New components

| Component | Responsibility |
|---|---|
| `App\Support\Storage\GoogleDrive\GoogleDriveClient` | OAuth token exchange/refresh, Drive v3 REST calls (`files.list/get/create/copy/delete/update`, `about.get`), multipart + resumable upload, retry/backoff, quota reads. Built on `Illuminate\Support\Facades\Http` — **no new composer dependency**; `league/flysystem` v3 is already vendored via `laravel/framework`. |
| `App\Support\Storage\GoogleDrive\GoogleDriveAdapter` | `League\Flysystem\FilesystemAdapter` so `Storage::disk('google_drive')` behaves like any other disk. This is what keeps ~10 existing call sites (`exists`/`download`/`delete`/`readStream`) working unchanged. |
| `App\Support\Storage\GoogleDrive\GoogleDriveFolderSchema` | Single source of truth for the folder tree: logical path → Drive folder id, name sanitising, ensure-exists with caching. |
| `App\Support\Storage\DrivePromoter` | Orchestrates one file: skip-if-verified by checksum, upload, verify, record, flip the record's disk, free the local file, retry bookkeeping. |
| `App\Support\Storage\StorageConnection` | Active connection for the current office: `mode`, `isConnected()`, `needsReauth()`, `refreshIfExpiring()`. |
| `App\Support\Storage\DocumentResolver` | The §4.2 resolution, used by every download endpoint. |
| `App\Support\Storage\DriveProxy` | Range-aware streaming from Drive with correct headers, no local write. |
| `App\Support\Storage\StorageScope` | Office segment for paths, Drive root and cache keys; `''` today, `offices/{uuid}` and the office uuid once tenancy lands. |
| `App\Support\Storage\StorageBacklog` | Computes unsynced bytes + counts, enforces the ceiling, exposes the health surface. |
| `App\Providers\StorageServiceProvider` | Lazily registers the `google_drive` disk; throws a typed `StorageConnectionMissingException` when not connected. |

**Client notes.** Access tokens refresh transparently when expiry is within 60 s; a failed refresh flips the connection to `needs_reauth`, notifies admins, and **never** breaks a local write or an already-local document. Retry with exponential backoff + jitter on `429` / `403 rateLimitExceeded` / `5xx`, honouring `Retry-After`, max 5 attempts, then `status='failed'` with `last_error`. A small client-side throttle keeps concurrency under the documented ceiling instead of discovering it by failing. All Drive calls pass `supportsAllDrives=true` so a Shared Drive works without a code change.

**Adapter notes.** Drive visibility is meaningless for app-owned `drive.file` files, so visibility methods report `NotSupportedException`/`private` rather than lying; there is no `publicUrl`, which is exactly why branding stays local. `readStream` is implemented over a `php://temp` stream (spills to disk past 2 MB); with the 10 MB caps the bound is acceptable, and the hot download path uses `DriveProxy` instead so no buffering happens for ordinary downloads.

### 4.5 Drive folder schema

Created once, on connect, under one app-owned root per office. Mirrors the local layout so the two are reconcilable by eye.

```
Educational DB/{short_company_name}-{office_uuid}/
├── Applicants/{applicant_id - full_name}/Documents/
├── Employees/{employee_id - name}/Documents/
├── Contracts/{contract_number}/
├── Incomes/Receipts/{yyyy}/{receipt_number}/
├── Expenses/{yyyy}/{expense_id}/
├── Tasks/{task_id}/
├── Support Tickets/{ticket_id}/
├── Managed Files/{folder}/
└── Unfiled/{yyyy-mm}/                 ← safety net for any path that cannot be mapped
```

Rules:

- **Everything resolves by id from `google_drive_folders`, never by name lookup.** Drive permits duplicate names, so name-based resolution is a correctness bug waiting to happen.
- Folder names are sanitised (strip `/ \ : * ? " < > |`, collapse whitespace, cap at 100 chars) with the numeric id retained for disambiguation.
- Path→folder mapping is a **pure function of the record's local path**, with an `Unfiled/{yyyy-mm}` fallback so a new writer can never fail to store. Unknown shapes are filed, logged, and can be re-homed later; nothing is ever rejected because the schema did not anticipate it.
- A rename on our side does **not** rename the Drive folder. The folder id is the contract. A "refresh folder names" maintenance action can be added later if wanted.
- Every uploaded file carries `appProperties = { app: 'educational-db', office, disk, sourcePath, checksum }`. Because `drive.file` exposes only app-created files, reconciliation and orphan detection rely on these properties rather than on scanning the user's Drive.

### 4.6 The controls that make Drive-first safe

| Control | Behaviour |
|---|---|
| **Verified-before-delete gate** | Local deletion only after `status='synced'` with a matching size and md5. Single choke point in `DrivePromoter`; nothing else deletes local document bytes. |
| **Backlog ceiling** | `GOOGLE_DRIVE_BACKLOG_LIMIT_MB` (default **1024 = 1 GB, measured per office**). While the unsynced backlog is under it, uploads keep working and accumulate locally. At the ceiling, **new uploads are refused** with `storage.backlog_limit_reached` (422) and admins are notified — the server disk can never be silently filled. |
| **Backlog visibility** | Unsynced bytes + pending/failed counts on the Storage page, plus `drive:status`. Pending backlog is the health signal that Drive is not keeping up. |
| **Never-pruned backlog** | Unsynced local files are the only copy; no scheduled job may delete them. Stale backlog triggers an alert, not a clean-up. |
| **Health + reauth** | `drive:verify` (token, root folder, quota) on a schedule; `needs_reauth` banner with the 7-day-testing-mode explanation; notification on failure. |
| **Restore path** | `drive:fetch {path}` and `drive:restore-local --domain=…` pull bytes back from Drive to local before an operator ever touches the Google account. Documented recovery procedure. |
| **Disconnect keeps everything** | Disconnect wipes token material, marks the connection `disconnected`, and **keeps the mapping rows and the Drive tree**. Documents become unreadable until reconnect, so the UI must say that plainly and offer `restore-local` first. |
| **Delete semantics** | Record deletion trashes the Drive file (`trashed=true`) and deletes the local file if one remains. Permanent Drive deletion is a separate, explicitly confirmed action. |

### 4.7 Data model

All three tables carry `office_id` (nullable, indexed, filled by `StorageScope` today) and are office-scoped in every unique key. Secrets never leave the connection row.

**`google_drive_connections`**

| Column | Notes |
|---|---|
| `id`, `office_id`, `provider_key` (`google_drive`) | Provider-scoped from day one. |
| `connected_by` | `user_id` → `nullOnDelete`. |
| `status` | `connected` \| `needs_reauth` \| `disconnected`. |
| `mode` | `drive_first` (default) \| `mirror`. |
| `account_email`, `account_name`, `account_avatar_url` | Shown on the Storage page; identifies **whose** Drive holds the documents. |
| `access_token`, `refresh_token` | **`encrypted` casts** (same pattern as `settings.mail_password`). |
| `token_expires_at`, `scopes`, `token_type` | Refresh scheduling + scope drift detection. |
| `root_folder_id`, `root_folder_name`, `company_folder_id` | The app-owned tree root. |
| `is_shared_drive` | Whether the root lives on a Shared Drive (affects list calls). |
| `last_verified_at`, `last_synced_at`, `last_error` | Health surface. |
| `quota_bytes`, `quota_used_bytes`, `quota_checked_at` | Quota bar; early warning before uploads start failing. |
| timestamps | One active connection per office, enforced in the model + a unique index on `(office_id, provider_key)` where not disconnected. |

**`google_drive_folders`** — `id`, `office_id`, `connection_id`, `logical_path`, `name`, `drive_folder_id`, timestamps. Unique `(connection_id, logical_path)`.

**`google_drive_files`** — `id`, `office_id`, `connection_id`, `disk`, `path`, `drive_file_id`, `drive_folder_id`, `checksum`, `size`, `mime_type`, `status` (`pending` \| `synced` \| `failed` \| `orphaned`), `attempts`, `last_error`, `promoted_from` (`upload` \| `backfill`), `promoted_at`, `verified_at`, `trashed_at`, timestamps. Unique `(connection_id, disk, path)`; index on `(status, office_id)`.

Migrations (latest existing is `2026_10_14_000005`):

- `2026_10_15_000001_create_google_drive_connections_table.php`
- `2026_10_15_000002_create_google_drive_folders_table.php`
- `2026_10_15_000003_create_google_drive_files_table.php`
- `2026_10_15_000004_seed_storage_settings_permission.php`

**Deliberate non-decision:** connection tokens are *not* stored on the `settings` table. That table is `Auditable` and serialised through `SettingResource`; tokens there would risk both audit-log leakage and accidental API exposure. A dedicated model excluded from `Auditable`, with secrets `$hidden`, is the safe shape.

### 4.8 OAuth and token lifecycle

- Scope: `https://www.googleapis.com/auth/drive.file` only.
- Authorization Code flow with **PKCE (S256)** plus a single-use, 10-minute, server-side `state` bound to the initiating user id. PKCE makes an intercepted code useless; `state` makes the flow non-forgeable.
- `GET /api/storage/google-drive/connect` (authenticated, `manage_storage_settings`) returns the consent URL; the SPA opens it in the same tab.
- Google redirects to `{APP_URL}/api/storage/google-drive/callback` — a browser redirect carries no bearer token, so this route lives in the **public** group with `throttle:10,1`, protected by the single-use state + PKCE verifier. Success redirects to `/setup/settings/storage?connected=1`, failure to `?error=<code>`. Under tenancy this becomes `/offices/{uuid}/api/…` (already anticipated by the multi-tenancy plan).
- Tokens are encrypted at rest and never returned by any endpoint.
- **Immediately after connect**, the app creates the folder tree, reads quota and account identity, and runs `drive:verify` so a broken link is discovered at connect time rather than at the first upload.
- `disconnect` deletes token material and keeps files, mappings and the Drive tree; the UI states the consequence and offers `restore-local` first.

### 4.9 Security

| Concern | Control |
|---|---|
| Token theft from the DB | `encrypted` casts; tokens excluded from resources, audit logging and error payloads. |
| CSRF / code injection on the callback | Single-use server-side `state` + PKCE S256 + `throttle:10,1`. |
| Over-broad access | `drive.file` — the app can only see files it created; it cannot enumerate the user's Drive. |
| Who can link/unlink | New permission `manage_storage_settings`; `view_settings` still governs viewing status. Route-level `permission:` middleware, matching neighbouring settings routes. |
| Accountability | Connection, promotion and backlog-ceiling state changes go to the audit log (excluding secrets), plus notifications on auth failure, quota exhaustion and ceiling breach. |
| Path traversal on the proxy | Reuse `FileController::validatedPath` semantics; no Drive-supplied name is ever trusted for a filesystem path, and the proxy only ever addresses a mapped `drive_file_id`. |
| Data residency / consent | The Storage page states plainly what is uploaded, that local copies are deleted after verification, that branding stays local, and that the target is the company's own Google account. |

### 4.10 Reliability and failure modes

| Failure | Behaviour |
|---|---|
| No queue worker running | Promotion is attempted **inline** with a per-file time budget; anything unfinished falls to the queue, and `drive:promote --pending --limit=200` runs every 5 minutes (`withoutOverlapping()->onOneServer()`, matching `routes/console.php`) as convergence. Because the inline attempt frees the disk immediately for the common small-file case, a workerless install still keeps the server clean. |
| Google unreachable during upload | Local write already succeeded; the file stays local and usable, marked pending, retried with backoff. Backlog ceiling is the only thing that can stop new uploads, and it says so explicitly. |
| Token revoked / expired | `needs_reauth` banner, admins notified, local documents keep working, already-promoted documents are unreadable until reconnect — stated in the UI, not hidden. |
| Quota exhausted | `403 storageQuotaExceeded` is detected, surfaced with the exact Drive message plus the quota bar, and promotion pauses instead of retrying forever. |
| Local file deleted but already promoted | Reads come from Drive. Nothing to do — this is the normal state. |
| Drive file deleted out of band | `drive:reconcile` marks the mapping `orphaned`; the record is reported as needing re-upload, and if a local copy still exists (pending backlog) it is simply promoted again. |
| Duplicate folder names | Never resolved by name — id-based resolution only. |
| Interrupted backfill | Batched, idempotent, resumable by `(disk, path)`; re-running skips verified files by checksum. |
| Server disk fills anyway | Ceiling breach refuses new uploads and notifies; `drive:status` shows exactly how much is stuck and why. |
| Linked Google account lost | Documents are unrecoverable if local copies were deleted — **the one genuine data-loss path in this design.** Mitigated by recommending a dedicated/Shared Drive account, keeping mappings across reconnects, and shipping `drive:restore-local` so an operator can pull everything back before touching the account. |

---

### 4.11 Attachment gating — no Drive yet means attachments are disabled

Decision 6: document storage is a *feature of having storage*. Nothing unbounded may land on the server, so the surfaces that create documents are closed until the office links its own Drive.

**Two tiers**

| Tier | What it covers | Before linkage |
|---|---|---|
| **Vital artwork — always allowed** | `website_logo`, `company_signature`, contract letterhead and cover design, receipt/payslip letterheads, contract document designs, avatars | Upload / replace / remove exactly as today, on the local `public` disk, never sent to Drive |
| **Documents — gated** | applicant and applicant-portal attachments, task attachments, expense/income/staff attachments, employee documents, expense documents, support-ticket attachments, managed files | Create/upload blocked with `storage.attachments_not_enabled` (409) plus a CTA to Settings → Storage. Existing files stay listed and downloadable. |

The split is not arbitrary: the vital set is bounded (a handful of images per office, ≤8 MB each, and it *must* stay same-origin for print), while the gated set is unbounded and is exactly the growth the operator is buying Drive to absorb.

**Enforcement — one choke point, server-side.** A `StorageGate` service answers `attachmentsEnabled()`; a thin route middleware (`storage.enabled`) guards the gated write routes, and the one writer that is not a single-file route calls the service directly. A UI-only gate would be a lie, because the API is the product surface.

| Gated write endpoint | Notes |
|---|---|
| `POST /api/applicants/{applicant}/attachments` | |
| `POST /api/applicant-portal/attachments` | |
| `POST /api/tasks/{task}/attachments` | |
| `POST /api/attachments/{type}/{id}` | expenses / incomes / staff |
| `POST /api/employees/{employee}/document` | |
| `POST /api/expenses/{expense}/document` | |
| `POST /api/files/upload` | managed files |
| `POST /api/support-tickets` | **only when `attachments` are present** — raising a ticket with no file stays open to every user, so this one is a controller-level check, not middleware |

The gate covers **create/upload only**. Reads and deletes are never gated: an existing document must stay downloadable, and deletable by whoever may delete it, whether or not a Drive is linked. That is what makes switching the gate on safe for an install that already holds documents.

**Escape hatch.** `storage.require_drive_for_attachments` (config, default `true`) forces the gate open for an install that will never use Drive. The default is exactly decision 6; the flag exists only so a deployment cannot be permanently locked out of its own attachments.

**What the gate must not do:** it must not hide pages or list screens. Hiding a list makes existing documents unreachable and turns a storage policy into a data-access regression. Disable the *upload control* and the *create* path; leave read, download and delete alone.

One case flagged rather than decided: **support-ticket attachments** are gated here because they are attachments, but a screenshot on a help request is cheap and useful. Kept open in §15.

---

## 5. Backend work breakdown

### Phase 1 — Foundation
1. Migrations (§4.7) + `GoogleDriveConnection`, `GoogleDriveFolder`, `GoogleDriveFile` models with relations, casts and `$hidden` on secrets.
2. `config/services.php` → `google_drive` block; `config/filesystems.php` → `google_drive` disk definition.
3. `.env.example`: `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_REDIRECT_URI`, `GOOGLE_DRIVE_PROMOTE_MODE` (`inline`\|`queue`), `GOOGLE_DRIVE_INLINE_BUDGET_MS`, `GOOGLE_DRIVE_BACKLOG_LIMIT_MB`, `GOOGLE_DRIVE_BATCH_SIZE`.
4. `StorageServiceProvider`, `StorageConnection`, `StorageScope`, `StorageBacklog`, typed `StorageConnectionMissingException`.
5. `manage_storage_settings` permission (`category` `core`) — added to **`PermissionSeeder::CATALOG`** and seeded by migration for existing installs, following `2026_10_07_000013_seed_contract_payment_override_permission.php` exactly (`insertOrIgnore` + role grants to `super_admin`/`admin`; `down()` intentionally a no-op comment).
6. Backend message codes in `lang/{en,fa,ps}/api.php`, resolved through `ApiMessage::forCode`.

### Phase 2 — Drive client and adapter
7. `GoogleDriveClient`: consent URL with PKCE + state, code redemption, refresh, `files.*`, `about.get`, multipart and resumable upload, retry/backoff, `supportsAllDrives`.
8. `GoogleDriveFolderSchema` with id caching, sanitisation and the `Unfiled` fallback.
9. `GoogleDriveAdapter` (Flysystem v3) and its contract tests.

### Phase 3 — Connection lifecycle
10. `StorageConnectionController`: `show`, `connect`, `callback`, `disconnect`, `verify`, `folders`, `status`.
11. `StorageConnectionResource` — never exposes tokens.
12. Routes in `routes/api.php` (§7). Callback in the public group.
13. Notifications for `needs_reauth`, quota exhaustion and backlog-ceiling breach (through `Notification::deliverTo`).

### Phase 4 — Gate, promotion and serving
14. `DrivePromoter` + `App\Jobs\PromoteDocumentToDrive` (`ShouldQueue`; the repo's **first** queued job — see §13.3) + a `PromoteInline` path with a time budget.
15. **Attachment gate**: `StorageGate`, the `storage.enabled` middleware, and its application to the gated write routes (§4.11) plus the controller-level check inside `SupportTicketController::store`.
16. `DocumentResolver` + `DriveProxy`, and wiring of **every** writer in §3 to promote after a successful local write:
    `FileController`, `ApplicantAttachmentController`, `ApplicantPortalController`, `TaskAttachmentController`, `AttachmentController`, `SupportTicketController`, `EmployeeController`, `ExpenseController`.
    Branding writers (`SettingController`, `ContractDocumentDesignController`, `AuthController`) are **not** wired — their files never leave `public`.
17. Deletion propagation: on model/controller delete, trash the mapped Drive file and drop the mapping. Cover the `Task::deleting` cascade as well as the controllers.
18. Download endpoints switch to `DocumentResolver` (Range-aware), including `FileController::viewFile`. `employees.document_path` and `expenses.document_path` are normalised through the resolver as `(disk = 'local', path)` so they participate without a schema change.

### Phase 5 — Backfill, maintenance and monitoring
18. `php artisan drive:verify` — token, root folder, quota, tree integrity.
19. `php artisan drive:promote --pending --limit=200 [--office=]` — convergence sweeper.
20. `php artisan drive:backfill [--domain=] [--limit=100] [--dry-run] [--keep-local] [--office=]` — promote existing local files and **report reclaimed bytes**.
21. `php artisan drive:fetch {path}` and `drive:restore-local [--domain=] [--limit=]` — pull bytes back from Drive.
22. `php artisan drive:reconcile [--prune-orphans]` (dry-run by default) and `drive:status` (backlog bytes, pending/failed, quota, last sync).
23. Scheduler entries in `routes/console.php` (`drive:promote --pending`, daily `drive:verify`) + `docs/scheduled-jobs.md`.
24. `DriveSyncController`: `summary`, `start` (batched, resumable), `progress`, `cancel`, `retry`.

Backfill order: folders → applicant attachments → employee/expense documents → shared attachments → task attachments → support tickets → managed files. Throttled by batch size and the client-side limiter; Drive's 750 GB/day/user upload ceiling and per-60 s query ceilings are the binding constraints.

---

## 6. Frontend work breakdown

1. `resources/ts/api/storage.ts` — typed contract; unwraps `response.data.data` **in the API layer only**, modelled on `api/incomes.ts`.
2. `resources/ts/components/setup/StorageSettingsPanel.vue` — the panel, embedded the same way `OfficeSettingsPanel.vue` is.
3. `resources/ts/pages/setup/settings.vue` — add `'storage'` to the `SettingsPage` union, the `settingsPages` list (`tabler-cloud`), `pageFields.storage = []`, the template branch, and add `storage` to the `showsSettingsActions` exclusion list (it owns its own API and has no `settings` fields, like `office` and `support`).
4. **Gate-aware upload controls** (§4.11). One composable (`useStorageGate`) reads a single `attachments_enabled` flag from the storage status payload, and every gated upload control is disabled with a tooltip/inline notice and a "Connect Google Drive" link when it is false. The flag must come from the server, never be inferred on the client. Affected surfaces: applicant attachments tab, applicant portal upload, task attachment drawer, shared attachments section, employee and expense document fields, managed-files page, support-ticket form, and the storage page itself as the CTA target.
5. `resources/ts/navigation/horizontal/setup.ts` — no change needed; settings is one nav entry.

Panel states:

- **Not connected (the default state of a new office)** — states plainly that **document attachments are switched off until a Drive is linked**, lists what still works (logo, signature, contract cover, letterheads, avatars), what will be uploaded, that local copies are deleted after verification, the single `drive.file` scope in plain language, and a "Connect Google Drive" CTA. This is the page that unblocks the whole feature, so it is the one state that must be unmissable — reached from the disabled upload controls as well as from the settings sidebar.
- **Connecting** — spinner, cancel.
- **Connected** — account chip (name/email/avatar), root folder name + "Open in Drive", mode badge ("Drive is primary · local copies removed after upload" vs "Mirror · local copies kept"), quota bar, unsynced backlog size, last sync, buttons: Verify access, Promote pending now, Restore everything to local, Disconnect.
- **Needs re-auth** — banner with the 7-day-testing-mode explanation, the fact that Drive-held documents are unreadable until reconnect, and a reconnect CTA.
- **Backlog / ceiling** — counters (total / on Drive / pending / failed), unsynced bytes against the ceiling, determinate progress with `aria-live="polite"`, failure list with per-row retry, cancel.
- **Backfill** — dry-run summary first (file count + bytes to reclaim), then batched start with progress and reclaimed bytes.

Interface requirements: RTL **and** LTR using logical properties only (`ms-*`/`me-*`, never `ml-*`/`mr-*`); light **and** dark via Vuetify theme tokens (`text-medium-emphasis`, `variant="tonal"`, no hardcoded hex); responsive `VCol` breakpoints; keyboard-reachable controls with visible focus; `vue-toastification` for toasts (never the legacy library); every new class-based icon a real `tabler-*` token picked up by `npm run build:icons` — `icons.css` is generated and must not be hand-edited; dynamic names need `icon-allowlist.json` or the remote `@iconify/vue` `<Icon>`.

---

## 7. API surface

All under the existing `auth:sanctum` + `abilities:access` + `active` + `edit-window` group unless noted.

The table below is the storage surface. The **gated attachment routes stay where they are** — they gain the `storage.enabled` middleware rather than moving, so existing clients, download URLs and permission middleware are untouched (§4.11).

| Method | Path | Handler | Permission |
|---|---|---|---|
| GET | `/storage/google-drive` | `show` | `view_settings` |
| GET | `/storage/google-drive/connect` | `connect` | `manage_storage_settings` |
| GET | `/storage/google-drive/callback` | `callback` | **public**, `throttle:10,1`, state + PKCE guarded |
| POST | `/storage/google-drive/disconnect` | `disconnect` | `manage_storage_settings` |
| POST | `/storage/google-drive/verify` | `verify` | `manage_storage_settings` |
| GET | `/storage/google-drive/folders` | `folders` | `view_settings` |
| GET | `/storage/google-drive/status` | `status` | `view_settings` |
| GET | `/storage/sync/summary` | `summary` | `view_settings` |
| POST | `/storage/sync` | `start` | `manage_storage_settings` |
| GET | `/storage/sync/progress` | `progress` | `view_settings` |
| POST | `/storage/sync/cancel` | `cancel` | `manage_storage_settings` |
| POST | `/storage/sync/retry` | `retry` | `manage_storage_settings` |

---

## 8. i18n

- Frontend: new `pages.settings.storage.*` keys in `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json`.
- Backend codes in `lang/{en,fa,ps}/api.php`: `storage.drive_not_connected`, `storage.attachments_not_enabled`, `storage.insufficient_scope`, `storage.quota_exceeded`, `storage.backlog_limit_reached`, `storage.promote_failed`, `storage.sync_already_running`, `storage.file_not_found`, `storage.reauth_required`, `storage.invalid_state`, `storage.not_shared_drive_ready`.
- `storage.attachments_not_enabled` must be phrased as an action, not an error: "Attachments are switched off until this office connects its Google Drive." Every gated surface shows the same copy so the message is learned once.
- No hardcoded English strings in the panel or in controller responses.

---

## 9. Print and document impact

Documents and printing are high-importance in this project, and this plan deliberately changes nothing about them:

- Receipts (A5), payslips (A5) and contracts (A4) continue to render from local `public` artwork and inline data. **Verified during the audit: no print path reads an uploaded document.** Print cannot read Drive.
- Branding, letterheads, contract designs and avatars never leave the `public` disk, so every printed image stays a same-origin static URL.
- The contract-document-design snapshot in `ContractResource` and the receipt/payslip services reference `Storage::url(...)` on `public` only; unchanged.
- No new `@page` rules, no new global `@media print` selectors, no changes to `utils/printDocument.ts` or the namespaced print-mode body classes.
- Regression gate: the contract-print and receipt/payslip layouts must render identically after Phase 4 wiring.

---

## 10. Google Cloud setup (owner: operator)

1. Create a project in Google Cloud Console.
2. Enable the **Google Drive API**.
3. Configure the OAuth consent screen. Choose `Internal` if the org is on Workspace (best — no expiry trap, no review). Otherwise choose `External` and **publish**: `drive.file` is non-sensitive, so publishing needs no verification review, and it avoids the 7-day refresh-token expiry that `Testing` mode imposes.
4. Create an **OAuth client ID** of type *Web application*.
5. Authorised redirect URI: `{APP_URL}/api/storage/google-drive/callback`.
6. Put the client ID, client secret and redirect URI into `.env`.
7. **Recommended: a dedicated company Google account or a Shared Drive, not a personal Drive** — the documents live there, and `drive:restore-local` is only a safety valve, not a backup strategy.

**Prerequisite owned by the operator: the OAuth client credentials and the account choice.** Everything else can be built and tested against `Http::fake()` without them; only the end-to-end smoke test cannot.

---

## 11. Testing strategy

Automated (no live Google required — `Http::fake()` + `Storage::fake()`):

| Test | Covers |
|---|---|
| `GoogleDriveClientTest` | Consent URL contains PKCE challenge + state; token exchange; refresh before expiry; 429/5xx backoff; revoked token → `needs_reauth`; quota read. |
| `GoogleDriveAdapterTest` | Flysystem contract: write/read/exists/delete/listContents/createDirectory/readStream, plus the "not connected" exception. |
| `DriveFolderSchemaTest` | Path building, name sanitisation, id caching, `Unfiled` fallback, duplicate-name safety. |
| `DrivePromoterTest` | Checksum skip; verify-before-delete ordering; failure keeps local file and records the error; retry bookkeeping; `mirror` mode keeps local. |
| `DocumentResolverTest` | Local hit; promoted record served from Drive; missing-everywhere → 404; `Range` passthrough. |
| `StorageBacklogTest` | Ceiling refusal, notification, and recovery once promotion clears the backlog. |
| `StorageConnectionTest` | show/connect/callback happy path; bad state; PKCE mismatch; throttle; disconnect keeps files and mappings; permission gates. |
| `StorageGateTest` | With no connection: every gated write route returns 409 `storage.attachments_not_enabled`, ticket creation **without** a file still succeeds, ticket creation **with** a file is refused, and reads/downloads/deletes of existing documents keep working. With a connection: all of the above succeed. With `storage.require_drive_for_attachments=false`: everything succeeds unlinked. Vital artwork upload still works with no connection. |
| `StorageSyncTest` | start/progress/cancel/retry, batch limits, resumability, dry-run backfill reporting reclaimed bytes. |

Manual smoke checklist against a real Drive: connect → confirm the folder tree exists → upload one file per domain → confirm each lands in the right folder with the right `appProperties` and that **the local file is gone while the download still works** → `drive:backfill --dry-run` then a real batch → confirm reclaimed bytes → disconnect → confirm the documents are reported unreadable and `restore-local` recovers them → reconnect with the same account → confirm documents are readable again.

**Known test ripple from the gate — measured, not guessed.** Only five test files touch storage at all, and only one is affected:

- `SupportTicketTest` — uploads a ticket attachment through the endpoint and asserts `Storage::disk('local')->assertExists(...)`. It needs `connectFakeDrive()` and its assertion becomes "the file is on Drive and the mapping is `synced`".
- `CompanySettingsTest`, `ContractDocumentDesignTest`, `SettingsEmailAndFileRemovalTest` — vitals on the `public` disk, ungated, unaffected.
- `IncomeReceiptTest` — `Storage::fake('public')` artwork only, unaffected.
- `tests/TestCase.php::seedApplicantDocuments()` creates attachment **rows** with no files; reads are ungated, so it keeps working. Combined with the dead `ApplicantDocumentReadiness` (§3), gating attachments breaks no contract or workflow test.
- New test helpers: `connectFakeDrive()` in `TestCase` (connection row + `Http::fake()`) and a `FakeGoogleDrive` HTTP double in `tests/Support/` that emulates tokens, `files.list/create/get/update/delete` and `about.get`, assigning ids and real md5s — so promoter and resolver tests exercise the real client paths.

Regression: `SupportTicketTest`, `SettingsEmailAndFileRemovalTest`, `CompanySettingsTest`, `ContractDocumentDesignTest`, `IncomeReceiptTest`, `AcademySlipTest`, `BuiltInContractGenerationTest`, `VersionedContractGenerationTest`, plus `php artisan test` and `npm run typecheck` (never bare `vue-tsc`/`tsc`).

---

## 12. Rollout and rollback

**Rollout**

> **Sequencing warning created by the gate.** The gate is part of this feature, so shipping it before any office has connected a Drive **disables attachments for every existing install**. Phases 1–3 (connect, verify, folders) must therefore land before or with the gate turns strict, and the operator must be able to connect in the same release. Practical order: ship storage settings + connect first with `storage.require_drive_for_attachments=false` (attachments keep working as today), connect the real Drive on the live office, then flip the flag to `true`.

1. Ship Phases 1–3 with promotion disabled and the gate open (`storage.require_drive_for_attachments=false`): connection, verification and folder creation work; nothing is uploaded; nothing is disabled.
2. Connect the production Drive, run `drive:verify`, and confirm the folder tree and account identity on the Storage page.
3. Enable promotion in **`mirror`** mode: Drive fills up, local files stay. This proves the client, the folder schema and the mapping table with zero risk to the bytes.
4. Flip the gate to strict (`true`) so new uploads require the connection, then switch `mode = 'drive_first'` so verified uploads start freeing local space.
5. Run `drive:backfill` domain by domain, watching reclaimed bytes and the 1 GB per-office backlog ceiling.

**Rollback**

- Any time before `drive_first`: nothing was deleted, so rollback is just not using Drive.
- After `drive_first`: rollback = `drive:restore-local` (pull everything back), then disconnect. This needs local space equal to the restored set, which is why the ceiling and the backlog report exist — an operator must be able to see the number before choosing.
- Disconnect alone never destroys data: tokens are wiped, mappings and the Drive tree stay, reconnecting the same account restores access.
- The mapping tables are inert when disconnected and can be dropped later without losing a document that exists locally.
- No migration in this plan mutates existing file rows, so a failed rollout cannot orphan a record.

---

## 13. Risks and open items

| # | Risk / item | Mitigation / owner |
|---|---|---|
| 1 | OAuth client credentials do not exist yet | Operator (§10). Blocks the live smoke test only. |
| 2 | Consent screen left in `Testing` → refresh token dies after 7 days | Publish the app or use `Internal`; `needs_reauth` detects it instead of failing silently. |
| 3 | Link to Google, not to the bytes: losing the account loses the documents | Dedicated company account / Shared Drive, permanent account identity on the Storage page, `drive:restore-local`, mappings preserved across reconnect. **Accepted residual risk of Drive-first.** |
| 4 | Documents unreadable while Google auth is broken | Proactive refresh, post-connect `drive:verify`, scheduled `drive:verify`, reauth banner, and `restore-local` as the operator's escape hatch. |
| 5 | The repo dispatches **no** queued jobs today; `database` is the default queue connection and no worker is provably running | Inline promotion with a time budget as the primary path (keeps the disk free without a worker) plus the 5-minute `drive:promote --pending` sweep. Confirm a worker during rollout. |
| 6 | Drive quotas and rate limits | Backoff + client throttle + quota bar + pause-on-exhaustion; batched, resumable backfill. |
| 7 | Server disk fills from an unsynced backlog | Hard ceiling that refuses new uploads and notifies, plus `drive:status` reporting bytes stuck and why. |
| 8 | `employees` / `expenses` lack a `disk` column | Normalised through the resolver; canonical copies already live in `attachments`. No schema change. |
| 9 | Renames on our side not reflected in Drive folder names | Documented: folder id is the contract, not the name. |
| 10 | DB growth of the mapping table | Unique `(connection_id, disk, path)`, status index, prune of `orphaned` rows in `drive:reconcile`. |
| 11 | Promotion silently stops (worker down, token dead, quota full) | `last_synced_at`, pending counter and unsynced-bytes figure on the Storage page; the backlog is the visible health signal. |
| 12 | Per-office tenancy lands later and reworks storage | Tables, uniqueness, root folder, cache prefix and commands are office-scoped now; `StorageScope` is the only place that changes. |
| 13 | Path→folder mapping misses a new writer | `Unfiled/{yyyy-mm}` fallback means a promotion can never fail because the schema did not know a path shape. |
| 14 | The gate locks an install out of its own attachments (Drive never gets linked, or the operator cannot reach Google) | Default is the requested behaviour, but `storage.require_drive_for_attachments=false` is a one-line escape hatch, and reads/deletes are never gated. Rollout flips the flag only after a real Drive is connected on the live office. |
| 15 | Disabling attachments removes a feature existing users depend on, with no migration path | Only the *create* path is gated; every existing document stays listed, downloadable and deletable, and `drive:backfill --dry-run` shows exactly what moving to Drive would cost before anything changes. |
| 16 | 1 GB per office is exceeded during a long Google outage | Uploads are refused with an actionable message rather than filling the disk; the ceiling is per office so one office cannot starve another; `drive:status` shows the exact number. |

---

## 14. Progress tracker

Everything below is **not started** (this revision is planning only).

### Phase 1 — Foundation
- [ ] Migrations: connections, folders, files, permission
- [ ] Models: `GoogleDriveConnection`, `GoogleDriveFolder`, `GoogleDriveFile` (encrypted casts, hidden secrets)
- [ ] `config/services.php` + `config/filesystems.php` + `.env.example`
- [ ] `StorageServiceProvider`, `StorageConnection`, `StorageScope`, `StorageBacklog`, typed exception
- [ ] `manage_storage_settings` in `PermissionSeeder::CATALOG` + seed migration + role grants
- [ ] Backend message codes in `lang/{en,fa,ps}/api.php`

### Phase 2 — Drive client and adapter
- [ ] `GoogleDriveClient` (OAuth + Drive v3 + multipart/resumable + retry/backoff + Shared Drive)
- [ ] `GoogleDriveFolderSchema` (id caching, sanitisation, `Unfiled` fallback)
- [ ] `GoogleDriveAdapter` (Flysystem v3)
- [ ] `GoogleDriveClientTest`, `GoogleDriveAdapterTest`, `DriveFolderSchemaTest`

### Phase 3 — Connection lifecycle
- [ ] `StorageConnectionController` (show/connect/callback/disconnect/verify/folders/status)
- [ ] `StorageConnectionResource`
- [ ] Routes in `routes/api.php` (callback in the public group)
- [ ] Re-auth / quota / ceiling notifications via `Notification::deliverTo`
- [ ] `StorageConnectionTest`

### Phase 4 — Gate, promotion and serving
- [ ] `StorageGate` + `storage.enabled` middleware applied to the gated write routes + controller-level check in `SupportTicketController::store`
- [ ] `storage.require_drive_for_attachments` config (default `true`)
- [ ] `StorageGateTest`
- [ ] `DrivePromoter` + `PromoteDocumentToDrive` job + inline path with time budget
- [ ] `DocumentResolver` + `DriveProxy` (Range passthrough) + download fallback
- [ ] Wire `FileController`, `ApplicantAttachmentController`, `ApplicantPortalController`, `TaskAttachmentController`, `AttachmentController`, `SupportTicketController`, `EmployeeController`, `ExpenseController`
- [ ] Confirm branding writers are **not** wired (`SettingController`, `ContractDocumentDesignController`, `AuthController`)
- [ ] Deletion propagation (controllers + `Task::deleting` cascade)
- [ ] `DrivePromoterTest`, `DocumentResolverTest`, `StorageBacklogTest`

### Phase 5 — Backfill, maintenance and monitoring
- [ ] `drive:verify`, `drive:promote`, `drive:backfill`, `drive:fetch`, `drive:restore-local`, `drive:reconcile`, `drive:status`
- [ ] Scheduler entries in `routes/console.php` + `docs/scheduled-jobs.md`
- [ ] `DriveSyncController` (summary/start/progress/cancel/retry)
- [ ] `StorageSyncTest`

### Frontend
- [ ] `resources/ts/api/storage.ts`
- [ ] `StorageSettingsPanel.vue`
- [ ] Wire the `storage` page into `pages/setup/settings.vue` (union, list, `pageFields`, template branch, action-bar exclusion)
- [ ] `useStorageGate` composable + disabled upload controls with the "Connect Google Drive" CTA on every gated surface (applicant attachments, portal, tasks, shared attachments, employee/expense documents, managed files, support tickets)
- [ ] i18n keys in `en.json`, `fa-AF.json`, `ps-AF.json`
- [ ] RTL/LTR + dark/light + responsive pass
- [ ] `npm run build:icons` if any new icon token is introduced

### Verification
- [ ] `php artisan test` (run with the sqlite test DB — see §15)
- [ ] Rework `SupportTicketTest` for the gate + Drive-first; add `connectFakeDrive()` and `tests/Support/FakeGoogleDrive`
- [ ] `npm run typecheck`
- [ ] Print regression (contract A4, receipt/payslip A5)
- [ ] Live Google Drive smoke checklist (§11)
- [ ] Docs: this file's tracker updated + `docs/scheduled-jobs.md` + `README`/`.env.example` notes
- [ ] Cross-check `docs/ai/multi-tenancy-per-office.md` Phase 5 against what actually shipped

---

## 15. Decisions — resolved, and what is still open

### Resolved by the operator (2026-10-15)

| # | Question | Decision | Where it landed |
|---|---|---|---|
| 1 | Attachments before a Drive is linked | **Disabled.** Only vital lightweight artwork keeps working (logo, signature, contract cover, letterheads — plus avatars and contract document designs, which are the same class of file). | §2 dec. 6, §4.11 |
| 2 | Unsynced-backlog ceiling | **1 GB per office**, then new uploads are refused with a clear message. | §2 dec. 12, §4.6 |
| 3 | Files already on the server | Kept readable, then promoted to Drive and freed when a Drive is linked. | §2 dec. 4, §11 |
| 4 | Promotion trigger | **Inline for small files, background for the rest**, plus the 5-minute sweep. | §2 dec. 13, §4.1 |
| 5 | Default mode on connect | `drive_first`; `mirror` remains available and is the recommended first-deployment step. | §2 dec. 7, §12 |

### Still open — 2 small questions, both answerable during implementation

1. **Support-ticket attachments.** Gated like every other attachment (current plan) or allowed unlinked as a cheap exception, since a screenshot on a help request is ≤5 MB and genuinely useful when someone is already having a bad day? Plan currently gates it.
2. **Avatars.** Classified as vital/ungated in this plan (≤800 KB, `public` disk, rendered by the browser). Confirm, or fold avatars into the gated tier.

### Environment notes for whoever implements this

- **The test suite cannot run on MySQL in this container** (no server, `sudo` requires a password). It was verified during planning that the full migration set applies cleanly and `CompanySettingsTest` passes with `DB_CONNECTION=sqlite DB_DATABASE=:memory:`. Per the operator's instruction, `phpunit.xml` should be switched to sqlite so the suite runs anywhere; that change must be made deliberately and the whole suite re-baselined, because it also settles `RefreshDatabase` behaviour for the new tables.
- **Baseline before starting:** capture `php artisan test` (sqlite) and `npm run typecheck` results so the Drive work can be judged against them.

---

---

## 16. Viability assessment (requested by the operator, 2026-10-15)

> Operator: *"I HAVE SOME SERIOUS DOUBT IMPLEMENTING THIS PLAN NOW. tell me if this feature is viable or not, how google drive outages play and how this feature is usable overall for a real firm."*

### 16.1 Verdict

**Viable — conditionally, and the conditions are about the Google *account*, not about Google's uptime.**

- **Durability actually improves.** A single Hostinger box is one disk, one snapshot policy, one careless `rm`. Drive replicates across Google's infrastructure with file versioning and a 30-day trash. Moving the durable copy to Drive makes the *bytes* safer than they are today, not less safe.
- **Availability of the file path gets worse.** Today a document opens as long as the server is up. Under Drive-first it also needs a live token and a reachable Google. That is a real regression and the reason for everything in §4.6.
- **The blast radius is narrow.** Only *file content* is affected. Applicants, contracts, tasks, payments, salaries, attendance, reports and print all keep working from MySQL. A Drive incident means "this PDF will not open right now", not "the firm stops working". That distinction is what makes the feature acceptable at all.

### 16.2 How failures actually play out, ranked by how likely a real firm is to hit them

| # | Failure | Likelihood | Duration | What users see | Control |
|---|---|---|---|---|---|
| 1 | **Refresh token revoked** — app left in `Testing` mode (7 days), user removed access, password change on the storage account, unused for 6 months, or Google's own revocation bugs (there are open reports of production apps being revoked every few days) | **Common — expect this at least once** | Until an admin reconnects (minutes, but only if someone notices) | Uploads stop; Drive-held documents stop opening | Publish the OAuth app or use `Internal`; `drive:verify` on a schedule; `needs_reauth` banner + notification; reconnecting is a 2-minute action that keeps all existing mappings |
| 2 | **Drive quota exhausted** — free tier is 15 GB shared with Gmail/Photos, and some reports say new accounts now default lower | **Likely for a document-heavy firm** | Until storage is bought or files are pruned | Every upload fails with `storageQuotaExceeded`; with the gate on, attachments stop | Quota bar that warns *before* the wall; surface the exact Drive message; recommend Google One (100 GB ≈ $2/mo, 2 TB ≈ $100/yr) |
| 3 | **The app's folder is deleted or trashed in Drive by a person** | Plausible | Recoverable for 30 days from Drive trash | Documents vanish from the app | `drive.file` cannot prevent this; `drive:reconcile` detects it and reports what is missing; Drive trash + 30-day window is the safety net |
| 4 | **Google Drive partial outage / degraded API** | A handful of incidents per year (Workspace status history shows hours-scale global incidents a few times a year, plus shorter partial ones) | Typically minutes to a few hours | Some uploads or downloads fail intermittently; retries hide most of it | Backoff + retry, backlog ceiling, and the read cache (§16.3) |
| 5 | **Account lost** — deleted, suspended, or set up under a departing employee's personal Gmail | Low if the account is company-owned; high if it is not | Potentially permanent | Documents are unrecoverable if local copies were deleted | Company-owned account or **Shared Drive**; account identity shown permanently in Settings; `drive:restore-local` before any account change |

**The ranking is the point.** Engineers instinctively worry about #4 (outages) and it is the *least* likely of the serious ones. The realistic failure is #1/#2 — a broken or full Google account — which is an administrative problem with an administrative fix, provided the app surfaces it loudly and does not destroy local copies while it is broken.

### 16.3 The one design change this analysis justifies

Pure Drive-first (delete local immediately after verification) maximises disk savings but makes `#1–#4` into *hard* stops. A **bounded local read cache** turns all of them into *degrades*:

- After a document is served from Drive, keep it in a cache directory with a last-access timestamp.
- Cap the cache at a number **the operator chooses** (`GOOGLE_DRIVE_READ_CACHE_MB`, default 0 = pure Drive-first). Evict least-recently-used files when over budget.
- Result: with, say, 1 GB of cache per office, the documents people actually work with that week still open during a Google outage, repeat opens are instant, and server usage is still a bounded number that cannot grow on its own.

This costs a bounded amount of the resource the operator is trying to protect, and removes the worst failure mode. It is the one change I would insist on before switching `mode = 'drive_first'` in production. Everything else in this plan is unchanged by it, because the resolver already decides where a byte comes from — the cache is one more place to look, first.

### 16.4 What "project viable" requires of the operator

1. **A company-owned Google account (or a Shared Drive), never an employee's personal Gmail.** This is the single most important condition.
2. **Paid quota headroom.** 15 GB will not hold a visa agency's scans; budget a Google One plan or a Workspace seat.
3. **Publish the OAuth app** (or use `Internal`), so tokens stop dying weekly.
4. **Someone who reads the Storage page.** The pending/backlog counter is the health signal; the whole design assumes a human notices it.
5. **Accept the availability trade:** during a Drive incident, documents that are not in the local cache cannot be opened, while the rest of the application keeps working normally.

### 16.5 When this feature is *not* the right answer

If the firm cannot accept *any* window in which a document is unopenable, then customer-owned Drive is the wrong architecture, and the correct answer is object storage (S3-compatible: Cloudflare R2, Backblaze B2, AWS S3, Google Cloud Storage) with a local cache. That keeps the server disk empty, removes OAuth and account-ownership risk entirely, and is priced per GB rather than per account. What it loses is the actual product idea — "the documents live in *our* Google Drive, at no extra cost" — and it moves the data into a bucket the vendor controls. **Note for the record:** the service catalog available to this assistant contains no S3-compatible storage provider, so no install-verified recommendation can be made here; if the operator wants this route, it needs its own research pass rather than a guess.

### 16.6 Recommendation on sequencing, given the doubt

The doubt is answerable with data rather than more planning, because the plan is already phased so that the risky part is last and reversible:

1. Build Phases 1–3 (connection, client, adapter, settings panel) — **read-only**: it links, verifies and lists folders, and changes nothing about how documents are stored. Risk: zero.
2. Run it in **`mirror`** mode with the gate **off** for two to four weeks. Nothing is deleted, nothing is disabled, and the Storage page reports what *would* happen.
3. Read the results: pending/failed counts, quota growth, reauth events, upload latency. If tokens survive and quota is adequate, flip `drive_first` with a read cache. If the numbers are ugly, disconnect — no document was touched.

That is the honest recommendation: **do not decide the architecture question now; build the reversible half and let the pilot answer it.**

---
