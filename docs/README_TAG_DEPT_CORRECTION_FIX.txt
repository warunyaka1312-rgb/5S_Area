5S SMART | ALUMET - Tag correction update

- Adds unit dropdown values split from comma-separated area/unit strings (e.g. 4,5,6 becomes separate options).
- Prevents creating duplicate Tag No values; existing duplicate records remain so each can be edited or deleted.
- Adds a delete button with confirmation.
- Adds corrective_action, corrective_by, corrective_date and verification_note fields using additive SQLite migration.
- On edit, system fields Tag No, department, unit and system dates are read-only; corrective-action fields remain editable.
- Existing db/database.sqlite and uploads/tag_photos must be retained during deployment.

IMPORTANT ACCESS CONTROL NOTE:
The current project package does not contain an authentication/user-role system (no users table/session login was found). Therefore this change separates system fields from corrective fields in the form, but cannot securely restrict editing to a specific department administrator until login and department-role accounts are integrated. Do not expose this page to an untrusted network before role-based authentication is added.
