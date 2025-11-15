<?php

if (!defined('ABSPATH')) exit;

/**
 * Register sections.
 *
 * @since 1.0.0
 *
 * @param array $sections
 * @return array
 */
function register_sections( $sections ) {
    $sections['novac'] = __( 'Novac', 'novac-give' );

    return $sections;
}

add_filter( 'give_get_sections_gateways', 'register_sections' );

add_filter( 'give_get_settings_gateways', function ( array $settings ) {

    $section = give_get_current_setting_section();

    if ( $section !== 'novac' ) {
        return $settings;
    }

    $section = [
        [
            'id'   => 'give_novac_settings',
            'name' => esc_html__( 'Novac', 'novac-give' ),
            'desc' => esc_html__( 'Configure Novac API credentials and behavior.', 'novac-give' ),
            'type' => 'title',
        ],
        [
            'name' => esc_html__( 'Public Key', 'novac-give' ),
            'id'   => 'give_novac_public_key',
            'type' => 'text',
        ],
        [
            'name' => esc_html__( 'Secret Key', 'novac-give' ),
            'id'   => 'give_novac_secret_key',
            'type' => 'text',
        ],
        [
            'name'    => esc_html__( 'Mode', 'novac-give' ),
            'id'      => 'give_novac_mode',
            'type'    => 'select',
            'options' => [
                'test' => esc_html__( 'Test', 'novac-give' ),
                'live' => esc_html__( 'Live', 'novac-give' ),
            ],
            'default' => 'test',
        ],
        [
            'name' => esc_html__( 'Webhook Secret (optional)', 'novac-give' ),
            'id'   => 'give_novac_webhook_secret',
            'type' => 'text',
            'desc' => esc_html__( 'If Novac signs webhooks, put the secret here to verify (HMAC).', 'novac-give' ),
        ],
        [
            'id'   => 'give_novac_settings_end',
            'type' => 'sectionend',
        ],
    ];

    return array_merge( $settings, $section );
} );