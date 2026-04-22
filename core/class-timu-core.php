<?php
/**
 * TIMU Shared Core Library
 * Version: 1.6112
 * Author: thisismyurl.com
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TIMU_Core_v1' ) ) {

    abstract class TIMU_Core_v1 {
        protected $plugin_slug;
        protected $plugin_url;
        protected $options_group;

        public function __construct( $slug, $url, $group ) {
            $this->plugin_slug   = $slug;
            $this->plugin_url    = $url;
            $this->options_group = $group;

            add_action( 'admin_init', array( $this, 'register_core_settings' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_core_assets' ) );
        }

        public function register_core_settings() {
            register_setting( 
                $this->options_group, 
                $this->plugin_slug . '_options',
                array(
                    'type'              => 'array',
                    'sanitize_callback' => array( $this, 'sanitize_core_options' )
                )
            );
        }

        public function sanitize_core_options( $input ) {
            delete_transient( $this->plugin_slug . '_license_status' );
            delete_transient( $this->plugin_slug . '_license_msg' );

            $input = is_array( $input ) ? $input : array();

            if ( isset( $input['registration_key'] ) ) {
                $input['registration_key'] = sanitize_text_field( $input['registration_key'] );
            }

            if ( isset( $input['enable_shifting'] ) ) {
                $input['enable_shifting'] = empty( $input['enable_shifting'] ) ? 0 : 1;
            }

            if ( isset( $input['slug'] ) ) {
                $input['slug'] = sanitize_title( $input['slug'] );
            }

            return $input;
        }

        public function enqueue_core_assets( $hook ) {
            if ( strpos( $hook, $this->plugin_slug ) === false ) {
                return;
            }

            $version = defined( 'TIMU_LOGIN_SUPPORT_VERSION' ) ? TIMU_LOGIN_SUPPORT_VERSION : '1.6112';

            wp_enqueue_media();
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_style( 'timu-core-css', $this->plugin_url . 'core/assets/shared-admin.css', array(), $version );
            wp_enqueue_script( 'timu-core-js', $this->plugin_url . 'core/assets/shared-admin.js', array( 'jquery', 'wp-color-picker' ), $version, true );
        }

        protected function render_registration_field() {
            ?>
            <tr>
                <th scope="row"><?php esc_html_e( 'Local-First Promise', 'thisismyurl-login-support' ); ?></th>
                <td>
                    <div class="timu-registration-box is-valid">
                        <p>
                            <?php esc_html_e( 'This plugin is designed to work on your WordPress site without any required cloud account, AI service, license gate, or paywall.', 'thisismyurl-login-support' ); ?>
                        </p>
                        <p class="description"><?php esc_html_e( 'Security controls, recovery tools, logs, and settings stay under the site owner\'s control.', 'thisismyurl-login-support' ); ?></p>
                    </div>
                </td>
            </tr>
            <?php
        }

        protected function render_core_header() {
            $icon = $this->plugin_url . 'assets/thisismyurl-login-support-for-wordpress-icon.png';
            ?>
            <div class="timu-header">
                <img src="<?php echo esc_url( $icon ); ?>" alt="<?php esc_attr_e( 'thisismyurl icon', 'thisismyurl-login-support' ); ?>" class="timu-header-icon">
                <h1><?php echo esc_html( get_admin_page_title() ); ?> <span class="agency-by">by thisismyurl.com</span></h1>
            </div>
            <?php
        }

        protected function render_core_sidebar( $extra_content = '' ) {
            $banner = $this->plugin_url . 'assets/thisismyurl-login-support-for-wordpress-banner.png';
            ?>
            <div id="postbox-container-1" class="postbox-container timu-marketing-sidebar">
                <div class="postbox">
                    <img src="<?php echo esc_url( $banner ); ?>" alt="<?php esc_attr_e( 'thisismyurl banner', 'thisismyurl-login-support' ); ?>" class="timu-banner">
                    <div class="inside">
                        <h3><?php esc_html_e( 'Plugin Values', 'thisismyurl-login-support' ); ?></h3>
                        <p><?php esc_html_e( 'Local-first, no AI required, plain-English controls, and safe recovery paths.', 'thisismyurl-login-support' ); ?></p>
                        <p class="description timu-small-text"><?php esc_html_e( 'Use the settings on this page to control how login access works for your site.', 'thisismyurl-login-support' ); ?></p>
                        <?php echo wp_kses_post( $extra_content ); ?>
                    </div>
                </div>
                <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e( 'Safety Checklist', 'thisismyurl-login-support' ); ?></span></h2>
                    <div class="inside">
                        <ul class="timu-checklist">
                            <li><?php esc_html_e( 'Generate a recovery link before changing the secret slug.', 'thisismyurl-login-support' ); ?></li>
                            <li><?php esc_html_e( 'Test login changes in a private browser window.', 'thisismyurl-login-support' ); ?></li>
                            <li><?php esc_html_e( 'Review the security event log after policy changes.', 'thisismyurl-login-support' ); ?></li>
                            <li><?php esc_html_e( 'Review Site Health after major security changes.', 'thisismyurl-login-support' ); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php
        }

        protected function render_core_footer() {
            ?>
            <div class="clear"></div>
            <div class="timu-footer-links">
                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <a href="https://thisismyurl.com/" target="_blank" rel="noopener noreferrer">thisismyurl.com</a>
            </div>
            <?php
        }
    }
}