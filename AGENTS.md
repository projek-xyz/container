# AGENTS: projek-xyz/container

A PHP PSR-11 dependency-injection container library. PSR-4 `Projek\` → `src/`, PHP >= 8.4, PSR-12, Kahlan specs, Conventional Commits.

## Quickstart Commands

| Action | Command |
|--------|---------|
| Run all checks (lint + test) | `composer test` |
| Format code (Pint, Laravel preset) | `composer format` |
| Lint code (Pint, Laravel preset) | `composer lint` |
| Run specs/Kahlan tests | `composer spec` |
| Install dependencies | `composer install` |

**Notes:**
- Run a single spec file: `composer spec -- --spec=tests/spec/Container.spec.php` (or `--grep='*Container*'`).

## Project Structure

```
src/                  # Source code (PSR-4: Projek\):
  Container.php
  Container/
    AbstractContainerAware.php
    ContainerAware.php
    EntryCollector.php
    Events/
      AfterRegistration.php
      AfterResolution.php
      BeforeRegistration.php
      BeforeResolution.php
      Dispatcher.php
      ListenerProvider.php
    Exception.php
    HasContainer.php
    InvalidArgumentException.php
    NotFoundException.php
    Resolver.php
    UnresolvableArgumentException.php
tests/
  spec/               # Kahlan specifications dir
    Container.spec.php
    ContainerAware.spec.php
    EntryCollector.spec.php
    Resolver.spec.php
  stub/               # Test stubs and fixtures dir
  config.php          # Kahlan config (coverage, stubs dir)
```

## Coding Standards

- **Formatter**: `composer format` runs Pint with the Laravel preset
- **Linter**: `composer lint` runs Pint with the Laravel preset (`--test` mode fails on discrepancies)
- Both use the project's `composer.json` scripts
- PHP version requirement: `>=8.4` (defined in `composer.json`). Keep `src/` and `tests/` compatible with declared minimum PHP version.

## Testing

- Test framework: **Kahlan** (v6.x)
- Spec files live in `tests/spec/**/*.spec.php` and use `describe`/`it` + `expect()` syntax
- Spec files should mirrors `src/` files structures: `src/Foo.php` -> `tests/spec/Foo.spec.php`; `src/Foo/Bar.php` -> `tests/spec/Foo/Bar.spec.php`
- Stub file live in `tests/stub/` with `Stubs\` PSR-4 prefix (autoload-dev)
- Run all tests with `composer spec`
- CI runs tests on PHP 8.4–8.5 matrix (`.github/workflows/tests.yml`), local dev pins PHP 8.4 via `.tool-versions` (asdf/mise)
- The suite currently reports 100% coverage (125/125 statements) — new `src/` code needs specs to keep it there (CI coverage driver: xdebug)

## Git workflow

- **Commit messages must be Conventional Commits** (`feat:`, `fix:`, `chore:`, ...) — enforced by the `commit-msg` hook (commitlint); non-conforming messages are rejected. Skip hooks with `--no-verify` flag.
- `pre-commit` runs lint-staged, which applies `vendor/bin/pint --preset laravel` to staged `*.php` files (Pint fixes never block the commit)
- Hooks are installed by the `prepare` script on `npm install` (`simple-git-hooks`); `opencode.json`/`.opencode`/`.ai` are gitignored
- Release: `npm run release` (commit-and-tag-version); pushing a `v*.*.*` tag triggers the `publish` workflow (release + wiki sync)

## Conventions

- All instantiable source files use `declare(strict_types=1)`, **except** Exception classes and Interface
- Files are namespaced under `Projek\` (or `Stubs\` for test stubs, fixtures and helpers)
- Classes and functions **must be explicitly qualified**: either a `use` import or a `\` prefix — **never a bare call in a `strict_types` file** (e.g. DO `use function sprintf;` + `sprintf();`, or `\sprintf();`; DON'T call `sprintf();` unqualified)
- Documentation available in `.github/wiki/` is synced to the GitHub wiki by the `wiki` job in `.github/workflows/publish.yml` when a `v*.*.*` tag is pushed.
- Sample PHPMD config is available in `tests/phpmd.xml`;
