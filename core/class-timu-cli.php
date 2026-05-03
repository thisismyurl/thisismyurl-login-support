<?php
/**
 * WP-CLI commands for Login Support by thisismyurl.com.
 *
 * Loaded only when WP_CLI is defined. The plugin's main class is responsible
 * for storing options + transients; this class only mutates that state via
 * the same option/transient keys.
 *
 * @package thisismyurl-login-support
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Manage the Login Support plugin from the command line.
 */
class TIMU_Login_Support_CLI {

    const PLUGIN_SLUG  = 'thisismyurl-login-support';
    const LOG_OPTION   = 'thisismyurl-login-support_logs';
    const RECOVERY_KEY = 'thisismyurl-login-support_recovery';

    /**
     * Clear the lockout for a username or IP.
     *
     * ## OPTIONS
     *
     * <target>
     * : Username, email, or IP to unlock. Pass `--all` to clear everything.
     *
     * [--all]
     * : Clear every lockout transient owned by this plugin.
     *
     * ## EXAMPLES
     *
     *   wp login-support unlock cross
     *   wp login-support unlock 198.51.100.4
     *   wp login-support unlock --all
     */
    public function unlock( $args, $assoc_args ) {
        global $wpdb;

        if ( ! empty( $assoc_args['all'] ) || ( isset( $args[0] ) && '--all' === $args[0] ) ) {
            // Match every lockout/attempts transient.
            $like = $wpdb->esc_like( '_transient_' . self::PLUGIN_SLUG . '_lock' ) . '%';
            $rows = $wpdb->query(
                $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                    $like,
                    $wpdb->esc_like( '_transient_timeout_' . self::PLUGIN_SLUG . '_lock' ) . '%',
                    $wpdb->esc_like( '_transient_' . self::PLUGIN_SLUG . '_rl' ) . '%',
                    $wpdb->esc_like( '_transient_timeout_' . self::PLUGIN_SLUG . '_rl' ) . '%'
                )
            );

            wp_cache_flush();
            WP_CLI::success( sprintf( 'Cleared %d lockout/attempt transient rows.', (int) $rows ) );
            return;
        }

        $target = isset( $args[0] ) ? trim( $args[0] ) : '';

        if ( '' === $target ) {
            WP_CLI::error( 'Pass a username, email, or IP — or `--all`.' );
        }

        // Resolve email -> login if needed.
        if ( is_email( $target ) ) {
            $user = get_user_by( 'email', $target );

            if ( $user ) {
                $target = $user->user_login;
            }
        }

        $is_ip   = (bool) filter_var( $target, FILTER_VALIDATE_IP );
        $cleared = 0;

        if ( $is_ip ) {
            // Per-IP lockout & per-IP attempts.
            $cleared += (int) delete_transient( self::PLUGIN_SLUG . '_lock_ip_' . md5( $target ) );
            $cleared += (int) delete_transient( self::PLUGIN_SLUG . '_rl_ip_' . md5( $target ) );

            // Best-effort scan for per-(username, IP) keys involving this IP.
            // Cheap path: ask the DB.
            $like_lock = $wpdb->esc_like( '_transient_' . self::PLUGIN_SLUG . '_lock_' ) . '%';
            $rows      = $wpdb->get_col(
                $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like_lock )
            );

            foreach ( $rows as $row ) {
                $name = preg_replace( '/^_transient_/', '', $row );
                // Cannot reverse md5 — we just nuke any lockout that might be
                // related when the operator asked for an IP. Document the
                // tradeoff: --all is the precise tool for "wipe everything".
                $value = get_transient( $name );

                if ( $value ) {
                    delete_transient( $name );
                    $cleared++;
                }
            }
        } else {
            // Username path — we cannot reverse md5( "$user|$ip" ) cheaply,
            // so the username unlock walks every lockout transient and clears
            // any whose row was authored under this plugin. The footprint is
            // bounded by the rate-limit window, so this is fine.
            $like = $wpdb->esc_like( '_transient_' . self::PLUGIN_SLUG . '_lock_' ) . '%';
            $rows = $wpdb->get_col(
                $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
            );

            foreach ( $rows as $row ) {
                $name = preg_replace( '/^_transient_/', '', $row );
                delete_transient( $name );
                $cleared++;
            }
        }

        WP_CLI::success( sprintf( 'Cleared %d lockout records for %s.', $cleared, $target ) );
    }

    /**
     * Reset the secret login slug — disables Stealth Mode and clears the slug.
     *
     * ## EXAMPLES
     *
     *   wp login-support reset-slug
     */
    public function reset_slug() {
        $options = get_option( self::PLUGIN_SLUG . '_options', array() );
        $options = is_array( $options ) ? $options : array();

        $options['enable_shifting'] = 0;
        $options['slug']            = '';

        update_option( self::PLUGIN_SLUG . '_options', $options );
        WP_CLI::success( 'Stealth Mode disabled and secret slug cleared. /wp-login.php is reachable again.' );
    }

    /**
     * Generate a one-time recovery URL and print it to stdout.
     *
     * ## EXAMPLES
     *
     *   wp login-support generate-recovery
     */
    public function generate_recovery() {
        $token = wp_generate_password( 24, false, false );
        $token = strtolower( $token );

        $options = get_option( self::PLUGIN_SLUG . '_options', array() );
        $options = is_array( $options ) ? $options : array();
        $ttl     = isset( $options['recovery_token_ttl'] ) ? max( 5, (int) $options['recovery_token_ttl'] ) : 20;

        set_transient(
            self::RECOVERY_KEY,
            array(
                'hash'    => wp_hash( $token ),
                'expires' => time() + ( MINUTE_IN_SECONDS * $ttl ),
            ),
            MINUTE_IN_SECONDS * $ttl
        );

        $url = add_query_arg( 'timu_recovery_token', rawurlencode( $token ), home_url( '/' ) );

        WP_CLI::log( sprintf( 'Recovery URL (valid for %d minutes):', $ttl ) );
        WP_CLI::log( $url );
        WP_CLI::success( 'Open this URL in a browser. The token is single-use.' );
    }

    /**
     * Print or clear the security event log.
     *
     * ## OPTIONS
     *
     * [--limit=<n>]
     * : Number of recent entries to show. Default 50.
     *
     * [--clear]
     * : Wipe the log instead of printing.
     *
     * ## EXAMPLES
     *
     *   wp login-support logs
     *   wp login-support logs --limit=200
     *   wp login-support logs --clear
     */
    public function logs( $args, $assoc_args ) {
        if ( ! empty( $assoc_args['clear'] ) ) {
            update_option( self::LOG_OPTION, array(), false );
            WP_CLI::success( 'Security log cleared.' );
            return;
        }

        $limit = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 50;
        $logs  = get_option( self::LOG_OPTION, array() );
        $logs  = is_array( $logs ) ? $logs : array();
        $logs  = array_reverse( $logs );
        $logs  = array_slice( $logs, 0, $limit );

        if ( empty( $logs ) ) {
            WP_CLI::log( 'No events recorded yet.' );
            return;
        }

        $rows = array();

        foreach ( $logs as $log ) {
            $rows[] = array(
                'time'  => gmdate( 'Y-m-d H:i:s', isset( $log['time'] ) ? (int) $log['time'] : 0 ),
                'event' => isset( $log['event'] ) ? (string) $log['event'] : '',
                'user'  => isset( $log['user'] ) ? (string) $log['user'] : '',
                'ip'    => isset( $log['ip'] ) ? (string) $log['ip'] : '',
            );
        }

        WP_CLI\Utils\format_items( 'table', $rows, array( 'time', 'event', 'user', 'ip' ) );
    }
}
