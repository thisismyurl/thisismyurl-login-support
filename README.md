# This Is My URL - Login Support

[![CI](https://github.com/thisismyurl/thisismyurl-login-support/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-login-support/actions/workflows/ci.yml) [![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0-blue)](LICENSE)

Shifts the WordPress login URL to a path you control and adds a few admin-side utilities for hardening the login flow.

## Current version

`1.6147` (format: `x.Yddd`)

- `x` = release class (`0` = pre-release, `1` = full release).
- `Y` = last digit of the current year.
- `ddd` = Julian day number.
- Example for May 3, 2026: year digit `6` + day `123` => `0.6123`.

This README is for developers working on the plugin: repo layout, contribution flow, and security advisories. End-user docs (features, settings, FAQ, changelog) live in [`readme.txt`](readme.txt) so the WordPress.org listing stays in sync.

## Security advisories

| Advisory | Severity | Affected | Fixed in |
|---|---|---|---|
| [`GHSA-p369-rjwx-f44g`](https://github.com/thisismyurl/thisismyurl-login-support/security/advisories) | High | ≤ 0.6112 | 0.6123 |

If you find a vulnerability, follow the disclosure process in [SECURITY.md](SECURITY.md). Don't open a public issue.

## Repository layout

```
thisismyurl-login-support.php   # plugin bootstrap, main class
core/class-timu-core.php        # shared admin framework
core/class-timu-cli.php         # WP-CLI commands (loaded only under WP_CLI)
core/assets/shared-admin.{js,css}
assets/                         # plugin icon + banner for .org
uninstall.php                   # removes options/transients/cron on uninstall
readme.txt                      # WP.org plugin directory readme (end-user docs)
SECURITY.md                     # disclosure process + threat model
CHANGELOG.md                    # version history
```

## Local development

```bash
composer install
composer run lint:phpcs        # WordPress Coding Standards
composer run lint:phpstan      # static analysis
php -l thisismyurl-login-support.php   # quick syntax check
```

CI runs the PHP lint matrix and PHPCS on every PR. If those go red, the PR doesn't merge.

## Documentation

- [readme.txt](readme.txt)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [SECURITY.md](SECURITY.md)
- [SUPPORT.md](SUPPORT.md)
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)

---

## Support and donations

I build these tools because WordPress sites in the wild keep hitting the same problems, and a small, focused plugin is usually the right fix. They're free to use, with no tracking and no ads.

If one of them saves you time, here are the genuine ways to help:

- **Sponsor the work.** [GitHub Sponsors](https://github.com/sponsors/thisismyurl) is the simplest way, and the Sponsor button at the top of this repo lists it alongside Bitcoin, Dogecoin, PayPal, and Interac e-transfer. Any amount helps, and none of it is expected.
- **Contribute code or ideas.** A pull request, a bug report, or a tested edge case is worth as much as a donation. See [CONTRIBUTING.md](CONTRIBUTING.md) to get started.
- **Share it.** A note on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps other people find work that might save them the same afternoon.

### Report issues and questions

- **Found a bug or want a feature?** Open an issue on the [Issues](../../issues) tab. Include your WordPress and PHP versions and the steps to reproduce it.
- **Have a question?** Start a thread on the [Discussions](../../discussions) tab.

### Contributing code

Code contributions are welcome. The short version:

1. Fork the repository and clone your fork.
2. Create a branch with a clear name, like `feature/short-descriptive-name`.
3. Make your change and test it against the edge cases.
4. Run the coding-standards check before you open the pull request.
5. Open a pull request that explains what changed and why.

The full workflow and standards live in [CONTRIBUTING.md](CONTRIBUTING.md). Contributing is never required, but it is always appreciated.

## About This Is My URL

This plugin is built and maintained by [This Is My URL](https://thisismyurl.com/), the WordPress development and technical SEO practice of Christopher Ross. I help teams build WordPress sites that stay secure, fast, and maintainable, and I write small, focused plugins like this one for the problems those sites keep running into.

### My background

- On the web since 1996, and in WordPress since 2007
- WordPress.org plugin developer with 19 plugins published since 2009
- Technical SEO practitioner focused on performance, security, and search visibility
- Lead instructor and curriculum architect at the M.L. Campbell Training Center, the Sherwin-Williams® international training facility for its industrial wood division

### Ways to connect

- **Website:** [thisismyurl.com](https://thisismyurl.com/)
- **WordPress.org:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)

## Contributors

- **Christopher Ross** ([@thisismyurl](https://github.com/thisismyurl)) — author and maintainer
- Thanks to everyone who has reported issues, tested edge cases, and contributed code

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

---
*This project follows the [10 Core Pillars](PILLARS.md). Support quality work [here](https://github.com/sponsors/thisismyurl).*
