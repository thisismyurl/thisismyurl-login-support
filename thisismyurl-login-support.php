<?php
/**
 * Plugin Name: Login Support by thisismyurl.com
 * ... rest of header ...
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path( __FILE__ ) . 'core/class-timu-core.php';

class TIMU_Login_Support extends TIMU_Core_v1 {

    public function __construct() {
        parent::__construct( 'thisismyurl-login-support', plugin_dir_url( __FILE__ ), 'timu_login_group' );
        
        $options = get_option( $this->plugin_slug . '_options', array() );

        // 1. Force Logout Logic
        add_action( 'admin_init', array( $this, 'handle_force_logout' ) );

        // 2. Plugin Shifting & Branding logic
        if ( ! empty( $options['enable_shifting'] ) ) {
            add_action( 'setup_theme', array( $this, 'handle_login_shifting' ), 1 );
            add_filter( 'site_url', array( $this, 'rewrite_login_urls' ), 10, 4 );
        }

        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }

    public function handle_force_logout() {
        if ( isset( $_GET['timu_force_logout'] ) && $_GET['timu_force_logout'] === 'true' ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            $users = get_users();
            foreach ( $users as $user ) { wp_destroy_other_sessions( $user->ID ); }
            add_action( 'admin_notices', function() {
                echo '<div class="updated notice is-dismissible"><p>All other user sessions terminated.</p></div>';
            });
        }
    }

    public function add_menu() {
        add_options_page( 'Login Support', 'Login Support', 'manage_options', $this->plugin_slug, array( $this, 'render_ui' ) );
    }

    public function render_ui() {
        $options = get_option( $this->plugin_slug . '_options' );
        
        // Prepare specific content for the sidebar box
        ob_start();
        ?>
        <hr><p><strong>Security Utilities:</strong></p>
        <a href="<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '&timu_force_logout=true'); ?>" 
           class="button button-link-delete" style="color:#dc3232;" 
           onclick="return confirm('Immediately log out all other users?');">Force Global Logout</a>
        <?php
        $sidebar_extra = ob_get_clean();

        ?>
        <div class="wrap timu-admin-wrap">
            <?php $this->render_core_header(); ?>
            <form method="post" action="options.php">
                <?php settings_fields( $this->options_group ); ?>
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <div class="timu-card">
                                <div class="timu-card-header">Configuration</div>
                                <div class="timu-card-body">
                                    <table class="form-table">
                                        <tr>
                                            <th>Activate Stealth Mode</th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" class="timu-toggle-trigger" data-target=".timu-conditional-shifting" name="<?php echo $this->plugin_slug; ?>_options[enable_shifting]" value="1" <?php checked(1, @$options['enable_shifting']); ?>>
                                                    <span class="timu-slider"></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="timu-conditional-shifting">
                                            <th>Secret Slug</th>
                                            <td><input type="text" name="<?php echo $this->plugin_slug; ?>_options[slug]" value="<?php echo esc_attr(@$options['slug']); ?>" class="regular-text" /></td>
                                        </tr>
                                        <?php $this->render_registration_field(); ?>
                                    </table>
                                </div>
                            </div>
                            <?php submit_button('Save Settings', 'primary large'); ?>
                        </div>
                        <?php $this->render_core_sidebar( $sidebar_extra ); ?>
                    </div>
                </div>
            </form>
            <?php $this->render_core_footer(); ?>
        </div>
        <?php
    }

    // (handle_login_shifting, rewrite_login_urls, etc. logic remains here)
}
new TIMU_Login_Support();

/**
 * Handle Plugin Updates via GitHub.
 */
add_action( 'plugins_loaded', function() {
    $updater_path = plugin_dir_path( __FILE__ ) . 'updater.php';
    if ( file_exists( $updater_path ) ) {
        require_once $updater_path;
        if ( class_exists( 'FWO_GitHub_Updater' ) ) {
            new FWO_GitHub_Updater( array(
                'slug'               => 'thisismyurl-login-support',
                'proper_folder_name' => 'thisismyurl-login-support',
                'api_url'            => 'https://api.github.com/repos/thisismyurl/thisismyurl-login-support/releases/latest',
                'github_url'         => 'https://github.com/thisismyurl/thisismyurl-login-support',
                'plugin_file'        => __FILE__,
            ) );
        }
    }
} );