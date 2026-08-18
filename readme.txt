=== Login Support by Christopher Ross ===
Contributors: thisismyurl
Plugin URI: https://thisismyurl.com/thisismyurl-login-support/
Author: Christopher Ross
Author URI: https://thisismyurl.com/
Donate link: https://github.com/sponsors/thisismyurl
Tags: login, security, rate limit, brute force, fail2ban
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6174.1642
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Harden your site login workflow with a custom login slug and admin security controls.

== Description ==

Login Support helps administrators reduce automated login probing by using a custom login path and layered login controls.

Features:

* New-IP email verification — when a user logs in from an unfamiliar IP, a 6-digit code is emailed and must be entered before the session is granted.
* Enable or disable Stealth Mode for login URL shifting.
* Set and validate a custom secret login slug.
* Generate one-time recovery links for safe access restoration.
* Enable login rate limiting with configurable thresholds.
* Honeypot username trap — auto-ban IPs that probe known attacker targets (admin, root, administrator) when those users don't exist.
* fail2ban-compatible log file sink (opt-in) with a ready-to-use filter config.
* REST API endpoint to query active lockout state (`GET /wp-json/timu-login-support/v1/lockouts`).
* 24-hour failed-login sparkline on the settings page.
* Two Factor compatibility panel — works alongside Two Factor, WP 2FA, and miniOrange.
* Track security events and control log retention.
* Review plugin posture in WordPress Site Health.
* Force logout all active user sessions from the settings page.

Admin Controls:

* Recovery Mode on/off and token lifetime (minutes).
* Rate limiting on/off, max failed attempts, attempt window, and lockout duration.
* Event logging on/off and retention period (days).
* Log clearing and one-time recovery token generation actions.

Important:

* Keep your custom login slug private.
* If you lose access to your custom slug, disable the plugin from WP-CLI or by renaming the plugin folder.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/thisismyurl-login-support/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings > Login Support.
4. Enable Stealth Mode and set your custom slug.
5. Configure recovery, rate limiting, and logging controls.
6. Save settings and test the new login URL in a private browser window.

== Frequently Asked Questions ==

= Can this fully block targeted attacks? =

No. It reduces exposure to common automated probes, but it is not a complete security solution by itself.

= What happens if I forget my custom slug? =

You have four recovery paths, in increasing severity:

1. **Generate a recovery link from the admin UI** (Settings > Login Support). The token is single-use and expires.
2. **WP-CLI**: `wp login-support generate-recovery` prints a fresh recovery URL to stdout. You can also unlock yourself directly with `wp login-support unlock <your-username>` or wipe the secret slug entirely with `wp login-support reset-slug`.
3. **Constant escape hatch**: add `define( 'TIMU_LOGIN_SUPPORT_DISABLE', true );` to wp-config.php. The plugin honours it immediately and `/wp-login.php` becomes reachable again. Remove the constant once you're back in.
4. **Last resort**: rename the plugin folder via SFTP/SSH, or `wp plugin deactivate thisismyurl-login-support`.

= Does the plugin include brute-force login controls? =

Yes. You can enable login rate limiting and tune:

* maximum failed attempts
* attempt window in minutes
* lockout duration in minutes

= Can I review security events? =

Yes. The settings page includes a Security Event Log table. You can configure retention days or clear logs at any time.

= Does this plugin replace SSL, firewalls, or 2FA? =

No. Use it alongside HTTPS, strong passwords, MFA/2FA, and a security policy.

== Support, Contributing & Sponsorship ==

= I want to support you =

I'm building these tools because WordPress developers and site owners deserve straightforward, practical solutions. There's no tracking, no ads, and you don't need to pay to use these plugins.

If they're helpful, here are genuine ways to support the work:

* **Sponsor this project:** Visit https://github.com/sponsors/thisismyurl if sponsorship fits your budget. Sponsorship helps, but it's always optional.
* **Contribute code or ideas:** Opening a pull request, reporting an issue, or testing edge cases is just as valuable as sponsorship. Helping me improve these plugins is a great way to contribute.
* **Share your experience:** A review on my [Google My Business profile](https://business.google.com/refer) or a follow on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps others find this work.

= I found a bug or have a feature idea =

* **File an issue on GitHub:** Visit https://github.com/thisismyurl/thisismyurl-login-support/issues and include your WordPress and PHP version.
* **Start a discussion:** Use the Discussions tab on GitHub for questions or ideas.

= I want to contribute code =

Code contributions are welcome and genuinely valuable:

1. Fork the repository on GitHub.
2. Create a feature branch (e.g., `feature/improve-safety`).
3. Make your changes and test thoroughly.
4. Follow WordPress coding standards.
5. Open a pull request with a clear description of what changed and why.

I review PRs thoughtfully and appreciate well-tested contributions. Contributing is never required, but it's genuinely helpful.


== Changelog ==

= 1.6174.1642 =
* Added new-IP email verification — users logging in from an unfamiliar IP receive a 6-digit code by email and must enter it before the session is granted.
* Added rate limiting on the verification form: after 5 wrong-code attempts the pending code is voided and the user must log in again (prevents OTP brute-force).
* Changed OTP generation from wp_rand() to random_int() (CSPRNG) for cryptographically secure codes.
* Removed GitHub-specific plugin headers (GitHub Plugin URI, Update URI) and updater bootstrap in preparation for WordPress.org hosting.
* Raised Requires at least from 6.0 to 6.4 (sanitize_url() introduced in 6.1; 6.4 matches plugin header).

= 1.6149 =
* Accessibility/security: "Clear Logs" is now a real button in a nonce-protected POST form rather than a destructive GET link, matching the Force Global Logout control.
* Uninstall: the lockout registry option (`_lockouts`) is now removed on plugin deletion — it previously survived uninstall as one orphaned row.

= 1.6148 =
* Accessibility (WCAG 2.2 AA): the five settings toggle switches now expose a programmatic name via `aria-labelledby`, plus `aria-expanded`/`aria-controls` for the conditional rows they reveal.
* Accessibility: the Security Event Log table gained `scope="col"` column headers and a caption; the failed-login sparkline now references its hourly-breakdown table via `aria-describedby`.
* Accessibility/security: "Force Global Logout" is now a real button in a nonce-protected POST form rather than a destructive GET link.

= 1.6147 =
* Added WordPress 7.0 Abilities API support: the read-only `thisismyurl-login-support/get-lockout-status` ability reports active login lockouts to admins and AI agents (manage_options only). The secret login slug is never exposed.
* Unified plugin versioning to the x.Yddd calendar-version scheme.
* Confirmed compatibility with WordPress 7.0.


= 1.6143 =
* First full release (class 1). The 0.6xxx line was pre-release on the `x.Yddd` scheme.
* Standardized the donation link to GitHub Sponsors.

= 0.6126 =
* Added honeypot username trap (issue #37) — configurable list, extended IP ban, log event.
* Added fail2ban-compatible file log sink (issue #34) — off by default, path-validated, `docs/fail2ban-filter.conf` included.
* Added REST endpoint `GET /wp-json/timu-login-support/v1/lockouts` (issue #31) — read-only, `manage_options` auth.
* Added 24-hour sparkline of failed-login attempts on settings page (issue #35) — pure canvas, accessible text fallback.
* Added Two Factor compatibility panel to settings page (issue #29).
* Added translations section to readme.txt and fr_CA translation (issue #1).
* Added lockout registry so active lockouts are enumerable without transient key scanning.

= 0.6123 =
* SECURITY: Fixed inverted rate-limiter (advisory GHSA-p369-rjwx-f44g) — a correct password could previously bypass an active lockout. Enforcement now hooks `wp_authenticate` priority 5 and halts BEFORE the password check runs. **All users on 0.6112 should update immediately.**
* Added per-IP global lockout (defends against username-rotation / credential stuffing).
* Hardened IP resolution — `X-Forwarded-For` and `CF-Connecting-IP` are no longer trusted unconditionally; opt in via `thisismyurl_login_support_trust_proxy_headers`.
* Recovery-bypass moved from IP-bound to session-cookie-bound (fixes false lockouts on rotating mobile networks).
* Application Password failures no longer count against the browser-login rate-limit budget.
* Detected popular 2FA plugins (Two Factor / WP 2FA / miniOrange) and yields by default — override via `thisismyurl_login_support_skip_for_2fa`.
* Constant escape hatch: `define( 'TIMU_LOGIN_SUPPORT_DISABLE', true );` in wp-config.php fully bypasses the plugin.
* WP-CLI commands: `wp login-support unlock|reset-slug|generate-recovery|logs`.
* Recovery token storage moved out of the autoloaded options array into a dedicated transient.
* `Referrer-Policy: no-referrer` emitted on the recovery URL hit so the token cannot leak via Referer.
* PHP 8.1+ hygiene: removed deprecated `FILTER_SANITIZE_FULL_SPECIAL_CHARS`.
* Added uninstall.php to clean every option, transient, and cron event on plugin removal.
* Logging-toggle change is now itself logged out-of-band so the disable event is preserved.

= 0.6112 =
* Added one-time recovery token mode with lifetime control.
* Added configurable login rate limiting and lockout protection.
* Added security event logging with retention controls and clear action.
* Added Site Health security posture test for plugin configuration.
* Expanded settings UI so administrators control all security features.
* Updated documentation to reflect new capabilities and controls.

== REST API ==

`GET /wp-json/timu-login-support/v1/lockouts`

Returns a JSON array of active lockouts (account and IP). Requires `manage_options` capability; returns 403 to unauthorized callers.

Example response:

```json
[
  { "type": "ip", "identifier": "1.2.3.4", "locked_until": 1234567890, "ttl_seconds": 1800 },
  { "type": "account", "identifier": "someuser", "locked_until": 1234567890, "ttl_seconds": 900 }
]
```

== Translations ==

* French (Canada) — Christopher Ross

Want to contribute a translation? Visit [translate.wordpress.org](https://translate.wordpress.org/) once the plugin is listed there, or open a pull request on GitHub with a `.po` file.

== Upgrade Notice ==

= 1.6174.1642 =
Adds new-IP email verification with OTP rate limiting. Removes GitHub updater headers for WordPress.org hosting. Raises minimum WordPress requirement to 6.4.

= 0.6126 =
Adds honeypot username trap, fail2ban file log integration, REST lockout endpoint, 24-hour sparkline, Two Factor compatibility panel, fr_CA translation, and lockout registry for ops dashboards.

= 0.6123 =
Critical security fix for advisory GHSA-p369-rjwx-f44g (inverted rate limiter). Update immediately. Adds per-IP lockout, 2FA compatibility, WP-CLI escape hatch, and Application Password carve-out.

= 0.6112 =
Includes recovery access, login rate limiting, event logging, and Site Health integration.
