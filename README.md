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

## Support and Contribute

### Ways to Support

I'm building these tools because WordPress developers and site owners deserve straightforward, practical solutions. There's no tracking, no ads, and you don't need to pay to use these plugins.

If you find them helpful, here are some genuine ways to support the work:

- **Sponsor if it fits your budget:** You can sponsor the project through [GitHub Sponsors](https://github.com/sponsors/thisismyurl). Sponsorship helps, but it's always optional.
- **Contribute code or ideas:** Opening a pull request, reporting an issue, or testing edge cases is just as valuable as sponsorship. Helping me improve these plugins is a great way to contribute.
- **Share your experience:** A review on [my Google My Business profile]([Add your Google Business Profile URL here]) or a follow on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps others find this work.

### Report Issues and Questions

Found a bug? Want to suggest a feature? Just curious how something works?

- **File an issue:** Use the [Issues](../../issues) tab. Include your WordPress and PHP version, and steps to reproduce.
- **Start a discussion:** Use the [Discussions](../../discussions) tab for questions, ideas, or general conversation about the plugin.

### Contributing Code

Code contributions are welcome and genuinely valuable. Here's the workflow:

1. **Fork this repository** and clone it locally.
2. **Create a feature branch** with a clear name (e.g., `feature/improve-safety-check`).
3. **Make your changes** and test thoroughly on edge cases.
4. **Follow WordPress coding standards** — run `composer run lint:phpcs` before opening a PR.
5. **Open a pull request** with a clear description of what changed and why.

I review PRs thoughtfully and appreciate well-tested contributions. Contributing is never required, but it's genuinely helpful.

---


## About This Is My URL

This plugin supports the work I do at [This Is My URL](https://thisismyurl.com/wordpress-security/), where I help WordPress teams build secure, performant, and maintainable sites.

This plugin is built and maintained by [This Is My URL](https://thisismyurl.com/), a WordPress development and technical SEO practice. I'm Christopher Ross, a WordPress developer and technical SEO specialist with 25+ years of experience in software development, training, and digital learning.

### My Background

- **25+ years** in software development, technical training, and digital systems design
- **WordPress contributor since 2007** with a strong track record helping organizations build practical, maintainable web systems
- **Technical SEO practitioner** helping sites improve performance, security, and search visibility
- **Training specialist** focused on practical outcomes and helping teams adopt technology with confidence

I believe in straightforward solutions that work. No hype. No unnecessary complexity.

### Ways to Connect

- **WordPress.org profile:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **Website:** [thisismyurl.com](https://thisismyurl.com/)
- **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)


## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
