# Login Support by thisismyurl.com

WordPress plugin to support stealth login URL shifting and admin-side login security utilities.

## Current Version

`1.6112` (format: `1.Yddd`)

- `Y` = last digit of the current year.
- `ddd` = Julian day number.
- Example for April 22, 2026: year digit `6` + day `112` => `1.6112`.

## Features

- Stealth Mode toggle for custom login slug behavior.
- Secret slug field with sanitization.
- Recovery Mode with one-time recovery token generation.
- Login rate limiting with configurable thresholds and lockout windows.
- Security Event Log with retention controls and clear action.
- Site Health integration for security posture checks.
- Force Global Logout admin action for active sessions.
- Shared core admin framework and UI components.

## Admin Controls

- Recovery Mode: enable/disable and token lifetime (5-180 minutes).
- Rate Limiting: enable/disable, failed-attempt threshold, attempt window, lockout duration.
- Event Logging: enable/disable, retention window (days), manual log clearing.
- Security Utilities: one-click global session logout and one-time recovery link generation.

## Standards and Compliance

This repository has been updated for WordPress.org submission readiness:

- Complete plugin header metadata in the main plugin file.
- Localization-ready strings and text domain usage.
- Nonce and capability checks for privileged actions.
- Sanitization and escaping improvements across admin output.
- DRY admin assets (shared JS/CSS in core, compatibility wrappers in assets).
- WordPress.org-compatible `readme.txt` added.

## Installation

1. Copy this plugin to `wp-content/plugins/thisismyurl-login-support`.
2. Activate the plugin in WordPress admin.
3. Open `Settings > Login Support`.
4. Enable Stealth Mode and set a secret slug.
5. Configure Recovery Mode, rate limiting, and security event logging.
6. Save and test login access in a private browser session.

## Security Feature Behavior

- Recovery Mode: generates a single-use tokenized URL that grants temporary login bypass for the current IP.
- Rate Limiting: tracks failed attempts by username+IP and applies timed lockouts.
- Event Logging: records key security actions and authentication events with retention pruning.
- Site Health: reports whether key plugin protections are enabled.

## Development Notes

- Main plugin bootstrap: `thisismyurl-login-support.php`
- Shared core class: `core/class-timu-core.php`
- Shared admin assets: `core/assets/shared-admin.js`, `core/assets/shared-admin.css`
- Legacy asset compatibility wrappers: `assets/admin-script.js`, `assets/admin-style.css`

## License

GPL-2.0-or-later

---

## About This Is My URL

This plugin is built and maintained by [This Is My URL](https://thisismyurl.com/), a WordPress development and technical SEO practice with more than 25 years of experience helping organizations build practical, maintainable web systems.

Christopher Ross ([@thisismyurl](https://profiles.wordpress.org/thisismyurl/)) is a WordCamp speaker, plugin developer, and WordPress practitioner based in Fort Erie, Ontario, Canada. Member of the WordPress community since 2007.

### More Resources

- **Plugin page:** [https://thisismyurl.com/thisismyurl-login-support/](https://thisismyurl.com/thisismyurl-login-support/)
- **WordPress.org profile:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **Other plugins:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **Website:** [thisismyurl.com](https://thisismyurl.com/)

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
