# CE-001 — Editing a draft extension clears its parent link and origin snapshot

- **Severity:** High
- **Area:** Employment Contracts, Other Contracts, contract editing
- **Status:** Confirmed (existing review); observed in the current database
- **Confidence:** High

## Summary

A contract extension is created correctly as a draft with `parent_contract_id` set and `snapshot_data.extension_of` recorded. Opening that draft in **Edit contract** and saving it — even with no field changed — sends `parent_contract_id: null`, persists the null, and rebuilds the snapshot without `extension_of`.

The row keeps `contract_role = extension`, so the UI still labels it an Extension, but the “of CTR-…” origin is gone. The record can no longer identify the agreement it extends, including in the printed snapshot.

## Reproduction (documented in `other-contracts-issues/EXT-20261110-001`)

1. Open **Employment Contracts** or **Other Contracts**.
2. On an active primary contract, choose **Actions → Extend contract**, then save.
3. The new draft shows the parent number in the Contract Number cell.
4. On that draft, choose **Actions → Edit contract**.
5. Save without changing anything.
6. The “· of CTR-…” suffix disappears; the database has `parent_contract_id = NULL` and no `snapshot_data.extension_of`.

## Current-state evidence

The current dev database contains three extension rows with no parent:

| Contract | Type | Role | Status | `parent_contract_id` |
| --- | --- | --- | --- | --- |
| 21 | Employment | extension | draft | `NULL` |
| 23 | Other | extension | cancelled | `NULL` |
| 25 | Other | extension | active | `NULL` |

Contract 21 was opened in the edit dialog during this audit and saved unchanged; `PUT /api/contracts/21` returned **200**, consistent with the existing reproduction path. It was already orphaned before this save.

The shared list renders these rows as “Extension” with no parent number.

## Expected

- Editing an extension must preserve `parent_contract_id`.
- `snapshot_data.extension_of` must survive an edit.
- The API should reject or ignore an attempt to null the parent through the generic update path.

## Actual

- The edit payload always includes `parent_contract_id`; in edit mode the form emits `null`.
- The controller accepts the nullable field and writes it to the existing row.
- The snapshot is rebuilt from scratch and loses `extension_of`.
- No warning or validation error is shown.

## Root cause (source trace)

1. `resources/ts/components/ContractForm.vue` always emits the parent value:

   ```ts
   parent_contract_id: props.extensionMode ? (props.parentContract?.id ?? null) : null,
   ```

   In edit mode `extensionMode` is false, so the payload contains `parent_contract_id: null`.

2. `app/Http/Controllers/ContractController.php::update()` validates `parent_contract_id` as nullable and passes the validated data to `$contract->update($data)`.

3. The same update calls `documentAttributes()`, which replaces `snapshot_data` with a freshly built snapshot that has no `extension_of` key.

## Impact

- Routine editing destroys the extension’s origin relationship.
- The list and printed document can no longer name the agreement being extended.
- The data becomes structurally indistinguishable from an orphan extension; CE-004’s invariant then has no way to repair it.
- The failure is silent.

## Suggested repair (report only; no code changed)

- Do not include `parent_contract_id` in the edit payload; send it only while creating an extension.
- Reject or ignore `parent_contract_id` in `ContractController::update()`.
- Preserve/merge `snapshot_data.extension_of` when rebuilding an extension snapshot.
- Add a regression test that edits a draft extension and asserts both the parent ID and the snapshot key survive.
- Repair the existing orphan rows (21, 23, 25) or mark them explicitly.

## Evidence

- `other-contracts-issues/EXT-20261110-001/issue.md` — before/after JSON and screenshots for both registers.
- Current database query: contracts 21, 23, 25 are `contract_role=extension` with `parent_contract_id=NULL`.
- This audit: `PUT /api/contracts/21` → 200 on the orphan draft.
