# EW-003 — Contract update routes bypass the edit window because controllers use scalar IDs

- **Severity:** High
- **Area:** Employment/other contracts, built-in service contracts, other scalar-ID update routes
- **Status:** Confirmed for generic contracts; source-confirmed for related routes
- **Confidence:** High

## Summary

`EnforceEditWindow` only enforces when the route contains a **bound Eloquent model** and the controller receives that model. Generic contract routes are declared with `Route::apiResource('contracts', ...)`, but the controller methods are:

```php
public function update(Request $request, $id): JsonResponse
public function cancel(Request $request, $id): JsonResponse
public function updateService(StoreServiceContractRequest $request, string $service, int $id, ...)
```

Because the controller parameters are `$id`/`int $id`, Laravel performs no implicit model binding. The middleware sees only scalar route parameters and does nothing. The same pattern exists in finance-type and educational-master-data updates.

I confirmed the bypass by editing a draft generic contract created more than three hours earlier (`CTR-26-021`, created 06:12, tested 09:51) as a manager with **no** override permission. `PUT /api/contracts/21` returned **200 OK**.

## Expected

- An old draft contract should be subject to the same one-hour gate as an old employee or task record: either 422 `business.edit_window_expired`, or an explicit, audited override.
- Cancellation and built-in service-contract updates should follow the same rule if they are considered edits.

## Actual

- Generic contract update bypasses the middleware entirely.
- `PUT /api/contracts/{id}/cancel` and `PUT /api/contracts/{service}/{id}` use the same scalar-ID pattern and are expected to bypass as well.
- `FinanceTypeController` and `EducationalMasterDataController` update routes follow the same scalar pattern, so their old records are also editable without the window.
- The contract form is still fully interactive; the bypass is silent and leaves no `edit_window_overridden` audit record.

## Impact

- Contract content can be changed indefinitely after creation, even though employee and task edits are frozen after one hour.
- Cancellation of an old contract is not windowed, despite being a material lifecycle change.
- The inconsistency makes the policy unpredictable and weakens the audit story: some old records are frozen, others are freely editable.
- A user who expects the global rule may believe an edit was blocked when it was not, or vice versa.

## Suggested direction (report only; no code changed)

- Bind `Contract $contract` in `update()` and `cancel()`, and rename the route parameter so implicit binding applies; do the same for `updateService()` or enforce the window explicitly inside the controller.
- Apply the same treatment to finance-type and master-data updates, or document why they are exempt.
- Add regression tests that update an old contract and an old built-in service contract and assert the expected 422/override behaviour.

## Evidence

- `app/Http/Controllers/ContractController.php` — `update()` at line ~290, `cancel()` at ~317, `updateService()` at ~222 take scalar IDs.
- `app/Http/Middleware/EnforceEditWindow.php::editedRecord()` only inspects `Model` route parameters.
- Live request as manager without override: `PUT /api/contracts/21` → **200**.
- The draft contract had been created at 06:12; the request was made at 09:51 (more than three hours later).
- This is separate from CE-001; that issue is about the update payload clearing the extension parent, not about whether the edit was allowed.
