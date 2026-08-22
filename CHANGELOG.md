# Changelog

All notable changes to this project will be documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added (Session 1, 2026-08-22)
- Initial Composer package skeleton: `type: library`, PSR-4 autoload under
  `ArbRajab\ConsentGuard`, `illuminate/*` component dependencies (not the
  full `laravel/framework`), auto-discovered `ConsentGuardServiceProvider`
  stub.
- Orchestra Testbench test harness with a passing placeholder test
  proving the infrastructure itself works, ahead of any real feature.
- Tooling: Laravel Pint, Larastan (PHPStan level 8, clean against the
  current skeleton).
- CI: lint, static analysis, tests, a Laravel 12/13 compatibility matrix,
  gitleaks, CodeQL, and osv-scanner.
- Governance: MIT license, README (with the reuse justification for a
  second Laravel repository in this portfolio and a four-session
  roadmap), CONTRIBUTING, SECURITY, CODE_OF_CONDUCT.

No feature code (audit-log, consent-guard) exists yet — see
[`HANDOFF.md`](HANDOFF.md) and the README's Roadmap section.
