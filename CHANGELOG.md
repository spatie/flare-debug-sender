# Changelog

All notable changes to `flare-debug-sender` will be documented in this file.

## 2.0.1 - 2026-11-06

### Testing toolchain

- Upgrade the development-only Pest dependency from v3 to v5.3 (#12).
- Migrate the PHPUnit configuration and generate coverage reports only when explicitly requested with `composer test-coverage`.
- Preserve intentional Ray debugging output while continuing to forbid `dd()` and `dump()` in architecture checks.
- Replace the placeholder test with a public sender/file-channel test covering error payload output and natural attribute ordering.
- Validate Composer metadata, platform requirements, and security advisories in the Ubuntu/Windows lowest/latest dependency matrix.

### Compatibility

Development dependencies require PHP 8.4.1 or later. Runtime requirements remain PHP ^8.2 and spatie/flare-client-php ^3.0; sender behavior and the Illuminate Support ^10|^11|^12 development constraint are unchanged. PR #11 (Illuminate Support 13) is not included.

### Other changes since 2.0.0

- Update actions/checkout to v7 (#10).
- Harden the Dependabot auto-merge author/repository guard.
- Refresh README badges and Spatie-hosted advertisement assets; apply source formatting only.

**Full Changelog**: https://github.com/spatie/flare-debug-sender/compare/2.0.0...2.0.1

## 2.0.0 - 2026-05-06

- Release for the v3 versions of the Flare packages

## 1.0.12 - 2025-10-21

wip

## 1.0.11 - 2025-10-10

wip

## 1.0.6 - 2025-08-26

- Allow defining an alternative sender

## 1.0.5 - 2025-07-17

- Allow sending to non verified https hosts"

## 1.0.4 - 2025-07-04

- Add new logging channels

## 1.0.3 - 2025-05-23

**Full Changelog**: https://github.com/spatie/flare-debug-sender/compare/1.0.2...1.0.3

## 1.0.2 - 2025-05-23

**Full Changelog**: https://github.com/spatie/flare-debug-sender/compare/1.0.1...1.0.2

## 1.0.1 - 2025-05-23

**Full Changelog**: https://github.com/spatie/flare-debug-sender/compare/1.0.0...1.0.1

## 1.0.0 - 2025-05-23

**Full Changelog**: https://github.com/spatie/flare-debug-sender/commits/1.0.0
