# API Error Messages — Humanizing Toasts

## Problem

Saving the **Settings** page (and other API forms) can show an unhelpful toast such as:

```
: invalid value
```

The user cannot tell **which field** failed or **why**. The goal is to surface the
real error the backend detected, translate it into human language (in the user's
locale), and never show a raw machine code or a dangling `: message` with no
field name.

## Root-cause analysis

The error pipeline is spread across the backend and the frontend:

1. **`bootstrap/app.php`** rewrote every `ValidationException` into a stable
   machine code. Any Laravel message that did not already look like a code was
   collapsed to the single literal `validation.invalid`:

   ```php
   str_starts_with($message, 'validation.') || str_starts_with($message, 'business.')
       ? $message
       : 'validation.invalid'
   ```

   So a failed `max`, `regex`, `timezone`, `between`, `in`, `date_format`, … rule
   all became the same generic `validation.invalid`. **The actual rule and its
   limits were thrown away** before the frontend ever saw them.

2. **The backend already sent the localized human text** in a parallel
   `messages` map, but the frontend ignored it.

3. **`resources/ts/utils/apiErrors.ts`** built the toast from the generic codes
   only:

   ```ts
   `${fieldLabel(firstField)}: ${firstFieldError}`
   ```

   `fieldLabel()` can resolve to an empty string, producing the leading `": "`
   artifact, and the text was the generic `Enter a valid value.`.

4. **`app/Support/ApiMessage.php`** could not resolve `validation.*` codes, so
   form requests that override a message with a code (e.g. the settings letterhead
   uploads use `validation.image` / `validation.max`) surfaced as
   `"Something went wrong. Please try again."`.

## Approach

Keep the machine-code contract (the `errors` map) but make it **specific**, and
teach the frontend to prefer the backend's already-localized human message.

* **Backend**: map the *real* failing Laravel rule (`Validator::failed()`) to a
  specific `validation.{rule}` code instead of the blanket `validation.invalid`.
  Keep the localized `messages` map, and make `ApiMessage` able to resolve
  `validation.*` codes so those messages are human too.
* **Frontend**: prefer the backend `messages` text for inline errors, humanize
  the code for the toast (short, label-prefixed), never emit an empty label, and
  fall back to a readable phrase for unknown codes instead of a raw key.
* **Translations**: add the missing validation-rule entries to the
  `backend.errors.validation.*` catalogs (en / fa-AF / ps-AF) and the
  `api.errors.validation.*` backend catalogs (en / fa / ps).

## What changed

### Backend

| File | Change |
| --- | --- |
| `app/Support/ValidationErrorCode.php` (new) | `forRule()` turns a `Validator::failed()` key (`Max`, `ProhibitedIf`, `App\Rules\Phone`, …) into `validation.max` / `validation.prohibited_if` / `validation.phone`. Unknown/absent rules fall back to `validation.invalid`. |
| `bootstrap/app.php` | The `ValidationException` renderer now zips each message with its concrete failing rule, emitting specific `errors` codes while preserving the (already localized) `messages` map. Explicit `validation.*` / `business.*` message overrides from form requests still win. |
| `lang/{en,fa,ps}/api.php` | New `errors.validation.*` keys (with a `default`) so `ApiMessage::forCode()` resolves `validation.*` codes to human, localized text. |

### Frontend

| File | Change |
| --- | --- |
| `resources/ts/utils/apiErrors.ts` | `ApiError` gained a `messages` field. `apiFieldErrors()` now prefers the backend's localized message (which includes real limits such as “between 1 and 65535”) and only translates the code as a fallback. `apiErrorMessage()` builds `Field: explanation`, drops the prefix when the label is unresolvable, and `humanizeApiError()` no longer leaks unmapped codes. |
| `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` | Added the missing `backend.errors.validation.*` rule keys (47 validation keys per locale now). Pashto previously had no validation catalog at all. |
| `tests/Feature/PhaseSixWorkdayTest.php` | Expectation updated from `validation.invalid` to the specific `validation.required`. |

### Resulting behaviour

For the settings email form, an invalid `company_email` now reads:

> Company email: Enter a valid email address.

and the inline error under the field shows the backend's precise sentence
(“The company email field must be a valid email address.”). An out-of-range
`mail_port` reads “Mail port: Enter a value within the allowed range.” instead of
a bare `: invalid value`.

## Verification

* `php -l` clean on every changed PHP file; all three locale JSON files parse.
* `npm run typecheck` — no new errors in the changed files (the repo has
  pre-existing errors in unrelated pages).
* The rule→code zip was exercised against a real Laravel validator (no DB
  needed): `required → validation.required`, `between:1,65535 → validation.between`,
  `timezone:all → validation.timezone`, `regex → validation.regex`,
  `image → validation.image`, `Rule::prohibitedIf → validation.prohibited`.
* `ApiMessage` keys resolve in en / fa / ps (`api.errors.validation.image`,
  `.max`, and the `default` fallback).
* `npm run i18n:check` reports only pre-existing gaps (`common.*`, `metrics.*`,
  `pages.finance…`); no new missing keys.
* The PHP feature suite could not be executed here: it needs MySQL on
  `127.0.0.1:3306`, which is not available in this environment. Please run
  `php artisan test --filter=PhaseSixWorkdayTest` against a real database.

## Follow-ups (not in this change)

* The backend emits ~110 `business.*` codes that have no frontend catalog entry
  (they rely on the localized `messages` map). They now degrade to a readable
  sentence instead of a raw key, but their client-side translations should be
  filled in over time.
* `scripts/i18n/runtime-test.mjs` currently fails on a pre-existing unrelated key
  (`components.dataTablePageKpis.pages.policyLibrary.contentHint`).
