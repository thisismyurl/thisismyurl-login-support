<?php
/**
 * Uninstall handler for Login Support by thisismyurl.com.
 *
 * Fires when the user removes the plugin via the WordPress admin (NOT on
 * deactivation). Cleans every option, transient, and scheduled event the
 * plugin owns. Closes audit-finding #8.
 *
 * @package thisismyurl-login-support
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$slug = 'thisismyurl-login-support';

// Discrete options.
delete_option( $slug . '_options' );
delete_option( $slug . '_logs' );

// Any standalone transients the plugin authored.
delete_transient( $slug . '_recovery' );
delete_transient( $slug . '_recovery_token' );
delete_transient( $slug . '_force_logout_done' );

// Pattern-match every transient owned by the plugin: rate-limit attempts,
// lockouts (per-account and per-IP), and recovery-bypass lookups. Pull the
// option_names directly so we hit both `_transient_<name>` and
// `_transient_timeout_<name>`.
$like_patterns = array(
    '_transient_' . $slug . '_%',
    '_transient_timeout_' . $slug . '_%',
    '_site_transient_' . $slug . '_%',
    '_site_transient_timeout_' . $slug . '_%',
);

foreach ( $like_patterns as $like ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( $like ) . '%'
        )
    );
}

// Multisite — repeat for sitemeta if applicable.
if ( is_multisite() ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like( '_site_transient_' . $slug . '_' ) . '%'
        )
    );
}

// Clear any scheduled cron events authored under our slug.
$cron = _get_cron_array();

if ( is_array( $cron ) ) {
    foreach ( $cron as $timestamp => $hooks ) {
        if ( ! is_array( $hooks ) ) {
            continue;
        }

        foreach ( array_keys( $hooks ) as $hook ) {
            if ( 0 === strpos( (string) $hook, $slug ) ) {
                wp_clear_scheduled_hook( $hook );
            }
        }
    }
}

// Flush object cache so the deletions stick on hosts with persistent cache.
wp_cache_flush();
