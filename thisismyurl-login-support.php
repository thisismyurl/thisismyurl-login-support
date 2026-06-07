<?php
/**
 * Plugin Name: Login Support by Christopher Ross
 * Plugin URI:  https://thisismyurl.com/
 * Description: Harden login access by allowing a custom login slug and admin security controls.
 * Version:     1.6149.0734
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      Christopher Ross
 * Author URI:  https://thisismyurl.com/
 * Text Domain: thisismyurl-login-support
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/thisismyurl/thisismyurl-login-support
 * Primary Branch:    main
 * Update URI:        https://github.com/thisismyurl/thisismyurl-login-support
 */

defined( 'ABSPATH' ) || exit;

define( 'TIMU_LOGIN_SUPPORT_VERSION', '1.6158.1440' );

require_once plugin_dir_path( __FILE__ ) . 'core/class-timu-core.php';

class TIMU_Login_Support extends TIMU_Core_v1 {

    const LOG_OPTION       = 'thisismyurl-login-support_logs';
    const LOCKOUT_REGISTRY = 'thisismyurl-login-support_lockouts';

    public function __construct() {
        parent::__construct( 'thisismyurl-login-support', plugin_dir_url( __FILE__ ), 'timu_login_group' );

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'admin_init', array( $this, 'handle_force_logout' ) );
        add_action( 'admin_init', array( $this, 'handle_clear_logs' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        /*
         * `setup_theme` priority 1 is intentional and load-bearing. We need
         * to intercept the request BEFORE the theme is loaded (`after_setup_theme`),
         * BEFORE the parsed-URL is resolved (`parse_request`), and certainly
         * before any other plugin's `init` work runs against `/wp-login.php`.
         * If we hooked any later, the theme's bootstrap could already have
         * fired functions.php side-effects against the request.
         *
         * Trade-off: we cannot use `is_admin()` reliably here yet — fine,
         * because handle_login_shifting() defends against admin/AJAX/cron/REST
         * with its own early-returns.
         */
        add_action( 'setup_theme', array( $this, 'handle_login_shifting' ), 1 );
        add_filter( 'site_url', array( $this, 'rewrite_login_urls' ), 10, 4 );

        /*
         * Rate limiter is hooked at `wp_authenticate` priority 5 — BEFORE WP runs
         * the password check (`wp_authenticate_username_password` runs at default
         * priority 20 on the `authenticate` filter). This is intentional and
         * load-bearing: the prior implementation (advisory GHSA-p369-rjwx-f44g)
         * hooked the lockout check on `authenticate` priority 30 with an
         * `is_wp_error` early-return, which meant a correct password would bypass
         * an active lockout entirely. By gating on `wp_authenticate` we ensure the
         * lockout fires regardless of credential correctness, and we read the
         * username from $_POST['log'] at that early stage (documented in
         * enforce_rate_limit()).
         */
        add_action( 'wp_authenticate', array( $this, 'enforce_rate_limit' ), 5, 2 );
        add_action( 'wp_login_failed', array( $this, 'track_failed_login' ) );
        add_action( 'wp_login', array( $this, 'track_successful_login' ), 10, 2 );

        /*
         * Application Password failures fire `wp_login_failed` too, which would
         * burn through the lockout budget on legitimate API traffic that simply
         * uses an expired/rotated app password. Carve them out: when the Application
         * Password authentication path errors, we set a flag that track_failed_login
         * checks before counting the attempt against the user/IP rate-limit budget.
         */
        add_action( 'wp_authenticate_application_password_errors', array( $this, 'mark_application_password_failure' ), 10, 4 );
        add_action( 'update_option_' . $this->plugin_slug . '_options', array( $this, 'handle_option_updates' ), 10, 2 );
        add_filter( 'site_status_tests', array( $this, 'register_site_health_tests' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ) );
    }

    /**
     * Append a Sponsor link to the plugin row actions.
     *
     * @param string[] $links Existing action links.
     * @return string[]
     */
    public function add_action_links( $links ) {
        $links[] = '<a href="' . esc_url( 'https://github.com/sponsors/thisismyurl' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sponsor', 'thisismyurl-login-support' ) . '</a>';
        return $links;
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'thisismyurl-login-support', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    private function get_options() {
        $options = get_option( $this->plugin_slug . '_options', array() );

        return is_array( $options ) ? $options : array();
    }

    public function sanitize_core_options( $input ) {
        $input    = parent::sanitize_core_options( $input );
        $defaults = $this->get_default_options();

        $input['enable_recovery_mode'] = empty( $input['enable_recovery_mode'] ) ? 0 : 1;
        $input['enable_rate_limit']    = empty( $input['enable_rate_limit'] ) ? 0 : 1;
        $input['enable_event_logging'] = empty( $input['enable_event_logging'] ) ? 0 : 1;
        $input['rate_limit_attempts']  = isset( $input['rate_limit_attempts'] ) ? max( 1, min( 20, absint( $input['rate_limit_attempts'] ) ) ) : $defaults['rate_limit_attempts'];
        $input['rate_limit_window']    = isset( $input['rate_limit_window'] ) ? max( 1, min( 120, absint( $input['rate_limit_window'] ) ) ) : $defaults['rate_limit_window'];
        $input['lockout_minutes']      = isset( $input['lockout_minutes'] ) ? max( 1, min( 1440, absint( $input['lockout_minutes'] ) ) ) : $defaults['lockout_minutes'];
        $input['recovery_token_ttl']   = isset( $input['recovery_token_ttl'] ) ? max( 5, min( 180, absint( $input['recovery_token_ttl'] ) ) ) : $defaults['recovery_token_ttl'];
        $input['log_retention_days']   = isset( $input['log_retention_days'] ) ? max( 1, min( 365, absint( $input['log_retention_days'] ) ) ) : $defaults['log_retention_days'];

        // Honeypot username list (issue #37). Accept comma- or whitespace-separated
        // entries, normalise each to a sanitize_title-safe slug, drop empties and
        // duplicates, re-join with commas for storage.
        if ( isset( $input['honeypot_usernames'] ) ) {
            $raw   = sanitize_textarea_field( wp_unslash( $input['honeypot_usernames'] ) );
            $parts = preg_split( '/[\s,]+/', $raw );
            $clean = array();

            if ( is_array( $parts ) ) {
                foreach ( $parts as $part ) {
                    $slug = sanitize_title( $part );

                    if ( '' !== $slug && ! in_array( $slug, $clean, true ) ) {
                        $clean[] = $slug;
                    }
                }
            }

            $input['honeypot_usernames'] = implode( ',', $clean );
        } else {
            $input['honeypot_usernames'] = $defaults['honeypot_usernames'];
        }

        $input['honeypot_ban_minutes'] = isset( $input['honeypot_ban_minutes'] ) ? max( 1, min( 1440, absint( $input['honeypot_ban_minutes'] ) ) ) : $defaults['honeypot_ban_minutes'];

        // fail2ban-compatible file log (issue #34).
        $input['enable_file_log'] = empty( $input['enable_file_log'] ) ? 0 : 1;

        if ( isset( $input['file_log_path'] ) ) {
            $path = trim( sanitize_text_field( wp_unslash( $input['file_log_path'] ) ) );

            if ( '' !== $path ) {
                $abspath_real = realpath( ABSPATH );
                $is_absolute  = ( '/' === substr( $path, 0, 1 ) ) || preg_match( '/^[A-Za-z]:[\\\\\/]/', $path );
                $within_web   = $abspath_real && 0 === strpos( $path, $abspath_real );

                if ( ! $is_absolute || $within_web || false !== strpos( $path, '..' ) ) {
                    add_settings_error(
                        $this->plugin_slug,
                        'invalid_file_log_path',
                        esc_html__( 'The fail2ban log path must be absolute and outside the WordPress web root.', 'thisismyurl-login-support' ),
                        'error'
                    );
                    $path = '';
                }
            }

            $input['file_log_path'] = $path;
        } else {
            $input['file_log_path'] = '';
        }

        if ( isset( $input['slug'] ) ) {
            $blocked_slugs = array( 'wp-admin', 'wp-login.php', 'xmlrpc.php' );

            if ( in_array( $input['slug'], $blocked_slugs, true ) ) {
                add_settings_error(
                    $this->plugin_slug,
                    'invalid_slug',
                    esc_html__( 'The chosen secret slug is reserved and cannot be used.', 'thisismyurl-login-support' ),
                    'error'
                );
                $input['slug'] = '';
            }
        }

        return $input;
    }

    private function get_default_options() {
        return array(
            'enable_shifting'      => 0,
            'slug'                 => '',
            'enable_recovery_mode' => 1,
            'recovery_token_ttl'   => 20,
            'enable_rate_limit'    => 1,
            'rate_limit_attempts'  => 5,
            'rate_limit_window'    => 15,
            'lockout_minutes'      => 30,
            'enable_event_logging' => 1,
            'log_retention_days'   => 30,
            'honeypot_usernames'   => 'admin,root,administrator',
            'honeypot_ban_minutes' => 60,
            'enable_file_log'      => 0,
            'file_log_path'        => '',
        );
    }

    private function get_controlled_options() {
        return wp_parse_args( $this->get_options(), $this->get_default_options() );
    }

    private function get_login_slug() {
        $options = $this->get_controlled_options();
        $slug    = isset( $options['slug'] ) ? sanitize_title( wp_unslash( $options['slug'] ) ) : '';

        return (string) $slug;
    }

    private function is_shifting_enabled() {
        $options = $this->get_controlled_options();

        return ! empty( $options['enable_shifting'] ) && ! empty( $this->get_login_slug() );
    }

    /**
     * Resolve the client IP.
     *
     * REMOTE_ADDR is the only header trusted by default. Forwarded-for variants
     * (`X-Forwarded-For`, `CF-Connecting-IP`) are spoofable by any client unless
     * the site sits behind a reverse proxy that strips and rewrites them. To
     * enable trust in those headers, the site owner opts in via filter:
     *
     *   add_filter( 'thisismyurl_login_support_trust_proxy_headers', '__return_true' );
     *
     * For Cloudflare specifically, the `CF-Connecting-IP` header is only honoured
     * when the request's REMOTE_ADDR is in Cloudflare's published IP ranges. A
     * site owner can supply that allowlist via:
     *
     *   add_filter( 'thisismyurl_login_support_cloudflare_ip_ranges', $cidrs );
     *
     * The default behaviour (REMOTE_ADDR only) is the only safe stance for a
     * plugin shipped to .org without knowing the deploy topology. Closes
     * audit-finding #4 (HTTP_X_FORWARDED_FOR / HTTP_CF_CONNECTING_IP trusted
     * unconditionally — P1).
     *
     * @return string A validated IP, or '0.0.0.0' if none can be derived.
     */
    private function get_client_ip() {
        $remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : '';

        $trust_proxy_headers = (bool) apply_filters( 'thisismyurl_login_support_trust_proxy_headers', false );

        if ( $trust_proxy_headers ) {
            // CF-Connecting-IP only when REMOTE_ADDR is in the Cloudflare ranges.
            if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && $this->ip_is_cloudflare( $remote_addr ) ) {
                $cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );

                if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
                    return $cf;
                }
            }

            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                $xff   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
                $parts = array_map( 'trim', explode( ',', $xff ) );
                $first = isset( $parts[0] ) ? $parts[0] : '';

                if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
                    return $first;
                }
            }
        }

        if ( $remote_addr && filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
            return $remote_addr;
        }

        return '0.0.0.0';
    }

    /**
     * Test whether $ip is inside any of the Cloudflare IP ranges.
     *
     * Default ranges are the Cloudflare-published lists (current as of the
     * release date). Site owners override via the
     * `thisismyurl_login_support_cloudflare_ip_ranges` filter; that filter is
     * the canonical contract and should return an array of CIDR strings.
     *
     * @param string $ip A validated IP, possibly empty.
     * @return bool
     */
    private function ip_is_cloudflare( $ip ) {
        if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return false;
        }

        $defaults = array(
            // IPv4.
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            // IPv6.
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        );

        $ranges = (array) apply_filters( 'thisismyurl_login_support_cloudflare_ip_ranges', $defaults );

        foreach ( $ranges as $cidr ) {
            if ( $this->ip_in_cidr( $ip, $cidr ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * CIDR membership test. Supports IPv4 and IPv6.
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private function ip_in_cidr( $ip, $cidr ) {
        if ( false === strpos( $cidr, '/' ) ) {
            return $ip === $cidr;
        }

        list( $subnet, $bits ) = explode( '/', $cidr, 2 );
        $bits = (int) $bits;

        $ip_packed     = @inet_pton( $ip );
        $subnet_packed = @inet_pton( $subnet );

        if ( false === $ip_packed || false === $subnet_packed || strlen( $ip_packed ) !== strlen( $subnet_packed ) ) {
            return false;
        }

        $bytes_full     = (int) floor( $bits / 8 );
        $bits_remaining = $bits % 8;

        if ( $bytes_full > 0 && 0 !== substr_compare( $ip_packed, $subnet_packed, 0, $bytes_full ) ) {
            return false;
        }

        if ( 0 === $bits_remaining ) {
            return true;
        }

        $mask = chr( 0xFF << ( 8 - $bits_remaining ) & 0xFF );

        return ( $ip_packed[ $bytes_full ] & $mask ) === ( $subnet_packed[ $bytes_full ] & $mask );
    }

    /**
     * Per-(username, IP) attempts key. Survives the rate-limit window.
     */
    private function get_rate_limit_cache_key( $username, $ip ) {
        return $this->plugin_slug . '_rl_' . md5( strtolower( (string) $username ) . '|' . $ip );
    }

    /**
     * Per-(username, IP) lockout key.
     */
    private function get_lockout_cache_key( $username, $ip ) {
        return $this->plugin_slug . '_lock_' . md5( strtolower( (string) $username ) . '|' . $ip );
    }

    /**
     * Per-IP attempts key (independent of username). Defends against an attacker
     * rotating usernames against a single IP — closes audit-finding #3 (P1).
     */
    private function get_ip_rate_limit_cache_key( $ip ) {
        return $this->plugin_slug . '_rl_ip_' . md5( (string) $ip );
    }

    /**
     * Per-IP lockout key.
     */
    private function get_ip_lockout_cache_key( $ip ) {
        return $this->plugin_slug . '_lock_ip_' . md5( (string) $ip );
    }

    /**
     * Per-IP attempt budget. The username-rotation defence is more aggressive
     * than the per-(username,IP) check — we let the global per-IP budget be a
     * filter-tunable multiple of the per-account threshold, defaulting to 4x.
     * That keeps the username-based limit meaningful (an honest user fat-
     * fingering their own password) while shutting down credential-stuffing
     * (one IP, many usernames).
     */
    private function get_ip_attempt_threshold( $per_account_attempts ) {
        $multiplier = (int) apply_filters( 'thisismyurl_login_support_ip_attempt_multiplier', 4 );
        $multiplier = max( 1, $multiplier );

        return max( $per_account_attempts, $per_account_attempts * $multiplier );
    }

    /**
     * Record an active lockout in the enumerable registry so the REST endpoint
     * can list current lockouts without scanning all transient keys. Expired
     * entries are pruned on each write; the registry is capped at 200 rows.
     *
     * @param string $type  'account' or 'ip'.
     * @param string $value The locked value (username or IP).
     * @param int    $until Unix timestamp when the lockout expires.
     */
    private function record_lockout( $type, $value, $until ) {
        $registry = get_option( self::LOCKOUT_REGISTRY, array() );
        $registry = is_array( $registry ) ? $registry : array();
        $now      = time();

        // Drop expired entries and any existing entry for this exact key.
        $pruned = array();
        foreach ( $registry as $entry ) {
            if ( ! isset( $entry['until'] ) || $entry['until'] <= $now ) {
                continue;
            }
            if ( $entry['type'] === $type && $entry['value'] === $value ) {
                continue; // replaced below.
            }
            $pruned[] = $entry;
        }

        $pruned[] = array(
            'type'  => $type,
            'value' => $value,
            'until' => $until,
        );

        if ( count( $pruned ) > 200 ) {
            $pruned = array_slice( $pruned, -200 );
        }

        update_option( self::LOCKOUT_REGISTRY, $pruned, false );
    }

    /**
     * Report whether a recognised 2FA plugin is currently active.
     * Public so render_ui() can surface interop status without duplicating
     * the detection logic.
     *
     * @return bool
     */
    public function is_two_factor_active() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $known = array(
            'two-factor/two-factor.php',
            'wp-2fa/wp-2fa.php',
            'miniorange-2-factor-authentication/miniorange_2_factor_settings.php',
        );

        foreach ( $known as $plugin_file ) {
            if ( is_plugin_active( $plugin_file ) ) {
                return true;
            }
        }

        return false;
    }

    private function log_event( $event, $details = '', $user_login = '' ) {
        $options = $this->get_controlled_options();

        if ( empty( $options['enable_event_logging'] ) ) {
            return;
        }

        $this->prune_logs();

        $logs   = get_option( self::LOG_OPTION, array() );
        $logs   = is_array( $logs ) ? $logs : array();
        $logs[] = array(
            'time'    => time(),
            'event'   => sanitize_text_field( $event ),
            'ip'      => sanitize_text_field( $this->get_client_ip() ),
            'user'    => sanitize_text_field( $user_login ),
            'details' => sanitize_text_field( $details ),
        );

        if ( count( $logs ) > 500 ) {
            $logs = array_slice( $logs, -500 );
        }

        update_option( self::LOG_OPTION, $logs, false );

        // fail2ban-compatible file sink (issue #34). Off by default; only writes
        // when the operator has enabled it and supplied a valid path.
        if ( ! empty( $options['enable_file_log'] ) && ! empty( $options['file_log_path'] ) ) {
            $ip_for_file  = isset( $logs[ count( $logs ) - 1 ]['ip'] ) ? $logs[ count( $logs ) - 1 ]['ip'] : $this->get_client_ip();
            $line         = date( 'Y-m-d H:i:s' ) . ' ' . $ip_for_file . ' ' . $user_login . "\n"; // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
            @file_put_contents( $options['file_log_path'], $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
    }

    private function prune_logs() {
        $options   = $this->get_controlled_options();
        $retention = max( 1, absint( $options['log_retention_days'] ) );
        $threshold = time() - ( DAY_IN_SECONDS * $retention );
        $logs      = get_option( self::LOG_OPTION, array() );
        $logs      = is_array( $logs ) ? $logs : array();

        if ( empty( $logs ) ) {
            return;
        }

        $filtered = array();

        foreach ( $logs as $log ) {
            if ( ! isset( $log['time'] ) ) {
                continue;
            }

            if ( (int) $log['time'] >= $threshold ) {
                $filtered[] = $log;
            }
        }

        if ( count( $filtered ) !== count( $logs ) ) {
            update_option( self::LOG_OPTION, $filtered, false );
        }
    }

    private function generate_recovery_token() {
        $token = wp_generate_password( 24, false, false );

        return strtolower( sanitize_text_field( $token ) );
    }

    /**
     * Recovery token is persisted in a dedicated, non-autoloaded transient —
     * NOT in the main `_options` array (which is autoloaded on every request,
     * meaning every uncached page load would carry the recovery hash + expiry
     * into PHP memory). Closes audit-finding #17 (P2).
     */
    const RECOVERY_TRANSIENT = 'thisismyurl-login-support_recovery';

    private function save_recovery_token( $token ) {
        $options = $this->get_controlled_options();
        $ttl     = max( 5, absint( $options['recovery_token_ttl'] ) );

        // Migration: if the old hash sits in the options array, scrub it.
        if ( isset( $options['recovery_token_hash'] ) || isset( $options['recovery_token_expires'] ) ) {
            unset( $options['recovery_token_hash'], $options['recovery_token_expires'] );
            update_option( $this->plugin_slug . '_options', $options );
        }

        set_transient(
            self::RECOVERY_TRANSIENT,
            array(
                'hash'    => wp_hash( $token ),
                'expires' => time() + ( MINUTE_IN_SECONDS * $ttl ),
            ),
            MINUTE_IN_SECONDS * $ttl
        );
    }

    private function is_valid_recovery_token( $token ) {
        $options = $this->get_controlled_options();

        if ( empty( $options['enable_recovery_mode'] ) ) {
            return false;
        }

        $stored = get_transient( self::RECOVERY_TRANSIENT );

        if ( ! is_array( $stored ) || empty( $stored['hash'] ) || empty( $stored['expires'] ) ) {
            return false;
        }

        if ( time() > (int) $stored['expires'] ) {
            return false;
        }

        return hash_equals( (string) $stored['hash'], wp_hash( (string) $token ) );
    }

    private function consume_recovery_token() {
        delete_transient( self::RECOVERY_TRANSIENT );

        // Belt-and-braces: scrub any stale option keys from older versions.
        $options = $this->get_controlled_options();

        if ( isset( $options['recovery_token_hash'] ) || isset( $options['recovery_token_expires'] ) ) {
            unset( $options['recovery_token_hash'], $options['recovery_token_expires'] );
            update_option( $this->plugin_slug . '_options', $options );
        }
    }

    /**
     * Stable, opaque per-visitor identifier used to bind a recovery-token
     * bypass to "the browser session that hit the URL," not to "any IP that
     * matched at click time." Closes audit-finding #5 (P1) — old behaviour
     * broke for users on mobile networks whose IP rotated mid-flow.
     *
     * The cookie is HttpOnly, Secure (when HTTPS), SameSite=Lax. Lifetime
     * matches the bypass window. Cookie value is a 32-byte random hex string
     * we hash before storing the lookup key, so the cookie value alone isn't
     * the lockup.
     */
    const RECOVERY_BYPASS_COOKIE = 'timu_login_support_bypass';

    private function set_recovery_bypass_cookie() {
        $token = wp_generate_password( 32, false, false );
        $ttl   = MINUTE_IN_SECONDS * 10;

        // Lookup key = hash of the cookie value, so reading the wp_options /
        // transient store doesn't expose the bypass token to anyone with DB
        // read access.
        $lookup = $this->plugin_slug . '_recovery_bypass_' . hash( 'sha256', $token );

        set_transient( $lookup, 1, $ttl );

        if ( ! headers_sent() ) {
            setcookie(
                self::RECOVERY_BYPASS_COOKIE,
                $token,
                array(
                    'expires'  => time() + $ttl,
                    'path'     => COOKIEPATH ? COOKIEPATH : '/',
                    'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                )
            );
        }
    }

    private function recovery_bypass_active() {
        if ( empty( $_COOKIE[ self::RECOVERY_BYPASS_COOKIE ] ) ) {
            return false;
        }

        $token  = sanitize_text_field( wp_unslash( $_COOKIE[ self::RECOVERY_BYPASS_COOKIE ] ) );
        $lookup = $this->plugin_slug . '_recovery_bypass_' . hash( 'sha256', $token );

        return (bool) get_transient( $lookup );
    }

    public function handle_admin_actions() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Replaced filter_input(INPUT_GET, ..., FILTER_SANITIZE_FULL_SPECIAL_CHARS) —
        // the FULL_SPECIAL_CHARS constant is deprecated in PHP 8.1+. The
        // wp_unslash + sanitize_text_field pair is the WP-native equivalent and
        // PHP 8.4-clean. Closes audit-finding #6 (P1).
        $action = isset( $_GET['timu_action'] ) ? sanitize_text_field( wp_unslash( $_GET['timu_action'] ) ) : '';
        $page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( $this->plugin_slug !== $page || empty( $action ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $nonce, 'timu_admin_actions' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'thisismyurl-login-support' ) );
        }

        if ( 'generate_recovery' === $action ) {
            $token        = $this->generate_recovery_token();
            $generated_by = wp_get_current_user();
            $this->save_recovery_token( $token );
            set_transient( $this->plugin_slug . '_recovery_token', $token, MINUTE_IN_SECONDS * 3 );
            $this->log_event(
                'Recovery token generated',
                sprintf( 'Generated by user_id=%d login=%s', (int) $generated_by->ID, $generated_by->user_login ),
                $generated_by->user_login
            );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->plugin_slug ) );
        exit;
    }

    /**
     * Clear the security event log.
     *
     * Destructive action: accepts POST only. The settings UI submits this via a
     * real <button> in a POST form (a11y finding P1 / 4.1.2), mirroring the
     * Force Global Logout control. The previous GET-link path let any
     * link-prefetcher or CSRF chain trip the wipe.
     *
     * @return void
     */
    public function handle_clear_logs() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $clear_logs = isset( $_POST['timu_clear_logs'] ) ? absint( wp_unslash( $_POST['timu_clear_logs'] ) ) : 0;
        $page       = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        if ( 1 !== $clear_logs || $this->plugin_slug !== $page ) {
            return;
        }

        if ( ! wp_verify_nonce( $nonce, 'timu_clear_logs' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'thisismyurl-login-support' ) );
        }

        update_option( self::LOG_OPTION, array(), false );
        $this->log_event( 'Security logs cleared', 'Admin cleared all security logs' );

        wp_safe_redirect(
            remove_query_arg(
                array( 'timu_clear_logs', '_wpnonce' ),
                admin_url( 'options-general.php?page=' . $this->plugin_slug )
            )
        );
        exit;
    }

    public function handle_force_logout() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Destructive action: accept POST only. The settings UI submits this
        // via a real <button> in a POST form (a11y finding P1 / 4.1.2).
        $force_logout = isset( $_POST['timu_force_logout'] ) ? absint( wp_unslash( $_POST['timu_force_logout'] ) ) : 0;
        $page         = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $nonce        = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        if ( 1 !== $force_logout || $this->plugin_slug !== $page ) {
            return;
        }

        if ( ! wp_verify_nonce( $nonce, 'timu_force_logout' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'thisismyurl-login-support' ) );
        }

        $users = get_users( array( 'fields' => array( 'ID' ) ) );

        foreach ( $users as $user ) {
            wp_destroy_other_sessions( (int) $user->ID );
        }

        set_transient( $this->plugin_slug . '_force_logout_done', 1, MINUTE_IN_SECONDS );
        $this->log_event( 'Global logout', 'Admin terminated all other sessions' );

        wp_safe_redirect(
            remove_query_arg(
                array( 'timu_force_logout', '_wpnonce' ),
                admin_url( 'options-general.php?page=' . $this->plugin_slug )
            )
        );
        exit;
    }

    public function add_menu() {
        add_options_page(
            esc_html__( 'Login Support', 'thisismyurl-login-support' ),
            esc_html__( 'Login Support', 'thisismyurl-login-support' ),
            'manage_options',
            $this->plugin_slug,
            array( $this, 'render_ui' )
        );
    }

    public function render_ui() {
        $options             = $this->get_controlled_options();
        $force_logout_done   = get_transient( $this->plugin_slug . '_force_logout_done' );
        $recovery_token      = get_transient( $this->plugin_slug . '_recovery_token' );
        $recovery_action_url = wp_nonce_url(
            admin_url( 'options-general.php?page=' . $this->plugin_slug . '&timu_action=generate_recovery' ),
            'timu_admin_actions'
        );
        $force_logout_action = admin_url( 'options-general.php?page=' . $this->plugin_slug );
        $clear_logs_action   = $force_logout_action;
        $logs                = get_option( self::LOG_OPTION, array() );

        $logs = is_array( $logs ) ? array_reverse( $logs ) : array();

        if ( $recovery_token ) {
            delete_transient( $this->plugin_slug . '_recovery_token' );
        }

        if ( $force_logout_done ) {
            delete_transient( $this->plugin_slug . '_force_logout_done' );
            add_settings_error(
                $this->plugin_slug,
                'timu_force_logout_success',
                esc_html__( 'All other user sessions were terminated.', 'thisismyurl-login-support' ),
                'updated'
            );
        }

        ob_start();
        ?>
        <hr>
        <p><strong><?php esc_html_e( 'Security Utilities:', 'thisismyurl-login-support' ); ?></strong></p>
        <form method="post" action="<?php echo esc_url( $force_logout_action ); ?>">
            <?php wp_nonce_field( 'timu_force_logout' ); ?>
            <input type="hidden" name="timu_force_logout" value="1">
            <button type="submit"
                class="button button-link-delete"
                onclick="return confirm('<?php echo esc_js( __( 'Immediately log out all other users?', 'thisismyurl-login-support' ) ); ?>');">
                <?php esc_html_e( 'Force Global Logout', 'thisismyurl-login-support' ); ?>
            </button>
        </form>
        <p>
            <a href="<?php echo esc_url( $recovery_action_url ); ?>" class="button">
                <?php esc_html_e( 'Generate One-Time Recovery Link', 'thisismyurl-login-support' ); ?>
            </a>
        </p>
        <?php if ( ! empty( $recovery_token ) ) : ?>
            <p class="description">
                <?php esc_html_e( 'Use this one-time URL immediately and store it securely:', 'thisismyurl-login-support' ); ?>
                <br>
                <code><?php echo esc_html( add_query_arg( 'timu_recovery_token', rawurlencode( $recovery_token ), home_url( '/' ) ) ); ?></code>
            </p>
        <?php endif; ?>
        <?php
        $sidebar_extra = ob_get_clean();
        ?>
        <div class="wrap timu-admin-wrap">
            <?php $this->render_core_header(); ?>
            <?php settings_errors( $this->plugin_slug ); ?>
            <form method="post" action="options.php">
                <?php settings_fields( $this->options_group ); ?>
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <div class="timu-card">
                                <div class="timu-card-header"><?php esc_html_e( 'Configuration', 'thisismyurl-login-support' ); ?></div>
                                <div class="timu-card-body">
                                    <table class="form-table" role="presentation">
                                        <tr>
                                            <th scope="row" id="timu-label-shifting"><?php esc_html_e( 'Activate Stealth Mode', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-shifting" aria-labelledby="timu-label-shifting" aria-expanded="<?php echo $options['enable_shifting'] ? 'true' : 'false'; ?>" aria-controls="timu-row-shifting" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_shifting]" value="1" <?php checked( 1, (int) $options['enable_shifting'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-shifting" id="timu-row-shifting">
                                            <th scope="row"><?php esc_html_e( 'Secret Slug', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <input type="text" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[slug]" value="<?php echo esc_attr( $options['slug'] ); ?>" class="regular-text" />
                                                <p class="description"><?php esc_html_e( 'Example: secure-login. Do not use wp-admin or wp-login.php.', 'thisismyurl-login-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" id="timu-label-recovery"><?php esc_html_e( 'Enable Recovery Mode', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-recovery" aria-labelledby="timu-label-recovery" aria-expanded="<?php echo $options['enable_recovery_mode'] ? 'true' : 'false'; ?>" aria-controls="timu-row-recovery" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_recovery_mode]" value="1" <?php checked( 1, (int) $options['enable_recovery_mode'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Allow temporary one-time token access to recover login URL access.', 'thisismyurl-login-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-recovery" id="timu-row-recovery">
                                            <th scope="row"><?php esc_html_e( 'Recovery Token Lifetime (minutes)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="5" max="180" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[recovery_token_ttl]" value="<?php echo esc_attr( (string) $options['recovery_token_ttl'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row" id="timu-label-rate-limit"><?php esc_html_e( 'Enable Login Rate Limiting', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-rate-limit" aria-labelledby="timu-label-rate-limit" aria-expanded="<?php echo $options['enable_rate_limit'] ? 'true' : 'false'; ?>" aria-controls="timu-row-rate-limit-attempts timu-row-rate-limit-window timu-row-rate-limit-lockout" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_rate_limit]" value="1" <?php checked( 1, (int) $options['enable_rate_limit'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-rate-limit" id="timu-row-rate-limit-attempts">
                                            <th scope="row"><?php esc_html_e( 'Max Failed Attempts', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="20" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[rate_limit_attempts]" value="<?php echo esc_attr( (string) $options['rate_limit_attempts'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr class="timu-conditional-rate-limit" id="timu-row-rate-limit-window">
                                            <th scope="row"><?php esc_html_e( 'Attempt Window (minutes)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="120" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[rate_limit_window]" value="<?php echo esc_attr( (string) $options['rate_limit_window'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr class="timu-conditional-rate-limit" id="timu-row-rate-limit-lockout">
                                            <th scope="row"><?php esc_html_e( 'Lockout Duration (minutes)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="1440" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[lockout_minutes]" value="<?php echo esc_attr( (string) $options['lockout_minutes'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row" id="timu-label-logging"><?php esc_html_e( 'Enable Security Event Logging', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-logging" aria-labelledby="timu-label-logging" aria-expanded="<?php echo $options['enable_event_logging'] ? 'true' : 'false'; ?>" aria-controls="timu-row-logging" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_event_logging]" value="1" <?php checked( 1, (int) $options['enable_event_logging'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-logging" id="timu-row-logging">
                                            <th scope="row"><?php esc_html_e( 'Log Retention (days)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="365" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[log_retention_days]" value="<?php echo esc_attr( (string) $options['log_retention_days'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Honeypot Usernames', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <textarea name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[honeypot_usernames]" rows="3" class="regular-text"><?php echo esc_textarea( $options['honeypot_usernames'] ); ?></textarea>
                                                <p class="description"><?php esc_html_e( 'Comma-separated usernames. Any login attempt against these names — when no matching user exists on this site — triggers an immediate extended IP ban.', 'thisismyurl-login-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Honeypot Ban Duration (minutes)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="1440" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[honeypot_ban_minutes]" value="<?php echo esc_attr( (string) $options['honeypot_ban_minutes'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row" id="timu-label-filelog"><?php esc_html_e( 'Enable fail2ban File Log', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-filelog" aria-labelledby="timu-label-filelog" aria-expanded="<?php echo $options['enable_file_log'] ? 'true' : 'false'; ?>" aria-controls="timu-row-filelog" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_file_log]" value="1" <?php checked( 1, (int) $options['enable_file_log'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Write one line per failed login to a file for fail2ban integration.', 'thisismyurl-login-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-filelog" id="timu-row-filelog">
                                            <th scope="row"><?php esc_html_e( 'Log File Path', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <input type="text" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[file_log_path]" value="<?php echo esc_attr( $options['file_log_path'] ); ?>" class="regular-text" placeholder="/var/log/wp-login-support.log" />
                                                <p class="description"><?php esc_html_e( 'Absolute path outside the web root. The web server must have write permission. Format per line: YYYY-MM-DD HH:MM:SS ip username', 'thisismyurl-login-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <?php $this->render_registration_field(); ?>
                                    </table>
                                </div>
                            </div>
                            <div class="timu-card">
                                <div class="timu-card-header"><?php esc_html_e( 'Security Event Log', 'thisismyurl-login-support' ); ?></div>
                                <div class="timu-card-body">
                                    <form method="post" action="<?php echo esc_url( $clear_logs_action ); ?>">
                                        <?php wp_nonce_field( 'timu_clear_logs' ); ?>
                                        <input type="hidden" name="timu_clear_logs" value="1">
                                        <button type="submit"
                                            class="button button-secondary"
                                            onclick="return confirm('<?php echo esc_js( __( 'Clear all security logs?', 'thisismyurl-login-support' ) ); ?>');">
                                            <?php esc_html_e( 'Clear Logs', 'thisismyurl-login-support' ); ?>
                                        </button>
                                    </form>
                                    <table class="widefat striped">
                                        <caption class="screen-reader-text"><?php esc_html_e( 'Security Event Log', 'thisismyurl-login-support' ); ?></caption>
                                        <thead>
                                            <tr>
                                                <th scope="col"><?php esc_html_e( 'Date', 'thisismyurl-login-support' ); ?></th>
                                                <th scope="col"><?php esc_html_e( 'Event', 'thisismyurl-login-support' ); ?></th>
                                                <th scope="col"><?php esc_html_e( 'User', 'thisismyurl-login-support' ); ?></th>
                                                <th scope="col"><?php esc_html_e( 'IP', 'thisismyurl-login-support' ); ?></th>
                                                <th scope="col"><?php esc_html_e( 'Details', 'thisismyurl-login-support' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ( empty( $logs ) ) : ?>
                                                <tr><td colspan="5"><?php esc_html_e( 'No events recorded yet.', 'thisismyurl-login-support' ); ?></td></tr>
                                            <?php else : ?>
                                                <?php foreach ( array_slice( $logs, 0, 100 ) as $log ) : ?>
                                                    <tr>
                                                        <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $log['time'] ) ); ?></td>
                                                        <td><?php echo esc_html( $log['event'] ); ?></td>
                                                        <td><?php echo esc_html( ! empty( $log['user'] ) ? $log['user'] : '-' ); ?></td>
                                                        <td><?php echo esc_html( ! empty( $log['ip'] ) ? $log['ip'] : '-' ); ?></td>
                                                        <td><?php echo esc_html( ! empty( $log['details'] ) ? $log['details'] : '-' ); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="timu-card">
                                <div class="timu-card-header"><?php esc_html_e( 'Failed Login Attempts — Last 24 Hours', 'thisismyurl-login-support' ); ?></div>
                                <div class="timu-card-body">
                                    <?php
                                    // Build 24 hourly buckets (index 0 = oldest, 23 = current hour).
                                    $sparkline_now     = time();
                                    $sparkline_buckets = array_fill( 0, 24, 0 );
                                    $sparkline_logs    = get_option( self::LOG_OPTION, array() );

                                    foreach ( (array) $sparkline_logs as $sl_entry ) {
                                        if ( empty( $sl_entry['event'] ) || false === stripos( $sl_entry['event'], 'failed' ) ) {
                                            continue;
                                        }
                                        $age_hours = (int) floor( ( $sparkline_now - (int) $sl_entry['time'] ) / 3600 );
                                        if ( $age_hours >= 0 && $age_hours < 24 ) {
                                            $sparkline_buckets[ 23 - $age_hours ]++;
                                        }
                                    }
                                    $sparkline_max   = max( 1, max( $sparkline_buckets ) );
                                    $sparkline_total = array_sum( $sparkline_buckets );
                                    ?>
                                    <canvas id="timu-sparkline" width="600" height="80"
                                        role="img"
                                        aria-label="<?php echo esc_attr( sprintf( /* translators: %d = number of failed login attempts */ __( '%d failed login attempts in the last 24 hours', 'thisismyurl-login-support' ), $sparkline_total ) ); ?>"
                                        aria-describedby="timu-sparkline-detail"
                                        style="max-width:100%;border:1px solid #dcdcde;border-radius:3px;">
                                        <p><?php echo esc_html( sprintf( /* translators: %d = total failed attempts */ __( '%d failed login attempts in the last 24 hours.', 'thisismyurl-login-support' ), $sparkline_total ) ); ?></p>
                                    </canvas>
                                    <script>
                                    (function(){
                                        var data = <?php echo wp_json_encode( $sparkline_buckets ); ?>;
                                        var max  = <?php echo (int) $sparkline_max; ?>;
                                        var c    = document.getElementById('timu-sparkline');
                                        if (!c || !c.getContext) return;
                                        var ctx  = c.getContext('2d');
                                        var w = c.width, h = c.height;
                                        var barW = Math.floor(w / data.length);
                                        var gap  = 2;
                                        ctx.clearRect(0, 0, w, h);
                                        for (var i = 0; i < data.length; i++) {
                                            var val    = data[i];
                                            var barH   = val > 0 ? Math.max(4, Math.round((val / max) * (h - 8))) : 2;
                                            var x      = i * barW + gap;
                                            var y      = h - barH;
                                            ctx.fillStyle = val > 0 ? '#d63638' : '#dcdcde';
                                            ctx.fillRect(x, y, barW - gap * 2, barH);
                                        }
                                    })();
                                    </script>
                                    <details id="timu-sparkline-detail" style="margin-top:8px;">
                                        <summary><?php esc_html_e( 'Hourly breakdown', 'thisismyurl-login-support' ); ?></summary>
                                        <table class="widefat striped" style="margin-top:6px;">
                                            <thead><tr><th><?php esc_html_e( 'Hour (oldest → newest)', 'thisismyurl-login-support' ); ?></th><th><?php esc_html_e( 'Failed attempts', 'thisismyurl-login-support' ); ?></th></tr></thead>
                                            <tbody>
                                            <?php for ( $i = 0; $i < 24; $i++ ) : ?>
                                                <tr>
                                                    <td><?php echo esc_html( sprintf( /* translators: %d = hour offset, 0=oldest */ __( '%d hrs ago', 'thisismyurl-login-support' ), 23 - $i ) ); ?></td>
                                                    <td><?php echo (int) $sparkline_buckets[ $i ]; ?></td>
                                                </tr>
                                            <?php endfor; ?>
                                            </tbody>
                                        </table>
                                    </details>
                                </div>
                            </div>
                            <div class="timu-card">
                                <div class="timu-card-header"><?php esc_html_e( 'Two Factor Compatibility', 'thisismyurl-login-support' ); ?></div>
                                <div class="timu-card-body">
                                    <?php $two_fa_active = $this->is_two_factor_active(); ?>
                                    <p>
                                        <strong><?php esc_html_e( 'Status:', 'thisismyurl-login-support' ); ?></strong>
                                        <?php if ( $two_fa_active ) : ?>
                                            <span style="color:#00a32a;">&#10003; <?php esc_html_e( 'A compatible Two Factor plugin is active.', 'thisismyurl-login-support' ); ?></span>
                                        <?php else : ?>
                                            <span><?php esc_html_e( 'No compatible Two Factor plugin detected.', 'thisismyurl-login-support' ); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <p><?php esc_html_e( 'When a Two Factor plugin is active, Login Support yields the rate-limit check during the 2FA challenge phase so that the second factor is never blocked by an IP counter.', 'thisismyurl-login-support' ); ?></p>
                                    <p><?php esc_html_e( 'To enforce rate-limiting on top of 2FA, add this to your theme or a plugin:', 'thisismyurl-login-support' ); ?></p>
                                    <code>add_filter( 'thisismyurl_login_support_skip_for_2fa', '__return_false' );</code>
                                    <h4><?php esc_html_e( 'Tested compatible plugins', 'thisismyurl-login-support' ); ?></h4>
                                    <ul>
                                        <li>Two Factor (<code>two-factor/two-factor.php</code>)</li>
                                        <li>WP 2FA (<code>wp-2fa/wp-2fa.php</code>)</li>
                                        <li>miniOrange 2 Factor Authentication (<code>miniorange-2-factor-authentication/...</code>)</li>
                                    </ul>
                                </div>
                            </div>
                            <?php submit_button( esc_html__( 'Save Settings', 'thisismyurl-login-support' ), 'primary large' ); ?>
                        </div>
                        <?php $this->render_core_sidebar( $sidebar_extra ); ?>
                    </div>
                </div>
            </form>
            <?php $this->render_core_footer(); ?>
        </div>
        <?php
    }

    public function handle_login_shifting() {
        // Constant escape hatch — `define( 'TIMU_LOGIN_SUPPORT_DISABLE', true );`
        // in wp-config.php fully bypasses slug shifting and recovery-token
        // logic. Closes audit-finding #2 (P0 — alternative path).
        if ( defined( 'TIMU_LOGIN_SUPPORT_DISABLE' ) && TIMU_LOGIN_SUPPORT_DISABLE ) {
            return;
        }

        $recovery_token = isset( $_GET['timu_recovery_token'] )
            ? sanitize_text_field( wp_unslash( $_GET['timu_recovery_token'] ) )
            : '';

        if ( ! empty( $recovery_token ) && $this->is_valid_recovery_token( $recovery_token ) ) {
            // Strip the token from any onward Referer header. Defends against
            // accidental token leakage to third-party assets loaded by the
            // login screen. Closes audit-finding #15 (P2).
            if ( ! headers_sent() ) {
                header( 'Referrer-Policy: no-referrer' );
            }

            $this->set_recovery_bypass_cookie();
            $this->consume_recovery_token();
            $this->log_event( 'Recovery token used', 'Temporary login bypass cookie issued (session-bound, not IP-bound)' );
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        if ( ! $this->is_shifting_enabled() ) {
            return;
        }

        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $request_path = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
        $secret_slug  = $this->get_login_slug();

        if ( $secret_slug === $request_path ) {
            require_once ABSPATH . 'wp-login.php';
            exit;
        }

        if ( 'wp-login.php' === basename( $request_path ) ) {
            if ( $this->recovery_bypass_active() ) {
                return;
            }

            wp_safe_redirect( home_url( '/' ) );
            exit;
        }
    }

    /**
     * Enforce the rate-limit lockout BEFORE WordPress evaluates the password.
     *
     * Hooked on `wp_authenticate` (priority 5). At this point WP has not yet
     * touched the credentials — `wp_authenticate_username_password` runs at
     * priority 20 on the `authenticate` filter. Hooking earlier and using
     * `wp_die()` to halt execution ensures a correct password CANNOT bypass an
     * active lockout (the bug fixed by GHSA-p369-rjwx-f44g).
     *
     * The action signature is `wp_authenticate( &$username, &$password )`. The
     * username is also available on $_POST['log']; we accept the by-reference
     * argument as authoritative.
     *
     * Two budgets are checked:
     *   1. Per-(username, IP) lockout — protects a specific account.
     *   2. Per-IP lockout              — protects against username rotation
     *                                    from a single source. Closes
     *                                    audit-finding #3 (P1).
     *
     * Skip behaviour:
     *   - When no username is supplied (e.g. an empty form submission), we let
     *     WP handle the empty-username error itself.
     *   - When a 2FA plugin we recognise is active and the
     *     `thisismyurl_login_support_skip_for_2fa` filter returns truthy, we
     *     yield. Closes audit-finding #11 (P1).
     *
     * @param string|null $username
     * @param string|null $password
     * @return void
     */
    public function enforce_rate_limit( $username, $password = null ) {
        $options = $this->get_controlled_options();

        if ( empty( $options['enable_rate_limit'] ) ) {
            return;
        }

        $username = is_string( $username ) ? trim( $username ) : '';

        if ( '' === $username ) {
            return;
        }

        if ( $this->should_skip_for_2fa() ) {
            return;
        }

        $ip                 = $this->get_client_ip();
        $username_lock_key  = $this->get_lockout_cache_key( $username, $ip );
        $ip_lock_key        = $this->get_ip_lockout_cache_key( $ip );

        if ( get_transient( $username_lock_key ) || get_transient( $ip_lock_key ) ) {
            $this->log_event( 'Login blocked by rate limiter', 'Active lockout intercepted authentication request', $username );

            // wp_die() is the correct halt here. Returning a WP_Error from
            // wp_authenticate is not contractual (it's an action, not a filter);
            // an early wp_die guarantees the password check downstream cannot
            // run, regardless of credential correctness.
            wp_die(
                esc_html__( 'Too many failed login attempts. Please try again later.', 'thisismyurl-login-support' ),
                esc_html__( 'Login locked', 'thisismyurl-login-support' ),
                array( 'response' => 429 )
            );
        }
    }

    /**
     * Detect popular 2FA plugins. The `thisismyurl_login_support_skip_for_2fa`
     * filter inverts the default — return true to skip (the default), false to
     * keep enforcing the rate limit on top of 2FA.
     *
     * @return bool
     */
    private function should_skip_for_2fa() {
        // is_plugin_active() lives in wp-admin/includes/plugin.php which is
        // not guaranteed to be loaded on the front-end auth path. Pull it in
        // when we need it.
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $known = array(
            'two-factor/two-factor.php',
            'wp-2fa/wp-2fa.php',
            'miniorange-2-factor-authentication/miniorange_2_factor_settings.php',
        );

        $active = false;

        foreach ( $known as $plugin_file ) {
            if ( is_plugin_active( $plugin_file ) ) {
                $active = true;
                break;
            }
        }

        if ( ! $active ) {
            return false;
        }

        return (bool) apply_filters( 'thisismyurl_login_support_skip_for_2fa', true );
    }

    /**
     * Register the read-only REST endpoint for lockout state (issue #31).
     * Endpoint: GET /wp-json/timu-login-support/v1/lockouts
     * Auth: manage_options capability.
     */
    public function register_rest_routes() {
        register_rest_route(
            'timu-login-support/v1',
            '/lockouts',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_lockouts' ),
                'permission_callback' => array( $this, 'rest_lockouts_permission' ),
                'schema'              => array( $this, 'rest_lockouts_schema' ),
            )
        );
    }

    public function rest_lockouts_permission() {
        return current_user_can( 'manage_options' );
    }

    public function rest_get_lockouts() {
        return rest_ensure_response( self::get_active_lockouts() );
    }

    /**
     * Read the lockout registry and return only the currently-active lockouts.
     *
     * Single source of truth for "what is locked out right now" — shared by the
     * REST endpoint and the WP 7 Abilities API registration. Expired rows are
     * skipped (the registry is pruned lazily on write, so this read-side filter
     * is what guarantees freshness).
     *
     * @since 1.6147
     *
     * @return array<int, array{type:string, identifier:string, locked_until:int, ttl_seconds:int}>
     */
    public static function get_active_lockouts() {
        $registry = get_option( self::LOCKOUT_REGISTRY, array() );
        $registry = is_array( $registry ) ? $registry : array();
        $now      = time();
        $active   = array();

        foreach ( $registry as $entry ) {
            if ( ! isset( $entry['until'] ) || $entry['until'] <= $now ) {
                continue;
            }

            $active[] = array(
                'type'         => sanitize_text_field( $entry['type'] ),
                'identifier'   => sanitize_text_field( $entry['value'] ),
                'locked_until' => (int) $entry['until'],
                'ttl_seconds'  => (int) $entry['until'] - $now,
            );
        }

        return $active;
    }

    public function rest_lockouts_schema() {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'lockout',
            'type'       => 'array',
            'items'      => array(
                'type'       => 'object',
                'properties' => array(
                    'type'         => array( 'type' => 'string', 'enum' => array( 'account', 'ip' ) ),
                    'identifier'   => array( 'type' => 'string' ),
                    'locked_until' => array( 'type' => 'integer' ),
                    'ttl_seconds'  => array( 'type' => 'integer' ),
                ),
            ),
        );
    }

    /**
     * Mark Application Password failures so track_failed_login() doesn't burn
     * the user/IP rate-limit budget on legitimate API traffic with rotated
     * credentials. Closes audit-finding #10 (P1).
     *
     * Signature: ( WP_Error $error, WP_User $user, array $app_password, array $args ).
     *
     * @param mixed $error Unused — we only care that the hook fired.
     */
    public function mark_application_password_failure( $error, $user = null, $app_password = null, $args = null ) {
        // Static flag picked up by track_failed_login on this same request.
        $GLOBALS['timu_login_support_app_password_failure'] = true;

        return $error;
    }

    public function track_failed_login( $username ) {
        $options = $this->get_controlled_options();

        // Application Password failure carve-out (audit-finding #10).
        if ( ! empty( $GLOBALS['timu_login_support_app_password_failure'] ) ) {
            $this->log_event( 'Application Password failure (not counted)', 'API path skipped browser-login budget', (string) $username );
            unset( $GLOBALS['timu_login_support_app_password_failure'] );
            return;
        }

        // Honeypot username check (issue #37). If the attempted username is in
        // the honeypot list AND doesn't correspond to a real user, treat the
        // request as malicious and apply an extended IP ban immediately.
        $honeypot_raw = isset( $options['honeypot_usernames'] ) ? $options['honeypot_usernames'] : 'admin,root,administrator';
        $honeypot_ban = isset( $options['honeypot_ban_minutes'] ) ? absint( $options['honeypot_ban_minutes'] ) : 60;
        $honeypots    = array_filter( array_map( 'trim', explode( ',', $honeypot_raw ) ) );

        if ( ! empty( $honeypots ) && in_array( strtolower( (string) $username ), array_map( 'strtolower', $honeypots ), true ) ) {
            if ( ! get_user_by( 'login', (string) $username ) ) {
                $ip              = $this->get_client_ip();
                $ip_lock_key_hp  = $this->get_ip_lockout_cache_key( $ip );
                $hp_seconds      = MINUTE_IN_SECONDS * $honeypot_ban;
                set_transient( $ip_lock_key_hp, 1, $hp_seconds );
                $this->record_lockout( 'ip', $ip, time() + $hp_seconds );
                $this->log_event( 'Honeypot triggered', sprintf( 'IP banned %d min — username matched honeypot list', $honeypot_ban ), (string) $username );
                return;
            }
        }

        if ( empty( $options['enable_rate_limit'] ) ) {
            $this->log_event( 'Failed login', 'Login failed while rate limiting is disabled', (string) $username );
            return;
        }

        $ip                  = $this->get_client_ip();
        $username            = (string) $username;
        $window_seconds      = MINUTE_IN_SECONDS * absint( $options['rate_limit_window'] );
        $lockout_seconds     = MINUTE_IN_SECONDS * absint( $options['lockout_minutes'] );
        $per_account_max     = absint( $options['rate_limit_attempts'] );
        $per_ip_max          = $this->get_ip_attempt_threshold( $per_account_max );

        // Per-(username, IP) budget.
        $attempts_key      = $this->get_rate_limit_cache_key( $username, $ip );
        $username_lock_key = $this->get_lockout_cache_key( $username, $ip );
        $attempts          = absint( get_transient( $attempts_key ) ) + 1;

        set_transient( $attempts_key, $attempts, $window_seconds );

        // Per-IP budget (independent of username — credential-stuffing defence).
        $ip_attempts_key = $this->get_ip_rate_limit_cache_key( $ip );
        $ip_lock_key     = $this->get_ip_lockout_cache_key( $ip );
        $ip_attempts     = absint( get_transient( $ip_attempts_key ) ) + 1;

        set_transient( $ip_attempts_key, $ip_attempts, $window_seconds );

        $this->log_event(
            'Failed login',
            sprintf( 'attempts: user=%d ip=%d', $attempts, $ip_attempts ),
            $username
        );

        if ( $attempts >= $per_account_max ) {
            set_transient( $username_lock_key, 1, $lockout_seconds );
            delete_transient( $attempts_key );
            $this->record_lockout( 'account', $username, time() + $lockout_seconds );
            $this->log_event( 'Rate limit lockout (per account)', 'Lockout triggered for user/IP pair', $username );
        }

        if ( $ip_attempts >= $per_ip_max ) {
            set_transient( $ip_lock_key, 1, $lockout_seconds );
            delete_transient( $ip_attempts_key );
            $this->record_lockout( 'ip', $ip, time() + $lockout_seconds );
            $this->log_event( 'Rate limit lockout (per IP)', 'Lockout triggered for IP — possible username rotation', $username );
        }
    }

    public function track_successful_login( $user_login, $user ) {
        $ip                = $this->get_client_ip();
        $attempts_key      = $this->get_rate_limit_cache_key( $user_login, $ip );
        $username_lock_key = $this->get_lockout_cache_key( $user_login, $ip );

        // Clear the per-(username,IP) state on success. Do NOT clear the per-IP
        // budget — a successful login from one account on a probing IP doesn't
        // erase the suspicious pattern of N other usernames being tried.
        delete_transient( $attempts_key );
        delete_transient( $username_lock_key );

        $this->log_event( 'Successful login', 'Authentication succeeded', (string) $user_login );
    }

    public function handle_option_updates( $old_value, $new_value ) {
        $old_value = is_array( $old_value ) ? $old_value : array();
        $new_value = is_array( $new_value ) ? $new_value : array();

        // Capture the toggle BEFORE honouring it. If logging is being turned
        // off, this is the last entry that will land before the toggle takes
        // effect on the next request — important for audit-trail integrity.
        // Closes audit-finding #19 (P2): the disable event should be the final
        // event recorded, never silently dropped.
        $old_logging = isset( $old_value['enable_event_logging'] ) ? (int) $old_value['enable_event_logging'] : 1;
        $new_logging = isset( $new_value['enable_event_logging'] ) ? (int) $new_value['enable_event_logging'] : 1;

        if ( $old_logging !== $new_logging ) {
            // Re-read options ourselves and bypass the toggle in log_event for
            // this single audit entry — the disable event must always land.
            $logs   = get_option( self::LOG_OPTION, array() );
            $logs   = is_array( $logs ) ? $logs : array();
            $logs[] = array(
                'time'    => time(),
                'event'   => 0 === $new_logging ? 'Event logging disabled' : 'Event logging enabled',
                'ip'      => sanitize_text_field( $this->get_client_ip() ),
                'user'    => sanitize_text_field( wp_get_current_user()->user_login ),
                'details' => 'Logging toggle change recorded out-of-band so the disable event itself is preserved.',
            );

            if ( count( $logs ) > 500 ) {
                $logs = array_slice( $logs, -500 );
            }

            update_option( self::LOG_OPTION, $logs, false );
        }

        if ( ( $old_value['slug'] ?? '' ) !== ( $new_value['slug'] ?? '' ) ) {
            $this->log_event( 'Secret slug updated', 'Stealth login slug changed by admin' );
        }

        if ( ( $old_value['enable_shifting'] ?? 0 ) !== ( $new_value['enable_shifting'] ?? 0 ) ) {
            $enabled = empty( $new_value['enable_shifting'] ) ? 'disabled' : 'enabled';
            $this->log_event( 'Stealth mode updated', 'Stealth mode was ' . $enabled );
        }
    }

    public function register_site_health_tests( $tests ) {
        $tests['direct'][ $this->plugin_slug . '_security_posture' ] = array(
            'label' => esc_html__( 'Login Support Security Configuration', 'thisismyurl-login-support' ),
            'test'  => array( $this, 'run_site_health_security_test' ),
        );

        return $tests;
    }

    public function run_site_health_security_test() {
        $options = $this->get_controlled_options();
        $good    = 0;
        $issues  = array();

        if ( ! empty( $options['enable_shifting'] ) && ! empty( $options['slug'] ) ) {
            $good++;
        } else {
            $issues[] = esc_html__( 'Stealth Mode is disabled or secret slug is empty.', 'thisismyurl-login-support' );
        }

        if ( ! empty( $options['enable_rate_limit'] ) ) {
            $good++;
        } else {
            $issues[] = esc_html__( 'Login rate limiting is disabled.', 'thisismyurl-login-support' );
        }

        if ( ! empty( $options['enable_event_logging'] ) ) {
            $good++;
        } else {
            $issues[] = esc_html__( 'Security event logging is disabled.', 'thisismyurl-login-support' );
        }

        // Filter to mark a setting as "deliberately disabled" so Site Health
        // doesn't flag it. Closes audit-finding #25 (P3). Useful for sites
        // that legitimately don't want, e.g., Stealth Mode (managed at the
        // edge instead).
        $deliberate = (array) apply_filters( 'thisismyurl_login_support_deliberately_disabled', array() );

        if ( in_array( 'enable_shifting', $deliberate, true ) ) {
            $good++;
        }
        if ( in_array( 'enable_rate_limit', $deliberate, true ) ) {
            $good++;
        }
        if ( in_array( 'enable_event_logging', $deliberate, true ) ) {
            $good++;
        }

        $good = min( 3, $good );

        if ( 3 === $good ) {
            return array(
                'label'       => esc_html__( 'Login Support security controls are active.', 'thisismyurl-login-support' ),
                'status'      => 'good',
                'badge'       => array(
                    'label' => esc_html__( 'Security', 'thisismyurl-login-support' ),
                    'color' => 'blue',
                ),
                'description' => esc_html__( 'Stealth mode, rate limiting, and event logging are enabled.', 'thisismyurl-login-support' ),
                'actions'     => '',
                'test'        => $this->plugin_slug . '_security_posture',
            );
        }

        return array(
            'label'       => esc_html__( 'Login Support security controls need attention.', 'thisismyurl-login-support' ),
            'status'      => 'recommended',
            'badge'       => array(
                'label' => esc_html__( 'Security', 'thisismyurl-login-support' ),
                'color' => 'blue',
            ),
            'description' => esc_html( implode( ' ', $issues ) ),
            'actions'     => sprintf(
                '<p><a class="button button-primary" href="%s">%s</a></p>',
                esc_url( admin_url( 'options-general.php?page=' . $this->plugin_slug ) ),
                esc_html__( 'Review Login Support settings', 'thisismyurl-login-support' )
            ),
            'test'        => $this->plugin_slug . '_security_posture',
        );
    }

    public function rewrite_login_urls( $url, $path, $scheme, $blog_id ) {
        if ( ! $this->is_shifting_enabled() ) {
            return $url;
        }

        if ( 'wp-login.php' === $path ) {
            return home_url( '/' . $this->get_login_slug() . '/' );
        }

        return $url;
    }
}

new TIMU_Login_Support();

// WP 7 Abilities API: expose read-only login-lockout status. The callback
// inside fires on `wp_abilities_api_init` (WordPress 6.9+), long after the
// class above is defined, so this require order is safe.
require_once plugin_dir_path( __FILE__ ) . 'abilities.php';

/**
 * GitHub Updater Integration.
 * Loads the updater logic once all plugins have been loaded by WordPress.
 */
add_action( 'plugins_loaded', function() {
    require_once plugin_dir_path( __FILE__ ) . 'github-updater.php';
    \ThisIsMyURL\LoginSupport\GitHubReleaseUpdater::boot( array(
        'plugin_file' => __FILE__,
        'slug'        => 'thisismyurl-login-support',
        'repo'        => 'thisismyurl/thisismyurl-login-support',
    ) );
} );

/**
 * WP-CLI escape hatch.
 *
 * Closes audit-finding #2 (P0 — admin recovery) and the [feature][P2] WP-CLI
 * tracker. Provides four sub-commands:
 *
 *   wp login-support unlock <user-or-ip>
 *   wp login-support reset-slug
 *   wp login-support generate-recovery
 *   wp login-support logs [--limit=<n>] [--clear]
 *
 * Every command bypasses the browser UI so an admin who has lost access to
 * the secret slug can recover via SSH/WP-CLI without renaming the plugin
 * folder. Documented in readme.txt.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once plugin_dir_path( __FILE__ ) . 'core/class-timu-cli.php';
    WP_CLI::add_command( 'login-support', 'TIMU_Login_Support_CLI' );
}