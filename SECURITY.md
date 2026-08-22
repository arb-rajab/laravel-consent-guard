# Security Policy

## Supported versions

No tagged release exists yet — this package is at Session 1 of its
governance-and-skeleton stage (see [`HANDOFF.md`](HANDOFF.md)). Once
`v1.0.0` ships (targeted for Session 4), this table will list which
Laravel-major-version lines receive security fixes, matching the CI
compatibility matrix in [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities.

Instead, use GitHub's private vulnerability reporting (Security tab →
"Report a vulnerability"), or email yaeouk@gmail.com directly.

Please include:
- A description of the vulnerability and its potential impact
- Steps to reproduce
- Affected version/commit

## Disclosure process

1. Acknowledgement within 5 business days.
2. Assessment and severity rating (informal CVSS).
3. Fix developed on a private branch where feasible.
4. Coordinated disclosure once a fix is released, with credit to the
   reporter unless anonymity is requested.

## Scope

This policy covers this package's own source (`src/`), its GitHub Actions
workflows, and its published Packagist releases. It does not cover
third-party dependencies (report those upstream) or the host applications
that consume this package.
