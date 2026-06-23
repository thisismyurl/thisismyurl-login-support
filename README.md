# Login Support

[![CI](https://github.com/thisismyurl/thisismyurl-login-support/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-login-support/actions/workflows/ci.yml) [![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0-blue)](LICENSE)

Hardens the WordPress login flow with rate limiting, IP lockouts, and an audit log you can read from the admin screen.

## What it does

- Caps the number of login attempts per IP before a lockout kicks in. You set the ceiling.
- Gives you an admin screen to see which IPs are locked and release them by hand.
- Keeps a timestamped log of failed and successful logins.
- Clears expired lockout records on a WP-Cron schedule, so the table doesn't grow forever.

## What it does not do

It runs entirely on your own site. There's no external service to sign up for, no cloud account, and no telemetry phoning home. Nothing about your login traffic leaves the server.

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Installation

1. Upload the plugin to `/wp-content/plugins/thisismyurl-login-support/`.
2. Activate it through the WordPress Plugins screen.
3. Go to **Settings > Login Support** to set your thresholds.

## Versioning

Versions follow `X.Yjjj.hhmm` — year, Julian day, 24-hour time of the build.

## About

Login Support is built and maintained by [Christopher Ross](https://thisismyurl.com/). I build focused WordPress tools for problems that keep showing up across real sites. No tracking, no ads, no upsells.

**WordPress.org:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/) · **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl) · **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
