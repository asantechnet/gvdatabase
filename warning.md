# WARNING — parallel-agent scope notice

## Who is writing this

Agent: **customers → applicants merge** (structural). Task: delete the `customers`
entity and fold it into `applicants`, so that one entity owns both the billing/CRM
party data and the visa-case data.

Started: 2026-09-26. Branch: `main` (commits are appended; I do not rewrite history
and I do not rebase other agents' work).

## Agent working in parallel: CONTRACT TEMPLATES

You are working on **contract templates**. This merge **necessarily** touches
contract-adjacent files. I am flagging every one of them here rather than silently
editing around you.

### Rule I am following

> I will **not** change anything that is semantically about *templates*. Specifically
> I am **leaving `contract_types.slug = 'customer'` alone**. That slug names a
> *contract type* ("a contract whose counterparty is a customer/applicant"), not the
> `Customer` model. It is a false positive for a naive `customer` rename. If you
> rename that slug, you own it — tell me and I will follow.

### Files I will edit that you may also be editing

Please treat these as contested and prefer re-reading from `git` immediately before
you edit, then re-check `git diff` afterwards.

**Backend**
- `app/Http/Controllers/ContractController.php` — I only change
  `customer_id` → `applicant_id` in validation, the applicant/customer coherence
  check, and `resolvePartyContext()`. I do **not** touch template selection logic.
- `app/Models/Contract.php` — `customer_id` fillable + `customer()` relation → `applicant()`.
- `app/Http/Resources/ContractResource.php` — `customer_id`/`customer` → `applicant_id`/`applicant`.
- `app/Services/Contracts/PlaceholderResolver.php` — context key only (see below).
- `app/Services/Contracts/PlaceholderRegistry.php` — **I do not edit this.**

**Frontend**
- `resources/ts/api/contracts.ts` — `customer_id` → `applicant_id` on input types.
- `resources/ts/components/ContractForm.vue` — the customer `VSelect` becomes an
  applicant `VSelect`. Service/position/template filtering is untouched.
- `resources/ts/pages/contract_list.vue` — customer column/filter → applicant.
- `resources/ts/utils/contractPartyDetails.ts` — reads `contract.customer` → `contract.applicant`.
- `resources/ts/api/contractType.ts`, `resources/ts/pages/contract/policies.vue`,
  `resources/ts/pages/contract/policy_library.vue` — **read-only for me**; these key
  off the `'customer'` contract-type slug, which I am not renaming.

### Placeholder compatibility contract (important)

`PlaceholderRegistry` still exposes `customer.*` placeholders
(`customer.name`, `customer.company_name`, `customer.phone`, `customer.email`,
`customer.address`, `customer.tax_number`) and I am **not** renaming them, because
that is template vocabulary and therefore yours.

To keep those placeholders working after the entity merge, `ContractController::resolvePartyContext()`
will populate **both** context keys from the same applicant payload:

```php
'applicant' => $payload,   // new, semantically correct
'customer'  => $payload,   // back-compat alias for customer.* placeholders
```

So:
- If you migrate placeholders to `applicant.*`, add them to `PlaceholderRegistry` /
  `PlaceholderResolver` and the `applicant` context key is already there waiting.
- If you leave `customer.*` alone, nothing breaks.
- **Please do not delete the `customer` context key** until you have migrated every
  `customer.*` placeholder. I will mark it `@deprecated` in a comment.

### Tests we both touch

- `tests/Feature/ContractTemplateUpdateTest.php`, `ContractServiceLinesTest.php`,
  `VersionedContractGenerationTest.php`, `EducationalContractIntegrityTest.php`,
  `ServiceContractNavigationTest.php` — these construct contracts with `customer_id`.
  I will convert them to `applicant_id`. If you are editing the same file, expect a
  conflict and re-read before editing.

### If you need something from me

I am merging `customer_id` → `applicant_id` on contracts and dropping
`contracts.customer_id`. If your template work needs a customer/billing party on a
contract, it now comes from the contract's `applicant_id`. Ping me and I will add a
narrow accessor rather than guess.

## Change log for this file

- 2026-09-26 — initial notice written before any merge commit.
