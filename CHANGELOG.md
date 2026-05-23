# Changelog

All notable changes to **Login Support by thisismyurl.com** are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses the `x.Yddd` versioning scheme defined in the project
release rules (`x` = release class, `Y` = last digit of year, `ddd` = Julian day).

## [1.6143] — 2026-05-23

### Changed
- Promoted to a full release (class 1). The `0.6xxx` line was pre-release on the `x.Yddd` scheme.
- Standardized the donation link to GitHub Sponsors (`https://github.com/sponsors/thisismyurl`).

## [0.6126] — 2026-05-06

### Added
- Honeypot username trap (issue #37): configurable list (default: `admin`, `root`, `administrator`). Any login attempt against a honeypot name that has no matching user triggers an immediate extended IP ban. List and ban duration are configurable in settings.
- fail2ban-compatible file log sink (issue #34): opt-in, off by default. Writes one line per failed login (`datetime ip username`) to a configurable absolute path outside the web root. `docs/fail2ban-filter.conf` included with filter definition and `jail.local` example.
- REST endpoint `GET /wp-json/timu-login-support/v1/lockouts` (issue #31): read-only, `manage_options` auth, returns 403 to unauthorized callers. Backed by a non-autoloaded lockout registry maintained on each lockout event (200-row cap, expired entries pruned on write).
- 24-hour failed-login sparkline on the settings page (issue #35): pure `<canvas>`, no external JS. Accessible `aria-label` and `<details>` hourly-breakdown table fallback.
- Two Factor compatibility panel in settings (issue #29): shows detected/not-detected status, documents yield behavior and override filter, lists tested compatible plugins.
- `is_two_factor_active()` public method — extracts 2FA detection from `should_skip_for_2fa()` so the UI can surface interop status without duplicating logic.
- Lockout registry (`LOCKOUT_REGISTRY` constant + `record_lockout()` method) — makes active lockouts enumerable for the REST endpoint and ops dashboards.
- Honeypot and fail2ban settings rows in the Configuration table.
- `docs/fail2ban-filter.conf` — ready-to-use fail2ban filter and jail.local example.
- fr_CA translation and POT file (issue #1).
- `updater.php` — GitHub Releases-backed self-update mechanism; removed when moving to WordPress.org.
- `CODE_OF_CONDUCT.md`, `SUPPORT.md`.

### Changed
- `should_skip_for_2fa()` visibility unchanged (private); `is_two_factor_active()` added as the public entry point for UI use.
- readme.txt: Translations section, REST API section, updated Features list, brute-force/fail2ban tags added.
- SECURITY.md: Log Storage Architecture section added, documenting the 500-entry ring-buffer, trade-offs, and future custom-table roadmap.

## [0.6123] — 2026-05-03

### Security
- **CRITICAL**: Fixed inverted rate-limiter (advisory `GHSA-p369-rjwx-f44g`).
  A correct password could bypass an active lockout because the lockout
  check ran after WordPress had already validated the password.
  Enforcement now hooks `wp_authenticate` priority 5 and halts BEFORE the
  password check runs. **All users on 0.6112 should update immediately.**
- Hardened proxy-IP trust. `X-Forwarded-For` and `CF-Connecting-IP` are
  no longer trusted unconditionally; opt in via filter. Cloudflare path
  additionally requires REMOTE_ADDR to be inside Cloudflare's published
  ranges.
- Recovery-bypass moved from IP-bound to session-cookie-bound (HttpOnly,
  Secure on HTTPS, SameSite=Lax). Lookup transient is keyed by SHA-256 of
  the cookie value, so DB read access alone cannot leak the bypass token.
- Recovery token storage moved out of the autoloaded `_options` array
  into a dedicated transient.
- `Referrer-Policy: no-referrer` emitted on the recovery URL hit.

### Added
- Per-IP global lockout, independent of username, defending against
  credential-stuffing / username rotation. Tunable via
  `thisismyurl_login_support_ip_attempt_multiplier` (default 4×).
- Application Password failure carve-out — failed app-password attempts
  no longer count against the browser-login budget.
- 2FA-plugin compatibility: yields by default when Two Factor / WP 2FA /
  miniOrange 2FA is active. Override via
  `thisismyurl_login_support_skip_for_2fa`.
- Constant escape hatch: `define( 'TIMU_LOGIN_SUPPORT_DISABLE', true );`
  in `wp-config.php` fully bypasses the plugin.
- WP-CLI commands: `wp login-support unlock|reset-slug|generate-recovery|logs`.
- `uninstall.php` cleans every option, transient, and cron event on
  plugin removal.
- Recovery-token generation event now records which admin user
  generated the token.
- Logging-toggle change is itself logged out-of-band so the disable event
  is preserved in the audit trail.

### Changed
- Replaced PHP 8.1+ deprecated `filter_input(..., FILTER_SANITIZE_FULL_SPECIAL_CHARS)`
  with `sanitize_text_field( wp_unslash( $_GET[...] ?? '' ) )`.
- Bumped tested-up-to to 6.9.

### Fixed
- `readme.txt` placeholder GitHub URL (`thisismyurl/[plugin-name]`)
  replaced with the real repo URL.
- `readme.txt` changelog/header version drift (1.6112 vs 0.6112).
- `core/class-timu-core.php` no longer falls back to a stale `1.6112`
  version when the plugin constant is missing.

## [0.6112]

### Added
- One-time recovery token mode with lifetime control.
- Configurable login rate limiting and lockout protection.
- Security event logging with retention controls and a clear action.
- Site Health security-posture test.
- Settings UI for all controls.
