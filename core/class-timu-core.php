<?php
/**
 * TIMU Shared Core Library
 * Version: 1.0.6
 * Author: thisismyurl.com
 */

if ( ! class_exists( 'TIMU_Core_v1' ) ) {

    abstract class TIMU_Core_v1 {
        protected $plugin_slug;
        protected $plugin_url;
        protected $options_group;
        protected $license_message = '';

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
            if ( isset( $input['registration_key'] ) ) {
                $input['registration_key'] = sanitize_text_field( $input['registration_key'] );
            }
            return $input;
        }

        public function enqueue_core_assets( $hook ) {
            if ( strpos( $hook, $this->plugin_slug ) === false ) return;
            wp_enqueue_media();
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            wp_enqueue_style( 'timu-core-css', $this->plugin_url . 'core/assets/shared-admin.css', array(), '1.0.0' );
            wp_enqueue_script( 'timu-core-js', $this->plugin_url . 'core/assets/shared-admin.js', array( 'jquery', 'wp-color-picker' ), '1.0.0', true );
        }

        protected function render_registration_field() {
            $options = get_option( $this->plugin_slug . '_options' );
            $key     = $options['registration_key'] ?? '';
            $is_valid = $this->is_licensed();
            $color    = $is_valid ? '#46b450' : '#dc3232';
            ?>
            <div class="timu-registration-box" style="margin-top: 30px; padding: 20px; border: 1px solid #ccd0d4; background: #fff; border-left: 4px solid <?php echo $color; ?>;">
                <h3 style="margin-top:0;">Plugin Registration</h3>
                <p>Enter your key from <a href="https://thisismyurl.com" target="_blank">thisismyurl.com</a> to unlock developer support.</p>
                <input type="text" name="<?php echo esc_attr($this->plugin_slug); ?>_options[registration_key]" value="<?php echo esc_attr( $key ); ?>" class="regular-text" style="font-family: monospace;">
                <p style="color: <?php echo $color; ?>; font-weight: bold; margin-top: 10px; margin-bottom:0;"><?php echo esc_html( $this->license_message ); ?></p>
            </div>
            <?php
        }

        protected function render_core_header() {
            $icon = $this->plugin_url . 'assets/thisismyurl-login-support-for-wordpress-icon.png';
            ?>
            <div class="timu-header">
                <img src="<?php echo esc_url( $icon ); ?>" alt="TIMU Icon" style="height: 60px; width: auto; object-fit: contain; margin-right: 20px;">
                <h1><?php echo esc_html( get_admin_page_title() ); ?> <span class="agency-by" style="font-style: italic; color: #888; margin-left: 10px;">by thisismyurl.com</span></h1>
            </div>
            <?php
        }

        protected function render_core_sidebar( $extra_content = '' ) {
            $tools = $this->fetch_other_tools();
            $banner = $this->plugin_url . 'assets/thisismyurl-login-support-for-wordpress-banner.png';
            $is_licensed = $this->is_licensed();
            ?>
            <div id="postbox-container-1" class="postbox-container timu-marketing-sidebar" style="width: 280px; float: right; margin-left: 20px;">
                <div class="postbox">
                    <img src="<?php echo esc_url($banner); ?>" style="width:100%; height:auto; display:block;">
                    <div class="inside">
                        <h3>Support Status</h3>
                        <p><?php echo $is_licensed ? '✅ <strong>Registered</strong>' : '❌ <strong>Unregistered</strong>'; ?></p>
                        <p class="description" style="font-size:11px;"><?php echo esc_html($this->license_message); ?></p>
                        <?php echo $extra_content; ?>
                    </div>
                </div>
                <div class="postbox">
                    <h2 class="hndle"><span>Other Tools</span></h2>
                    <div class="inside">
                        <?php if ( !empty($tools) && !isset($tools['error']) ) : foreach ( array_slice($tools, 0, 5) as $tool ) : ?>
                            <div class="timu-tool-item" style="display: flex; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                                <img src="<?php echo esc_url($tool['icon']); ?>" style="width: 40px; height: 40px; border-radius: 4px; margin-right: 10px;">
                                <div>
                                    <h4 style="margin:0; font-size:13px;"><?php echo esc_html($tool['name']); ?></h4>
                                    <a href="<?php echo esc_url($tool['url']); ?>" target="_blank" style="font-size:11px;">Get &rarr;</a>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                        <p style="text-align:center;"><a href="<?php echo add_query_arg('timu_refresh_tools', '1'); ?>" style="font-size:10px; color:#999;">Refresh List</a></p>
                    </div>
                </div>
            </div>
            <?php
        }

        private function fetch_other_tools() {
            if ( isset($_GET['timu_refresh_tools']) ) delete_transient( 'timu_tools_cache' );
            $tools = get_transient( 'timu_tools_cache' );
            if ( $tools ) return $tools;
            $response = wp_remote_get( 'https://thisismyurl.com/wp-json/api/v1/plugins/', array( 'timeout' => 8 ) );
            if ( is_wp_error( $response ) ) return array( 'error' => true );
            $tools = json_decode( wp_remote_retrieve_body( $response ), true );
            set_transient( 'timu_tools_cache', $tools, 12 * HOUR_IN_SECONDS );
            return $tools;
        }

        protected function is_licensed() {
            $options = get_option( $this->plugin_slug . '_options' );
            $key = ! empty( $options['registration_key'] ) ? $options['registration_key'] : '';
            if ( empty( $key ) ) { $this->license_message = 'Key missing.'; return false; }
            $cached = get_transient( $this->plugin_slug . '_license_status' );
            $cached_msg = get_transient( $this->plugin_slug . '_license_msg' );
            if ( false !== $cached ) { $this->license_message = $cached_msg; return ($cached === 'valid'); }
            $url = add_query_arg( array('registration_key' => $key, 'site_url' => home_url()), 'https://thisismyurl.com/wp-json/license-manager/v1/check/' );
            $res = wp_remote_get( $url, array( 'timeout' => 15 ) );
            if ( is_wp_error( $res ) ) { $this->license_message = 'Server error.'; return false; }
            $data = json_decode( wp_remote_retrieve_body( $res ), true );
            $is_valid = ( isset( $data['status'] ) && $data['status'] === 'valid' );
            $msg = $data['message'] ?? ($is_valid ? 'Active' : 'Invalid');
            set_transient( $this->plugin_slug . '_license_status', $is_valid ? 'valid' : 'invalid', DAY_IN_SECONDS );
            set_transient( $this->plugin_slug . '_license_msg', $msg, DAY_IN_SECONDS );
            $this->license_message = $msg;
            return $is_valid;
        }

        protected function render_core_footer() {
            ?>
            <div class="clear"></div>
            <div class="timu-footer-links" style="margin-top: 50px; border-top: 1px solid #ddd; padding-top: 20px; color: #999; font-size: 11px;">
                &copy; <?php echo date('Y'); ?> <a href="https://thisismyurl.com/" target="_blank" style="color: #999;">thisismyurl.com</a>
            </div>
            <?php
        }
    }
}