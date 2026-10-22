# CLEANUP MANIFEST — ONLINE BOOKSTORE

Date: 2026-10-22
Author: Antigravity Automated Deep Production Audit & Cleanup

This document records all files audited for potential deletion or quarantine in accordance with CRITICAL RULE 7:
> "Before deleting files, create a deletion manifest listing each candidate, its purpose, evidence that it is unnecessary, references checked, and deletion risk. Prefer moving approved-to-delete files into a timestamped quarantine folder outside the active source tree when practical, rather than permanently deleting them immediately."

Quarantine Destination: `F:\flutter\online_bookstore\quarantine_20261022\`

---

## 1. Candidate Files & Audit Verdict

| # | File Path | Original Purpose | References Checked | Risk Level | Action | Verification |
|---|---|---|---|---|---|---|
| 1 | `online-bookstore-api/bak.txt` | Raw text backup from earlier controller changes | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Preserved in quarantine; not needed by Laravel runtime |
| 2 | `online-bookstore-api/check_guest.php` | Scratch script testing guest auth endpoint | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Contains syntax error (`x <?php`); tests in `tests/Feature/` cover this |
| 3 | `online-bookstore-api/check_user.php` | Scratch script querying user model | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Manual debugging script; feature tests cover user queries |
| 4 | `online-bookstore-api/check_user2.php` | Scratch script testing password verification | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Manual debugging script; feature tests cover password hashing |
| 5 | `online-bookstore-api/dbg.bin` | Binary debug output | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Ephemeral debug binary |
| 6 | `online-bookstore-api/dbg.py` | Python script for testing API endpoints | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Flutter and Pest/PHPUnit tests supersede ad-hoc Python script |
| 7 | `online-bookstore-api/patch_test.py` | Python script testing PATCH route | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Laravel feature tests verify PATCH routes |
| 8 | `online-bookstore-api/routes.json` | Dump of Laravel routes from `route:list --json` | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Generated route list; dynamically regenerable via `php artisan route:list` |
| 9 | `online-bookstore-api/t.php` | Scratch script testing route dispatcher | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Manual debugging snippet |
| 10 | `online-bookstore-api/t.txt` | Scratch text output | `app/`, `routes/`, `config/`, `tests/` — 0 references | Very Low | **Quarantine** | Scratch text |
| 11 | `online-bookstore-api/testing.sqlite` | Temporary local SQLite database created during manual test run | Config uses MySQL / in-memory SQLite for tests | Very Low | **Quarantine** | Active database is MySQL on port 3306; test suite uses `:memory:` |
| 12 | `bookstore_app/build_err.txt` | Stale Flutter build error output | Flutter project files — 0 references | Very Low | **Quarantine** | Build artifact; not part of source code |
| 13 | `bookstore_app/build_out.txt` | Stale Flutter build log output | Flutter project files — 0 references | Very Low | **Quarantine** | Build artifact; not part of source code |
| 14 | `mysqld_err.log` | Empty 0-byte log file | Root directory | Very Low | **Quarantine** | 0 bytes; MySQL daemon logs to XAMPP / task stdout |
| 15 | `mysqld_out.log` | Empty 0-byte log file | Root directory | Very Low | **Quarantine** | 0 bytes; MySQL daemon logs to XAMPP / task stdout |
| 16 | `mysql_install.err.log` | Empty 0-byte log file | Root directory | Very Low | **Quarantine** | 0 bytes; one-off setup log |
| 17 | `mysql_install.log` | One-off MySQL initialization output log | Root directory | Very Low | **Quarantine** | Informational setup log |

---

## 2. Essential Files Strictly Retained (NOT Deleted)

| File / Directory | Reason Retained |
|---|---|
| `F:\flutter\online_bookstore\mysql.ini` | Required configuration file for standalone MySQL server daemon |
| `F:\flutter\online_bookstore\mysql_data\` | MySQL active database directory containing `bookstore` schema and tables |
| `F:\flutter\online_bookstore\database-backup\` | Historical SQL database dumps and schema backups |
| `online-bookstore-api/.env` | Environment configuration containing active DB credentials, app key, and API settings |
| `online-bookstore-api/.env.example` | Template for environment configuration |
| `online-bookstore-api/database/migrations/*` | 14 applied Laravel schema migrations |
| `bookstore_app/pubspec.yaml`, `pubspec.lock` | Flutter package declarations and exact dependency lockfile |
| `bookstore_app/analysis_options.yaml` | Flutter linter rules |
| `bookstore_app/lib/**/*` | All 123 source files verified as actively imported and used |
