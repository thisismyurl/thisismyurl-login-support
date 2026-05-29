<?php
/**
 * WP 7 Abilities API registration for This Is My URL - Login Support.
 *
 * Exposes the current login-lockout state as a discoverable, REST/AI-invokable
 * ability. Read-only by design: it reports which usernames and IPs are currently
 * locked out by the rate limiter, never reveals the secret login slug, and never
 * mutates security state. Clearing a lockout remains a deliberate human action
 * via WP-CLI (`wp login-support unlock`), not an agent-invokable operation.
 *
 * @package thisismyurl-login-support
 * @since   1.6147
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // Abilities API unavailable (WordPress < 6.9).
		}

		if ( ! method_exists( 'TIMU_Login_Support', 'get_active_lockouts' ) ) {
			return; // Plugin core not loaded; nothing to wrap.
		}

		wp_register_ability(
			'thisismyurl-login-support/get-lockout-status',
			array(
				'label'               => __( 'Get Login Lockout Status', 'thisismyurl-login-support' ),
				'description'         => __( 'Returns the login lockouts currently in force from the rate limiter — each locked username or IP, when the lockout expires, and how many seconds remain. Use this to diagnose why a legitimate user cannot sign in, or to audit brute-force activity. Read-only: it never clears a lockout and never exposes the secret login slug.', 'thisismyurl-login-support' ),
				'category'            => 'site',
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'count'    => array(
							'type'        => 'integer',
							'description' => __( 'The number of lockouts currently in force.', 'thisismyurl-login-support' ),
						),
						'lockouts' => array(
							'type'        => 'array',
							'description' => __( 'The active lockouts, each describing what is locked and for how long.', 'thisismyurl-login-support' ),
							'items'       => array(
								'type'                 => 'object',
								'properties'           => array(
									'type'         => array(
										'type'        => 'string',
										'enum'        => array( 'account', 'ip' ),
										'description' => __( 'Whether this lockout targets a user account or an IP address.', 'thisismyurl-login-support' ),
									),
									'identifier'   => array(
										'type'        => 'string',
										'description' => __( 'The locked username or IP address.', 'thisismyurl-login-support' ),
									),
									'locked_until' => array(
										'type'        => 'integer',
										'description' => __( 'Unix timestamp when the lockout expires.', 'thisismyurl-login-support' ),
									),
									'ttl_seconds'  => array(
										'type'        => 'integer',
										'description' => __( 'Seconds remaining until the lockout expires.', 'thisismyurl-login-support' ),
									),
								),
								'additionalProperties' => false,
							),
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input = array() ): array {
					$lockouts = TIMU_Login_Support::get_active_lockouts();

					return array(
						'count'    => count( $lockouts ),
						'lockouts' => $lockouts,
					);
				},
				'permission_callback' => static function ( $input = array() ): bool {
					// Security-admin operation: lockout state is sensitive
					// reconnaissance data, so never expose below manage_options.
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
);
