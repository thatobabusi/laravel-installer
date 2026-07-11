# GitHub Actions

| Workflow | Trigger | Purpose |
| --- | --- | --- |
| `tests.yml` | Push, pull request, daily | Runs the PHP/Laravel compatibility matrix. |
| `static-analysis.yml` | Push, pull request | Runs static analysis. |
| `docs.yml` | Push, pull request | Fails when generated command documentation is stale. |
| `docs-sync.yml` | Command-source push, weekly schedule, manual | Regenerates `docs/reference` and commits only documentation drift. |
| elease.yml | Manual | Validates a version, updates in/laravel, tags, pushes, and creates a GitHub Release. |
| update-changelog.yml | Release | Uses Laravel's shared changelog workflow. |

`docs-sync.yml` follows the repository-automation pattern used by `thatobabusi/thatobabusi`: scheduled/manual maintenance, narrow write permissions, deterministic generation, and an idempotent bot commit with `[skip ci]`.