# Security Policy

## Reporting a Security Vulnerability

If you discover a security vulnerability in this plugin, please email security@thisismyurl.com instead of using the issue tracker.

Please include:
- A description of the vulnerability
- Steps to reproduce or proof of concept
- Affected versions
- Any known workarounds

I take security seriously and will respond promptly to responsible disclosures.

## Security Practices

This plugin follows WordPress security best practices:

- **Input validation:** All user input is validated and sanitized.
- **Escaping:** Output is properly escaped for context (HTML, JavaScript, URL, CSS).
- **Capability checks:** All admin actions check user capabilities.
- **Nonce verification:** Forms include WordPress nonces.
- **Database queries:** Prepared statements with placeholders to prevent SQL injection.
- **No external phone-homes:** This plugin does not send data to external services without explicit user consent.
- **Regular updates:** Security patches are released promptly.

## Supported Versions

Security updates are provided for the current version and one previous major version.

| Version | Support | Status |
|---------|---------|--------|
| Latest | ✅ | Security updates |
| Previous | ✅ | Critical security updates only |
| Older | ❌ | No longer supported |

## Threat Model

This is a small plugin with a focused scope. Being explicit about what it
defends against — and what it does not — is more useful than implying
broader protection.

### What this plugin DOES defend against

- **Automated probing of `/wp-login.php`**. With Stealth Mode active, the
  default login URL returns the home page. Bots that target
  `/wp-login.php` directly never reach the login form.
- **Credential brute-forcing of a single account**. The per-(username, IP)
  rate limiter caps attempts and triggers a lockout for the configured
  duration.
- **Credential stuffing / username rotation from a single source**. The
  per-IP global lockout (added in 0.6123) caps the total number of
  failed attempts from a single IP regardless of which usernames are
  being tried. Threshold is `rate_limit_attempts × multiplier` (default
  4× per-account).
- **Lockout bypass via correct password** (the bug fixed in 0.6123 /
  advisory `GHSA-p369-rjwx-f44g`). Enforcement now runs on
  `wp_authenticate` priority 5, BEFORE the password check, so a correct
  password cannot bypass an active lockout.
- **Accidental token exposure via Referer**. The recovery-token endpoint
  emits `Referrer-Policy: no-referrer`, so the token does not leak to
  third-party assets loaded by the login screen.
- **Recovery bypass tied to the wrong IP**. The bypass is session-cookie-
  bound (HttpOnly, Secure on HTTPS, SameSite=Lax) rather than IP-bound,
  so a user whose IP rotates between clicking the recovery link and
  reaching `/wp-login.php` is not falsely re-locked out.
- **Application Password budget burn**. Failed Application Password
  authentications do not count against the browser-login rate-limit
  budget, so legitimate API traffic with a rotated app password cannot
  lock real users out.
- **Persistent state on uninstall**. `uninstall.php` cleans every option,
  transient, and cron event the plugin owns.

### What this plugin DOES NOT defend against

- **Authenticated attacks**. Once a user logs in, this plugin has nothing
  more to say. Use a 2FA plugin (Two Factor, WP 2FA, miniOrange) on top.
- **Distributed brute-force across many source IPs**. The per-IP lockout
  helps, but a botnet of 10,000 IPs each trying once will not trip it.
  Pair this plugin with edge controls (Cloudflare WAF, Wordfence, your
  reverse proxy) for that threat.
- **XML-RPC / wp-json brute-forcing**. This plugin only inspects the
  browser login path. Disable XML-RPC at the server level and use the
  Application Password carve-out + a separate REST-auth control if you
  expose the JSON API publicly.
- **Vulnerable WordPress core or plugin/theme code**. Keep core, plugins,
  and themes patched. This plugin is one layer; not the only one.
- **DNS hijacking, SSL stripping, MITM**. Use HTTPS site-wide. The
  recovery-bypass cookie is `Secure` only when the request is served
  over HTTPS.
- **Insider threats with database read access**. The recovery-bypass
  lookup is keyed by SHA-256 of the cookie value, so a DB dump alone
  cannot resurrect an active bypass — but an attacker with full server
  access can still impersonate any user.

### Known limits

- **IP source trust**. By default, only `REMOTE_ADDR` is trusted. If your
  site sits behind a reverse proxy (Cloudflare, AWS ELB, nginx
  load-balancer), opt in to forwarded headers via
  `add_filter( 'thisismyurl_login_support_trust_proxy_headers', '__return_true' );`.
  For Cloudflare specifically, `CF-Connecting-IP` is only honoured when
  `REMOTE_ADDR` is in the published Cloudflare ranges. Override that
  list with `thisismyurl_login_support_cloudflare_ip_ranges`.
- **Recovery token cryptography**. Recovery tokens are generated with
  `wp_generate_password( 24, false, false )` (≥120 bits of entropy
  against the lower-case alphanumeric alphabet) and stored as
  `wp_hash( $token )` rather than plaintext. `wp_hash()` uses HMAC-MD5
  with a server-side secret (`SECURE_AUTH_KEY`/`SECURE_AUTH_SALT`).
  This is fit for a 5–180 minute single-use credential whose primary
  defence is its high entropy and short lifetime, not for long-lived
  password storage. Comparison uses `hash_equals()` to avoid timing
  oracles.
- **Lockout state lives in transients**. On hosts with a persistent
  object cache (Memcached/Redis), lockouts survive across requests
  cheaply. On hosts without one, transients fall back to the options
  table — still durable, but heavier on writes for high-traffic sites.

## Log Storage Architecture

Login Support's security event log is stored in a single, non-autoloaded
`wp_options` row (`thisismyurl-login-support_logs`).

**Default behaviour**

| Property | Value |
|---|---|
| Row type | `wp_options`, non-autoloaded (`false`) |
| Max entries | 500 (ring-buffer — oldest entries are dropped when the cap is reached) |
| Max size | ~100 KB at 500 entries (worst-case, long usernames + IPs) |
| Retention pruning | On every write; entries older than `log_retention_days` are removed |

**Trade-off**

This approach works out of the box on any WordPress installation with no
schema migration. The downside is that under an active brute-force storm
every failed login rewrites the entire `wp_options` row. With the 500-entry
cap the row stays bounded, but on a high-traffic site the write frequency
can cause `wp_options` row contention.

**Mitigations already in place**

- 500-entry ring-buffer cap limits the maximum row size.
- Non-autoloaded storage: the row is never loaded on uncached front-end
  page views, only on admin and login-path requests where logging is active.

**Future: opt-in custom table**

Issue [#18](https://github.com/thisismyurl/thisismyurl-login-support/issues/18)
tracks adding an opt-in `{prefix}timu_login_support_log` custom table with
proper indexes for high-traffic deployments. This is not yet implemented —
the default option-based storage remains unchanged and is the only mode
available today.

If you are operating a site under sustained attack and observe database
contention, a short-term mitigation is to lower `log_retention_days` (which
accelerates pruning) or temporarily disable event logging from the settings
page.

## Changelog and Updates

Check [CHANGELOG.md](CHANGELOG.md) and [GitHub Releases](../../releases) for security-related updates and fixes.

## Questions?

If you have security-related questions (that aren't vulnerability reports), feel free to open a discussion or contact me directly through my website: [thisismyurl.com](https://thisismyurl.com/)
