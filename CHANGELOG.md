# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Conventional Commits.

## [2025-10-02]

### Added
- CLI: scenario:run now supports nested scenarios (subfolders) and resolves by name or group path (e.g., `profiles/client_profile`).
- Progress output for scenario:run with per-step short results table.
- Optional `--save-report` flag to persist a compact run report.

### Fixed
- Inconsistent behavior between scenario:list (group-aware) and scenario:run (root-only). scenario:run now falls back to grouped loader.

### Notes
- Backward compatible with root-level scenarios.
- Verified runs for `client_profile` by name and by full path.
