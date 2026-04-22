=== Login Support by thisismyurl.com ===
Contributors: thisismyurl
Donate link: https://thisismyurl.com/
Tags: login, security, wp-login, rate limit, site health
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.6112
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Harden your site login workflow with a custom login slug and admin security controls.

== Description ==

Login Support helps administrators reduce automated login probing by using a custom login path and layered login controls.

Features:

* Enable or disable Stealth Mode for login URL shifting.
* Set and validate a custom secret login slug.
* Generate one-time recovery links for safe access restoration.
* Enable login rate limiting with configurable thresholds.
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

Enable Recovery Mode, then generate a one-time recovery link from Settings > Login Support. The token is temporary and single-use.

You can still temporarily disable the plugin by renaming its folder via SFTP/SSH or with WP-CLI:

`wp plugin deactivate thisismyurl-login-support`

= Does the plugin include brute-force login controls? =

Yes. You can enable login rate limiting and tune:

* maximum failed attempts
* attempt window in minutes
* lockout duration in minutes

= Can I review security events? =

Yes. The settings page includes a Security Event Log table. You can configure retention days or clear logs at any time.

= Does this plugin replace SSL, firewalls, or 2FA? =

No. Use it alongside HTTPS, strong passwords, MFA/2FA, and a security policy.

== Changelog ==

= 1.6112 =
* Added one-time recovery token mode with lifetime control.
* Added configurable login rate limiting and lockout protection.
* Added security event logging with retention controls and clear action.
* Added Site Health security posture test for plugin configuration.
* Expanded settings UI so administrators control all security features.
* Updated documentation to reflect new capabilities and controls.

== Upgrade Notice ==

= 1.6112 =
Includes recovery access, login rate limiting, event logging, and Site Health integration.
