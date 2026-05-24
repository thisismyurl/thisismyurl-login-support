# This Is My URL - Login Support

[![CI](https://github.com/thisismyurl/thisismyurl-login-support/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-login-support/actions/workflows/ci.yml) [![WordPress Tested](https://img.shields.io/badge/WordPress-6.8%2B-blue)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0-blue)](LICENSE)


Shifts the WordPress login URL to a path you control and adds a few admin-side utilities for hardening the login flow.

## Current Version

`0.6126` (format: `x.Yddd`)

- `x` = release class (`0` = pre-release, `1` = full release).
- `Y` = last digit of the current year.
- `ddd` = Julian day number.
- Example for May 3, 2026: year digit `6` + day `123` => `0.6123`.

This README is for developers working on the plugin: repo layout, contribution flow, security advisories. End-user docs (features, settings, FAQ, changelog) live in [`readme.txt`](readme.txt) so the WordPress.org listing stays in sync.

## Security Advisories

| Advisory | Severity | Affected | Fixed in |
|---|---|---|---|
| [`GHSA-p369-rjwx-f44g`](https://github.com/thisismyurl/thisismyurl-login-support/security/advisories) | High | ≤ 0.6112 | 0.6123 |

If you find a vulnerability, follow the disclosure process in [SECURITY.md](SECURITY.md). Don't open a public issue.

## Repository Layout

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

## License

GPL-2.0-or-later

---

## Support and Contribute

### Ways to Support

I write these plugins because I keep running into the same WordPress problems on real client sites, and a small focused plugin is usually the right shape of fix. No tracking, no ads, no paywall.

If one of these saves you a headache, here's what actually helps:

- **Sponsor if you can:** [GitHub Sponsors](https://github.com/sponsors/thisismyurl). Optional, never expected.
- **Send a PR or a bug report:** an issue with reproduction steps is worth more to me than a five-star review. Edge-case testing is gold.
- **Follow the work:** [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), [LinkedIn](https://linkedin.com/in/thisismyurl). Helps the next person find it.

### Report Issues and Questions

- **File an issue:** the [Issues](../../issues) tab. Include WordPress version, PHP version, and steps to reproduce.
- **Start a discussion:** the [Discussions](../../discussions) tab for questions, ideas, or anything that isn't a clear bug.

### Contributing Code

PRs are welcome. The flow:

1. Fork and clone locally.
2. Branch with a descriptive name (e.g., `feature/improve-safety-check`).
3. Make the change. Test it on the edge cases that bit you.
4. Run `composer run lint:phpcs` before opening the PR.
5. Open the PR with a clear note on what changed and why.

I read every PR. Well-tested ones merge faster.

---


## About This Is My URL

This plugin comes out of the work I do at [This Is My URL](https://thisismyurl.com/wordpress-security/), helping WordPress teams keep their sites secure, fast, and maintainable.

I'm Christopher Ross. I've been on the open web since 1996 and on WordPress since 2007. This Is My URL is my WordPress development and technical SEO practice, based in Fort Erie, Ontario.

### My Background

- **30 years on the open web** (since 1996), 19 of those on WordPress (since 2007)
- **WordPress contributor since 2007** with plugins on .org and code shipped to media, education, and government deployments
- **Technical SEO** focused on performance, security, and search visibility on real sites
- **Training specialist** at M.L. Campbell, building learning systems that ship

I prefer plain solutions that work. No hype, no extra moving parts.

### Ways to Connect

- **WordPress.org profile:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **Website:** [thisismyurl.com](https://thisismyurl.com/)
- **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)


## Contributors

- **Christopher Ross** ([@thisismyurl](https://github.com/thisismyurl)) — author and maintainer
- **Contributors:** everyone who's filed an issue, tested an edge case, or sent a PR. Thank you.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

## Documentation

- [readme.txt](readme.txt)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [SECURITY.md](SECURITY.md)
- [SUPPORT.md](SUPPORT.md)
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)


---
*This project follows the [10 Core Pillars](PILLARS.md). Support quality work [here](https://github.com/sponsors/thisismyurl).*

