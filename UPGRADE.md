# Upgrade Guide

## Nothing to upgrade from yet

`v1.0.0` is this package's first tagged release — there is no prior
version whose breaking changes need documenting here. This file exists
now, ahead of actually needing content, so that the *first* real breaking
change has an obvious place to land rather than prompting "where do we put
this?" as its own small decision later.

When a future release changes public API behavior in a way that requires
action from an adopting application, it will be documented here as:

```
## Upgrading from vX to vY

- What changed, and why.
- The exact code/config change an adopting app needs to make.
```

## Laravel version support

This is not the same question as upgrading *this package's* major
version, but is worth stating here since it's the other kind of
compatibility boundary a consumer might look for an "UPGRADE.md" for:

- `v1.0.0` supports Laravel 12.x and 13.x (via `illuminate/*` component
  constraints `^12.0|^13.0`) with **identical behavior on both** — nothing
  about this package's public API or feature set differs by Laravel major.
  The CI matrix (`.github/workflows/ci.yml`) runs the full test suite
  against PHP 8.2–8.4 × Laravel 12/13 to confirm this on every change, not
  just at release time.
- Moving an adopting application from Laravel 12 to 13 requires no
  corresponding change to how this package is used. If that ever stops
  being true (a future Laravel major requiring a behavior change here),
  it will be documented in this file the same way a package-version
  upgrade would be.
