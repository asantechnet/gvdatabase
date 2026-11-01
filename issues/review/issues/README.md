# Issue index

One folder per confirmed root cause. All screenshots are in the shared, sanitized evidence folder `review/evidence/`; this adaptation keeps a single authoritative evidence set instead of duplicating files per issue.

| ID | Issue | Severity | Category |
|---|---|---|---|
| 01 | Case stage stored twice; pages disagree | P1 | Logic |
| 02 | Document and close gates not enforced | P1 | Logic / control |
| 03 | Unpaid salaries labelled "posted"; expenses exclude payroll | P2 | Logic |
| 04 | Payment status hidden from case/contract screens | P2 | Logic / UI |
| 05 | Follow-up list not filtered | P2 | Logic |
| 06 | Contract attention has no expiry detail | P2 | Logic / UI |
| 07 | Hidden columns and unnamed icon actions | P2 | UI/accessibility |
| 08 | Duplicate paginations on Payment requests | P2 | UI |
| 09 | 2FA setup weak; seeded weak password | P2 | Security |
| 10 | Super admin missing from Users; mislabelled Email column | P2 | Security / UI |
| 11 | Opaque audit trail; actor/note missing from timeline | P2 | Auditability |
| 12 | Currency/date/number formatting inconsistent | P2 | UI/logic |
| 13 | Mobile dashboard overflow; contextless application cards | P2 | UI/responsive |
| 14 | Asset price has no currency or valuation data | P2 | Logic |
| 15 | Visitor service is free text; percentage undefined | P2 | Data integrity |
| 16 | Placeholder copy and inconsistent page headers | P3 | UI polish |
| 17 | Primary vs extension contracts indistinguishable | P2 | Commercial logic |
| 18 | Service contract creation blocked by stale `start_date` rule | P1 | Logic / validation |

**Not filed as issues:** missing role-permission matrix, no active-session list, Roles "Total 1 users", and login label duplication are included in the main report; they share the admin/UX root causes above or were not deep-tested.

**Evidence handling:** no credentials or live 2FA secrets are stored. The 2FA screenshot was withheld because it contains a secret. Personal data is synthetic demo data.

**Constraints:** findings only. No application code, configuration, tests or business records were modified by the reviewer. One demo record was moved through the UI during testing (case #1 stage), documented in the report.
