# PREFLIGHT

Source of truth: `docs/db/SCHEMA.sql`

Use this checklist before creating or running new migrations.

## 1) Confirm intended schema change
- [ ] Open `docs/db/SCHEMA.sql` and identify the exact target table/column/index/constraint.
- [ ] Confirm the requested change is not already present in `docs/db/SCHEMA.sql`.
- [ ] If the change is missing from `docs/db/SCHEMA.sql`, update schema design docs first (or align with team decision) before coding migrations.

## 2) Prevent duplicate migration timestamps
- [ ] List existing migration files and ensure your new timestamp is unique:
  - `Get-ChildItem -Name database/migrations | Sort-Object`
- [ ] Double-check for same-prefix collisions (`YYYY_MM_DD_HHMMSS`) before saving the file.
- [ ] If a duplicate already exists (example: `2025_09_02_000026`), regenerate the new migration filename with a new timestamp.

Note: This repo currently has two migrations with timestamp `2025_09_02_000026`. Do not casually rename or delete them, because that can break migration history in existing environments. Treat this as a known legacy issue and address it later with a planned cleanup.

## 3) Prevent missing columns / drift
- [ ] Compare migration edits against `docs/db/SCHEMA.sql` to ensure every required column exists with correct type/null/default.
- [ ] Verify related indexes/foreign keys are included where required by `docs/db/SCHEMA.sql`.
- [ ] For rename/split changes, ensure backward-safe handling (copy/backfill/drop in safe order).

## 4) Validate migration set locally
- [ ] Run migration status and confirm ordering is correct:
  - `php artisan migrate:status`
- [ ] Run migrations on a fresh database:
  - `php artisan migrate:fresh --seed`
- [ ] Run rollback/redo sanity check for the new migration(s):
  - `php artisan migrate:rollback --step=1`
  - `php artisan migrate`

## 5) Final consistency check
- [ ] Re-compare resulting DB structure with `docs/db/SCHEMA.sql`.
- [ ] Ensure no duplicate timestamp filenames remain in `database/migrations`.
- [ ] Include schema/migration notes in PR description (what changed, why, and verification commands run).
