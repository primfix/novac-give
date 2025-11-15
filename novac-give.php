<?php
/**
 * Plugin Name: Novac for Give
 * Plugin URI: https://developer.novacpayment.com
 * Description: Accept donations via Novac (hosted checkout) in GiveWP.
 * Version: 1.0.0
 * Author: novac
 * Author URI: https://www.app.novacpayment.com
 * Developer: Novac Engineers
 * Developer URI: https://developer.novacpayment.com
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: novac-give
 * Requires Plugins: give
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) exit;

if ( !defined( 'GIVE_NOVAC_FILE' ) ) {
    define( 'GIVE_NOVAC_FILE', __FILE__ );
}

if ( !defined( 'GIVE_NOVAC_PATH' ) ) {
    define( 'GIVE_NOVAC_PATH', plugin_dir_path( __FILE__ ) );
}

if ( !defined( 'GIVE_NOVAC_URL' ) ) {
    define( 'GIVE_NOVAC_URL', plugin_dir_url( __FILE__ ) );
}

if ( !defined( 'GIVE_NOVAC_VER' ) ) {
    define( 'GIVE_NOVAC_VER', '1.0.0' );
}

if ( !defined( 'NOVAC_ALLOWED_WEBHOOK_IP_ADDRESS' ) ) {
    define( 'NOVAC_ALLOWED_WEBHOOK_IP_ADDRESS', '18.233.137.110');
}

/**
 * Register plugin settings link.
 *
 * @since 1.0.0
 *
 * @param array $links
 * @return array
 */
function register_settings_link( $links ) {
    $url = admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=novac' );
    $label = esc_html__( 'Settings', 'novac-give' );

    $settings_link = '<a href="' . esc_url( $url ) . '">' . $label . '</a>';
    array_unshift( $links, $settings_link );

    return $links;
}

/**
 * Remove Novac from the gateway list if it is disabled or currency is not supported.
 *
 * @since 3.0.2
 */
function filter_gateway( $gateways, $form_id ) {

    if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
        return $gateways;
    }

    // Sanitize request URI to satisfy WP coding standards.
    $request_uri = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
    $request_uri = wp_check_invalid_utf8( $request_uri );
//	$request_uri = sanitize_text_field( $request_uri );

    // Skip gateway filtering on create Give form donation page
    if ( false !== strpos( $request_uri, '/wp-admin/post-new.php?post_type=give_forms' ) ) {
        return $gateways;
    }

    if ( $form_id ) {
        $is_supported_currency = in_array( give_get_currency( $form_id ), [ 'NGN', 'GHS', 'USD', 'EUR', 'GBP' ] );
        $is_enabled = give_is_setting_enabled( give_get_meta( $form_id, 'novac_customize_novac_donations', true, 'global' ), [ 'enabled', 'global' ] );

        if ( ! $is_supported_currency || ! $is_enabled ) {
            unset( $gateways['novac'] );
        }
    }

    return $gateways;
}


/**
 * Register Novac as a payment method in GiveWP.
 *
 * @since 4.0.0
 */
function register_gateway( $registrar ) {
    $registrar->registerGateway( \GiveNovac\Give_Novac_Gateway::class );
}

add_action('plugins_loaded', function () {
    if (!class_exists('Give')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Novac for GiveWP</strong> requires GiveWP to be active.</p></div>';
        });
        return;
    }

    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'register_settings_link' );

    if (!class_exists('GiveNovac\Give_Novac_Gateway')) {
        require_once GIVE_NOVAC_PATH . 'includes/admin/settings.php';
        require_once GIVE_NOVAC_PATH . 'includes/class-novac-give-gateway.php';

        add_action( 'givewp_register_payment_gateway', 'register_gateway' );

        // Register gateway in GiveWP’s list.
        add_filter('give_payment_gateways', function ($gateways) {
            $gateways[\GiveNovac\Give_Novac_Gateway::id()] = [
                'admin_label'    => esc_html__('Novac', 'novac-give'),
                'checkout_label' => esc_html__('Novac', 'novac-give'),
            ];

            return $gateways;
        });

        add_action('give_enabled_payment_gateways', 'filter_gateway', 10, 2);
    }
});