# Contributing

## Branch naming

Branch from `main` using `type/short-description`.

- `feat/` for a feature
- `fix/` for a bugfix
- `docs/` for documentation
- `ci/` for GitHub Actions and repo automation
- `chore/` for maintenance that does not change plugin behavior

Do not commit directly to `main`.

## Commit style

Use [Conventional Commits](https://www.conventionalcommits.org/): `type: summary`.

Types match the branch prefixes above, plus `test` when the only change is the test command. Keep each commit to one concern. Do not mix plugin behavior changes with docs or CI.

## Pull request flow

1. Open a pull request into `main` and fill in the pull request template.
2. CI runs `composer lint` and `composer test` on the pull request.
3. Merge after that workflow is green.
4. Publish a release by tagging `v*` on `main`. The release workflow builds the plugin zip. Do not tag from a feature branch.
