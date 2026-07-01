# migration_duplicates

- Generated at: 2026-07-01T22:45:00+09:00
- Note: This is a heuristic scan of migration filename timestamps. Use it to find suspicious duplicates quickly.

## Result

There is one known legacy duplicate timestamp:

| timestamp | files | handling |
|---|---|---|
| `2025_09_02_000026` | `2025_09_02_000026_add_entry_period_to_tournaments_table.php` / `2025_09_02_000026_add_unique_to_teb.php` | Both are already applied in the current DB. Do not casually rename or delete them because that can break migration history in existing environments. |

No other duplicate migration timestamps were detected.
