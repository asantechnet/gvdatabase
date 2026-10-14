# Settings validation and email defaults

Company settings now use explicit field-error bindings instead of relying on a toast or matching DOM labels. Every editable company/email/operations field, color picker, upload, artwork scope and print-safe-area input has a field identifier and visible error feedback. Office and embedded Support editors use the same feedback mechanism.

## Behavior

- New settings, missing-profile fallbacks and the database column default disable company email configuration. The migration changes the default without rewriting existing preferences. The toggle governs company SMTP, sender and reply-to overrides; server mail fallback follows the existing application behavior.
- Invalid controls receive Vuetify error messages and error styling. Custom upload buttons and color pickers receive an error outline, an inline explanation and accessible invalid-state metadata.
- One local toast names up to three invalid fields, describes the problem and indicates when more fields need correction. All returned errors are displayed inline. Backend messages retain actual limits and supported formats.
- Failed saves keep the draft. The company editor opens the relevant section; the first visible invalid control is scrolled into view and focused. Editing one field clears only its errors. Errors in other settings sections remain available.
- Partial saves validate submitted required fields without requiring fields from other sections. Optional SMTP settings may remain empty to use server mail fallback. Websites require HTTP/HTTPS URLs; SMTP hosts require a hostname or IP without a URL scheme or embedded port.
- Upload type/size errors appear immediately. Invalid selections remain in the draft so a subsequent Save cannot silently ignore the selected file. Choosing a replacement or removing the selection clears the corresponding errors.
- Safe-area offsets and dimensions retain invalid entries for validation. All contract, receipt, payslip and scoped-artwork rectangles must fit within the page. Partial updates check the submitted dimensions against the saved dimensions.
- Support attachment errors such as `attachments.0` bind to the attachments control and identify the individual file.

Field labels live in `resources/ts/api/settingValidation.ts` and localized backend attributes in `lang/{en,fa,ps}/settings.php`. Company-setting descriptions are supplied in English, Dari and Pashto. The API response still preserves its machine-readable error codes and backend-localized messages.

## Validation

- **45 backend tests passed, 288 assertions**: company settings, email defaults, migration rollback/forward, artwork, office geofencing, support tickets and receipt behavior.
- `npm run test:settings-validation` covers descriptive/localized messages, server limits, partial client validation, error bindings, focus, independent corrections, nested attachments, file formats/sizes and editor-control coverage.
- Vue scripts/templates and TypeScript syntax compile for the settings, office and Support editors. Translation labels, bundled icons, PHP syntax and scoped diff whitespace checks pass.
- Standard `npm run typecheck` cannot resolve `vue-tsc/bin/vue-tsc.js` through the existing dependency links in this environment. Frontend checks were run against the installed package files directly; a complete project typecheck, production build and browser rendering remain unverified.

No document print styles, paper sizes or issued snapshots were changed. Existing A4 artwork snapshots and A5 receipt behavior are covered by the backend regression suite.
