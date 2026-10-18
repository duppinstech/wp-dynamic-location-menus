# AGENTS.md

WordPress plugin `duppins-technology/dynamic-location-menus`. It rewrites nav menu links from location context. Proprietary. PHP >= 7.4, Composer.

## Commands

Install:

```bash
composer install --no-dev --optimize-autoloader
```

Lint (`php -l` on plugin PHP files, skips `vendor/`):

```bash
composer lint
```

Test (smoke check only; does not boot WordPress):

```bash
composer test
```

Build the release zip. This is what `.github/workflows/release.yml` runs when a `v*` tag is pushed. Replace `VERSION` with the tag version without the leading `v`.

```bash
composer install --no-dev --optimize-autoloader --no-interaction
mkdir -p build/dynamic-location-menus
rsync -a --exclude='.git' --exclude='.github' --exclude='build' --exclude='.gitignore' --exclude='composer.lock' ./ build/dynamic-location-menus/
cd build && zip -r "../dynamic-location-menus-VERSION.zip" dynamic-location-menus
```

Do not tag, push, or publish a release unless the task says so.

## Conventions

- Indent PHP, JS, and CSS with 4 spaces. Line endings are LF. `*.ps1`, `*.bat`, and `*.cmd` use CRLF.
- Branch from `main` as `type/short-description` (`feat/`, `fix/`, `chore/`, `docs/`, `ci/`).
- Commits use Conventional Commits: `type: summary`.
- Do not commit to `main`.
- Keep plugin behavior changes out of docs and CI commits.

## Do not touch

- `vendor/` is Composer output. It is gitignored. Release zips include it. Do not commit it.
- Do not change `includes/`, `assets/`, `dynamic-location-menus.php`, or `uninstall.php` unless the task is a product change.
- Do not edit `LICENSE.md` terms or rewrite `SECURITY.md` or `CHANGELOG.md` history. Add a changelog entry only when a product change needs one.
- Do not commit `.env`, tokens, `CLAUDE.local.md`, or `.claude/settings.local.json`.
- Do not force-push, run `git reset --hard`, or run `git clean`.
