# EXT-20261110-001 — Editing a draft extension erases its parent link

**Severity:** High  
**Area:** Employment Contracts, Other Contracts, contract editing  
**Status:** Confirmed in both registers

## Summary

A contract extension is created correctly as a draft with `parent_contract_id` set and `snapshot_data.extension_of` recorded. Opening that draft in **Edit contract** and saving it — even without changing a field — sends `parent_contract_id: null`. The update persists the null and rebuilds `snapshot_data` without `extension_of`.

The row keeps `contract_role = extension`, so the UI still calls it an Extension, but the “of CTR-…” origin disappears. The record can no longer identify the agreement it extends, including in the printed document snapshot.

## Reproduction

1. Open **Employment Contracts**.
2. On an active primary contract, choose **Actions → Extend contract**, then save.
   - Result: a draft extension with the parent number shown in the Contract Number cell.
3. On the new draft, choose **Actions → Edit contract**.
4. Save without changing anything.
5. Observe the Contract Number cell: the “· of CTR-…” suffix is gone.
6. Inspect the database: `parent_contract_id` is `NULL`, and `snapshot_data.extension_of` is absent.

The same steps were repeated on **Other Contracts** with the same result.

## Observed values

### Other Contracts (clean before/after trace)

`CTR-26-024` was created as an extension of `CTR-26-022`:

Before editing — `evidence/other-before-edit.json`:

```json
{
  "number": "CTR-26-024",
  "role": "extension",
  "parent_id": 22,
  "parent_number": "CTR-26-022",
  "status": "draft",
  "snapshot_extension": "CTR-26-022"
}
```

After an unchanged edit/save — `evidence/other-after-edit.json`:

```json
{
  "number": "CTR-26-024",
  "role": "extension",
  "parent_id": null,
  "parent_number": null,
  "status": "draft",
  "snapshot_extension": null
}
```

UI comparison:

- Before: `other-extension-before-edit.png` — `CTR-26-024 Extension · of CTR-26-022`
- After: `other-extension-after-edit.png` — `CTR-26-024 Extension` with no origin

`CTR-26-024` was later deleted to exercise the Delete action; the before/after evidence remains.

### Employment Contracts

`CTR-26-021` was created as an extension of `CTR-26-020`, then edited and saved. Its current state — `evidence/employment-21-after-edit.json`:

```json
{
  "number": "CTR-26-021",
  "role": "extension",
  "parent_id": null,
  "parent_number": null,
  "status": "draft",
  "snapshot_extension": null
}
```

The current Employment list is captured in `evidence/employment-list-current.png`; the row shows only `Extension`, without `of CTR-26-020`.

## Root cause

1. `resources/ts/components/ContractForm.vue:487` always emits a parent value:

   ```ts
   parent_contract_id: props.extensionMode ? (props.parentContract?.id ?? null) : null,
   ```

   In **edit** mode `extensionMode` is false, so the payload contains `parent_contract_id: null` even though this is an extension row.

2. `app/Http/Controllers/ContractController.php:292-308` validates `parent_contract_id` as nullable and passes the whole validated payload to `$contract->update($data)`. The null is therefore written to the existing record.

3. The same update calls `documentAttributes()` and replaces `snapshot_data` with a freshly built snapshot that has no `extension_of` key (`ContractController.php:299-307`, `documentAttributes()` return block). The original extension origin is lost as well.

## Impact

- The parent/origin relationship is destroyed by a routine edit.
- The list no longer names the agreement being extended.
- The issued document snapshot loses `extension_of`, so the printed extension cannot reliably name its source agreement.
- The action is silent; no warning or validation error is shown.
- This affects both contract registers and any extension type that uses the shared list/form.

## Suggested repair

- Do not include `parent_contract_id` in the edit payload; only send it while creating an extension.
- Reject or ignore `parent_contract_id` in `ContractController::update`.
- Preserve `snapshot_data.extension_of` when rebuilding an extension snapshot, or merge the rebuilt snapshot instead of replacing the origin metadata.
- Add a regression test that edits a draft extension and asserts both `parent_contract_id` and `snapshot_data.extension_of` survive.

## Evidence files

- `evidence/other-before-edit.json`
- `evidence/other-after-edit.json`
- `evidence/other-extension-before-edit.png`
- `evidence/other-extension-after-edit.png`
- `evidence/employment-21-after-edit.json`
- `evidence/employment-list-current.png`
- `evidence/contract-state.json`
