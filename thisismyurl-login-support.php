<?php
/**
 * Plugin Name: Login Support by thisismyurl.com
 * Plugin URI:  https://thisismyurl.com/
 * Description: Harden login access by allowing a custom login slug and admin security controls.
 * Version:     0.6112
 * Author:      thisismyurl.com
 * Author URI:  https://thisismyurl.com/
 * Text Domain: thisismyurl-login-support
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'TIMU_LOGIN_SUPPORT_VERSION', '0.6112' );

require_once plugin_dir_path( __FILE__ ) . 'core/class-timu-core.php';

class TIMU_Login_Support extends TIMU_Core_v1 {

    const LOG_OPTION = 'thisismyurl-login-support_logs';

    public function __construct() {
        parent::__construct( 'thisismyurl-login-support', plugin_dir_url( __FILE__ ), 'timu_login_group' );

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'admin_init', array( $this, 'handle_force_logout' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'setup_theme', array( $this, 'handle_login_shifting' ), 1 );
        add_filter( 'site_url', array( $this, 'rewrite_login_urls' ), 10, 4 );
        add_filter( 'authenticate', array( $this, 'enforce_rate_limit' ), 30, 3 );
        add_action( 'wp_login_failed', array( $this, 'track_failed_login' ) );
        add_action( 'wp_login', array( $this, 'track_successful_login' ), 10, 2 );
        add_action( 'update_option_' . $this->plugin_slug . '_options', array( $this, 'handle_option_updates' ), 10, 2 );
        add_filter( 'site_status_tests', array( $this, 'register_site_health_tests' ) );
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

    private function get_client_ip() {
        $keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

        foreach ( $keys as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $raw_value = wp_unslash( $_SERVER[ $key ] );
            $value     = is_array( $raw_value ) ? reset( $raw_value ) : $raw_value;

            if ( 'HTTP_X_FORWARDED_FOR' === $key ) {
                $parts = array_map( 'trim', explode( ',', (string) $value ) );
                $value = isset( $parts[0] ) ? $parts[0] : '';
            }

            $sanitized = sanitize_text_field( (string) $value );

            if ( filter_var( $sanitized, FILTER_VALIDATE_IP ) ) {
                return $sanitized;
            }
        }

        return '0.0.0.0';
    }

    private function get_rate_limit_cache_key( $username, $ip ) {
        return $this->plugin_slug . '_rl_' . md5( strtolower( (string) $username ) . '|' . $ip );
    }

    private function get_lockout_cache_key( $username, $ip ) {
        return $this->plugin_slug . '_lock_' . md5( strtolower( (string) $username ) . '|' . $ip );
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

    private function save_recovery_token( $token ) {
        $options = $this->get_controlled_options();
        $ttl     = max( 5, absint( $options['recovery_token_ttl'] ) );

        $options['recovery_token_hash']    = wp_hash( $token );
        $options['recovery_token_expires'] = time() + ( MINUTE_IN_SECONDS * $ttl );

        update_option( $this->plugin_slug . '_options', $options );
    }

    private function is_valid_recovery_token( $token ) {
        $options = $this->get_controlled_options();

        if ( empty( $options['enable_recovery_mode'] ) ) {
            return false;
        }

        if ( empty( $options['recovery_token_hash'] ) || empty( $options['recovery_token_expires'] ) ) {
            return false;
        }

        if ( time() > (int) $options['recovery_token_expires'] ) {
            return false;
        }

        return hash_equals( $options['recovery_token_hash'], wp_hash( $token ) );
    }

    private function consume_recovery_token() {
        $options = $this->get_controlled_options();

        unset( $options['recovery_token_hash'], $options['recovery_token_expires'] );
        update_option( $this->plugin_slug . '_options', $options );
    }

    public function handle_admin_actions() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = filter_input( INPUT_GET, 'timu_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $page   = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $nonce  = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        if ( $this->plugin_slug !== $page || empty( $action ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $nonce, 'timu_admin_actions' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'thisismyurl-login-support' ) );
        }

        if ( 'generate_recovery' === $action ) {
            $token = $this->generate_recovery_token();
            $this->save_recovery_token( $token );
            set_transient( $this->plugin_slug . '_recovery_token', $token, MINUTE_IN_SECONDS * 3 );
            $this->log_event( 'Recovery token generated', 'Admin generated one-time recovery token' );
        }

        if ( 'clear_logs' === $action ) {
            update_option( self::LOG_OPTION, array(), false );
            $this->log_event( 'Security logs cleared', 'Admin cleared all security logs' );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->plugin_slug ) );
        exit;
    }

    public function handle_force_logout() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $force_logout = filter_input( INPUT_GET, 'timu_force_logout', FILTER_SANITIZE_NUMBER_INT );
        $page         = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $nonce        = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        if ( '1' !== (string) $force_logout || $this->plugin_slug !== $page ) {
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
        $clear_logs_url      = wp_nonce_url(
            admin_url( 'options-general.php?page=' . $this->plugin_slug . '&timu_action=clear_logs' ),
            'timu_admin_actions'
        );
        $force_logout_url    = wp_nonce_url(
            admin_url( 'options-general.php?page=' . $this->plugin_slug . '&timu_force_logout=1' ),
            'timu_force_logout'
        );
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
        <a href="<?php echo esc_url( $force_logout_url ); ?>"
            class="button button-link-delete"
            onclick="return confirm('<?php echo esc_js( __( 'Immediately log out all other users?', 'thisismyurl-login-support' ) ); ?>');">
            <?php esc_html_e( 'Force Global Logout', 'thisismyurl-login-support' ); ?>
        </a>
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
                                            <th scope="row"><?php esc_html_e( 'Activate Stealth Mode', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-shifting" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_shifting]" value="1" <?php checked( 1, (int) $options['enable_shifting'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-shifting">
                                            <th scope="row"><?php esc_html_e( 'Secret Slug', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <input type="text" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[slug]" value="<?php echo esc_attr( $options['slug'] ); ?>" class="regular-text" />
                                                <p class="description"><?php esc_html_e( 'Example: secure-login. Do not use wp-admin or wp-login.php.', 'thisismyurl-login-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Enable Recovery Mode', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-recovery" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_recovery_mode]" value="1" <?php checked( 1, (int) $options['enable_recovery_mode'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Allow temporary one-time token access to recover login URL access.', 'thisismyurl-login-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-recovery">
                                            <th scope="row"><?php esc_html_e( 'Recovery Token Lifetime (minutes)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="5" max="180" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[recovery_token_ttl]" value="<?php echo esc_attr( (string) $options['recovery_token_ttl'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Enable Login Rate Limiting', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-rate-limit" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_rate_limit]" value="1" <?php checked( 1, (int) $options['enable_rate_limit'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-rate-limit">
                                            <th scope="row"><?php esc_html_e( 'Max Failed Attempts', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="20" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[rate_limit_attempts]" value="<?php echo esc_attr( (string) $options['rate_limit_attempts'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr class="timu-conditional-rate-limit">
                                            <th scope="row"><?php esc_html_e( 'Attempt Window (minutes)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="120" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[rate_limit_window]" value="<?php echo esc_attr( (string) $options['rate_limit_window'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr class="timu-conditional-rate-limit">
                                            <th scope="row"><?php esc_html_e( 'Lockout Duration (minutes)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="1440" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[lockout_minutes]" value="<?php echo esc_attr( (string) $options['lockout_minutes'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Enable Security Event Logging', 'thisismyurl-login-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-logging" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[enable_event_logging]" value="1" <?php checked( 1, (int) $options['enable_event_logging'] ); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-logging">
                                            <th scope="row"><?php esc_html_e( 'Log Retention (days)', 'thisismyurl-login-support' ); ?></th>
                                            <td><input type="number" min="1" max="365" name="<?php echo esc_attr( $this->plugin_slug ); ?>_options[log_retention_days]" value="<?php echo esc_attr( (string) $options['log_retention_days'] ); ?>" class="small-text" /></td>
                                        </tr>
                                        <?php $this->render_registration_field(); ?>
                                    </table>
                                </div>
                            </div>
                            <div class="timu-card">
                                <div class="timu-card-header"><?php esc_html_e( 'Security Event Log', 'thisismyurl-login-support' ); ?></div>
                                <div class="timu-card-body">
                                    <p>
                                        <a href="<?php echo esc_url( $clear_logs_url ); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Clear all security logs?', 'thisismyurl-login-support' ) ); ?>');"><?php esc_html_e( 'Clear Logs', 'thisismyurl-login-support' ); ?></a>
                                    </p>
                                    <table class="widefat striped">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Date', 'thisismyurl-login-support' ); ?></th>
                                                <th><?php esc_html_e( 'Event', 'thisismyurl-login-support' ); ?></th>
                                                <th><?php esc_html_e( 'User', 'thisismyurl-login-support' ); ?></th>
                                                <th><?php esc_html_e( 'IP', 'thisismyurl-login-support' ); ?></th>
                                                <th><?php esc_html_e( 'Details', 'thisismyurl-login-support' ); ?></th>
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
        $recovery_token = filter_input( INPUT_GET, 'timu_recovery_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        if ( ! empty( $recovery_token ) && $this->is_valid_recovery_token( $recovery_token ) ) {
            set_transient( $this->plugin_slug . '_recovery_bypass_' . md5( $this->get_client_ip() ), 1, MINUTE_IN_SECONDS * 10 );
            $this->consume_recovery_token();
            $this->log_event( 'Recovery token used', 'Temporary login bypass granted for current IP' );
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
            $bypass = get_transient( $this->plugin_slug . '_recovery_bypass_' . md5( $this->get_client_ip() ) );

            if ( $bypass ) {
                return;
            }

            wp_safe_redirect( home_url( '/' ) );
            exit;
        }
    }

    public function enforce_rate_limit( $user, $username, $password ) {
        $options = $this->get_controlled_options();

        if ( empty( $options['enable_rate_limit'] ) ) {
            return $user;
        }

        if ( is_wp_error( $user ) ) {
            return $user;
        }

        $ip       = $this->get_client_ip();
        $lock_key = $this->get_lockout_cache_key( $username, $ip );

        if ( get_transient( $lock_key ) ) {
            $this->log_event( 'Login blocked by rate limiter', 'User is temporarily locked out', (string) $username );

            return new WP_Error(
                'timu_rate_limit_lockout',
                esc_html__( 'Too many failed attempts. Please try again later.', 'thisismyurl-login-support' )
            );
        }

        return $user;
    }

    public function track_failed_login( $username ) {
        $options = $this->get_controlled_options();

        if ( empty( $options['enable_rate_limit'] ) ) {
            $this->log_event( 'Failed login', 'Login failed while rate limiting is disabled', (string) $username );
            return;
        }

        $ip           = $this->get_client_ip();
        $attempts_key = $this->get_rate_limit_cache_key( $username, $ip );
        $lock_key     = $this->get_lockout_cache_key( $username, $ip );
        $attempts     = absint( get_transient( $attempts_key ) );

        $attempts++;

        set_transient( $attempts_key, $attempts, MINUTE_IN_SECONDS * absint( $options['rate_limit_window'] ) );
        $this->log_event( 'Failed login', 'Failed attempt count: ' . $attempts, (string) $username );

        if ( $attempts >= absint( $options['rate_limit_attempts'] ) ) {
            set_transient( $lock_key, 1, MINUTE_IN_SECONDS * absint( $options['lockout_minutes'] ) );
            delete_transient( $attempts_key );
            $this->log_event( 'Rate limit lockout', 'Lockout triggered after repeated failed attempts', (string) $username );
        }
    }

    public function track_successful_login( $user_login, $user ) {
        $ip           = $this->get_client_ip();
        $attempts_key = $this->get_rate_limit_cache_key( $user_login, $ip );
        $lock_key     = $this->get_lockout_cache_key( $user_login, $ip );

        delete_transient( $attempts_key );
        delete_transient( $lock_key );
        $this->log_event( 'Successful login', 'Authentication succeeded', (string) $user_login );
    }

    public function handle_option_updates( $old_value, $new_value ) {
        $old_value = is_array( $old_value ) ? $old_value : array();
        $new_value = is_array( $new_value ) ? $new_value : array();

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