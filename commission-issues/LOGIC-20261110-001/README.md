# LOGIC-20261110-001 — Counselor responsible-employee selector

## Status: NOT A DEFECT

The “Responsible employees” field is disabled when signed in as a counselor because `assign_responsible_employees` is a separate permission. That behavior is intentional and was confirmed by the user.

The field becomes usable after adding a role that carries the permission, and the intended workflow is for a super admin to assign responsible employees to the case.

This folder is retained only as part of the test track record. No product defect is reported here.

## Evidence

- `evidence/counselor-responsible-disabled.png` — counselor view before the permission grant
- `evidence/counselor-responsible-enabled-after-manager.png` — field enabled after adding Manager
- `evidence/counselor-private-created.png` — private contract created under the counselor account
