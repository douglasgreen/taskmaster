# Project Setup Guide

## Setup Scripts

This project uses the
[GitLab script system](https://github.blog/2015-06-30-scripts-to-rule-them-all/).
To set up the project, run:

```bash
script/setup
```

## Linting, Fixing, and Testing

### PHP

-   Lint: `composer lint`
-   Fix: `composer lint:fix`
-   Test: `composer test`

### JavaScript

-   Lint: `npm run lint`
-   Fix: `npm run lint:fix`
-   Test: `npm run test`

### Fixing PHP

When using prettier with @prettier/plugin-php, PHP is being reformatted with
`npm run lint:fix` and with `composer lint:fix`. You should run
`npm run lint:fix` first and let `composer lint:fix` clean up afterward.

Currently @prettier/plugin-php only supports up to PHP 8.2 so it may give up
with some syntax errors.

## Pre-commit Hooks

Linting and testing are automatically run by `.husky/pre-commit`. Fix any errors
or use `--no-verify` to bypass the check.

## Commitlint

[Conventional Commits](https://www.npmjs.com/package/@commitlint/config-conventional)
are enforced by `.husky/commit-msg`. Fix any lint errors before committing.
