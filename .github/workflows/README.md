# GitHub Actions

| Workflow | Trigger | Purpose |
| --- | --- | --- |
| `tests.yml` | Push, pull request, daily | Runs the PHP/Laravel compatibility matrix. |
| `static-analysis.yml` | Push, pull request | Runs static analysis. |
| `docs.yml` | Push, pull request | Fails when generated command documentation is stale. |
| `docs-sync.yml` | Command-source push, weekly schedule, manual | Regenerates `docs/reference` and commits only documentation drift. |
| `upstream-sync.yml` | Weekly schedule, manual | Merges `laravel/installer` master into the fork; opens a PR on a clean merge or an issue on conflict. |
| `release.yml` | Manual | Validates a version, updates `bin/laravel`, tags, pushes, and creates a GitHub Release. |
| `update-changelog.yml` | Release | Uses Laravel's shared changelog workflow. |

`docs-sync.yml` and `upstream-sync.yml` follow the repository-automation pattern used by `thatobabusi/thatobabusi`: scheduled/manual maintenance, narrow write permissions, deterministic generation, and idempotent bot commits.
