# Contributing to FormSummaryBundle

Thanks for your interest in improving FormSummaryBundle.

## Before you start

Check the [README](README.md), existing issues, and recent changes before proposing work. For a significant change to the public API, open an issue to discuss the intended behavior first. Small, focused bug fixes can be submitted directly.

Please do not include credentials, personal data, or sensitive application form values in reports, logs, tests, or screenshots. See the [security policy](SECURITY.md) for private vulnerability reporting.

## Development environment

The bundle supports PHP 8.2+ with compatible Symfony 6.4, 7.4 and 8.x versions. Install Composer and the PHP extensions required by the development suite (including `intl`, `mbstring`, `dom`, and `xml`).

```bash
composer install
composer validate --strict
composer qa
composer audit --locked
```

`composer qa` runs PHPUnit, PHPStan level 8, and PHP syntax checks. The GitHub Actions matrix also exercises supported Symfony/PHP combinations, lower supported dependency versions, and installation without development dependencies.

## Contributions

- Keep changes narrow in scope and preserve backward compatibility for the documented `1.x` API.
- Use `declare(strict_types=1);` in PHP files. Follow the existing namespaces and style.
- Add or update PHPUnit tests for behavior changes, especially field visibility, escaping, translations, and compatibility with Symfony form themes.
- Keep `@internal` implementation details out of public API guarantees.
- Update the README or changelog when a user-facing behavior or supported requirement changes.
- Avoid unrelated refactoring, generated files, and dependency changes that are not needed for the proposed change.

## Pull requests

Create a branch from `main`, explain the change and how it was verified, then open a focused pull request. Use the [pull request template](.github/PULL_REQUEST_TEMPLATE.md). Automated checks must pass before merging. If a test cannot run in your environment, state that clearly in the PR description.

Maintainers decide when a change is merged or released. Version tags and published releases are created separately from ordinary contributions.
