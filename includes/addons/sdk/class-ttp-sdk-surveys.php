<?php
/**
 * SDK Formbricks survey Testing Items.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers survey Scenarios and Utilities.
 */
class TTP_SDK_Surveys {

	/**
	 * Register survey Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$promos_surveys = __( 'Black Friday & Surveys', 'themeisle-tester' );
		$shared_sdk     = __( 'Shared SDK', 'themeisle-tester' );
		$group_surveys  = __( 'Surveys', 'themeisle-tester' );

		$registry->register(
			array(
				'id'          => 'survey_data_override',
				'type'        => 'scenario',
				'categories'  => array( $promos_surveys ),
				'group'       => $group_surveys,
				'product'     => $shared_sdk,
				'label'       => __( 'Override Formbricks survey data', 'themeisle-tester' ),
				'description' => __( 'Hooks themeisle-sdk/survey/{product_slug} to override environment ID, plan, or install days for a Themeisle product.', 'themeisle-tester' ),
				'width'       => 'full',
				'fields'      => array(
					array(
						'id'    => 'product_slug',
						'type'  => 'text',
						'label' => __( 'Product slug (e.g. otter-blocks, neve)', 'themeisle-tester' ),
					),
					array(
						'id'    => 'environment_id',
						'type'  => 'text',
						'label' => __( 'Formbricks environment ID (blank to leave as is)', 'themeisle-tester' ),
					),
					array(
						'id'      => 'plan',
						'type'    => 'select',
						'label'   => __( 'Plan attribute (blank to leave as is)', 'themeisle-tester' ),
						'options' => array( 'free', 'personal', 'pro', 'business', 'agency' ),
					),
					array(
						'id'    => 'install_days',
						'type'  => 'text',
						'label' => __( 'Install days override (blank to leave as is, number to override)', 'themeisle-tester' ),
					),
					array(
						'id'    => 'user_id',
						'type'  => 'text',
						'label' => __( 'User ID override (blank to leave as is)', 'themeisle-tester' ),
					),
					array(
						'id'    => 'random_user_id',
						'type'  => 'toggle',
						'label' => __( 'Generate a random user ID on every survey load (overrides the field above)', 'themeisle-tester' ),
					),
				),
				'apply'       => array( $this, 'apply_survey_override' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'survey_data_inspect',
				'type'        => 'utility',
				'categories'  => array( $promos_surveys ),
				'group'       => $group_surveys,
				'product'     => $shared_sdk,
				'label'       => __( 'Inspect survey data', 'themeisle-tester' ),
				'description' => __( 'Runs themeisle-sdk/survey/{product_slug} for known Themeisle products and shows what Formbricks would receive.', 'themeisle-tester' ),
				'width'       => 'full',
				'inspect'     => array( $this, 'inspect_survey_data' ),
			)
		);
	}

	/**
	 * Apply Formbricks survey override.
	 *
	 * Hooks the themeisle-sdk/survey/{slug} filter and selectively overrides
	 * environment ID, plan, and install_days_number attributes.
	 *
	 * @param array<string,mixed> $item  Item definition.
	 * @param array<string,mixed> $state Scenario state.
	 * @return void
	 */
	public function apply_survey_override( $item, $state ) {
		$params = isset( $state['params'] ) && is_array( $state['params'] ) ? $state['params'] : array();
		$slug   = isset( $params['product_slug'] ) && is_string( $params['product_slug'] ) ? sanitize_key( $params['product_slug'] ) : '';

		if ( '' === $slug ) {
			return;
		}

		$environment_id = isset( $params['environment_id'] ) && is_string( $params['environment_id'] ) ? sanitize_text_field( $params['environment_id'] ) : '';
		$plan           = isset( $params['plan'] ) && is_string( $params['plan'] ) ? sanitize_text_field( $params['plan'] ) : '';
		$install_days   = isset( $params['install_days'] ) && is_string( $params['install_days'] ) ? sanitize_text_field( $params['install_days'] ) : '';
		$user_id        = isset( $params['user_id'] ) && is_string( $params['user_id'] ) ? sanitize_text_field( $params['user_id'] ) : '';
		$random_user_id = ! empty( $params['random_user_id'] );

		add_filter(
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Targets the SDK-defined hook name.
			'themeisle-sdk/survey/' . $slug,
			function ( $data, $page_slug ) use ( $environment_id, $plan, $install_days, $user_id, $random_user_id ) {
				unset( $page_slug );

				if ( ! is_array( $data ) ) {
					$data = array();
				}

				if ( ! isset( $data['attributes'] ) || ! is_array( $data['attributes'] ) ) {
					$data['attributes'] = array();
				}

				if ( '' !== $environment_id ) {
					$data['environmentId'] = $environment_id;
				}

				if ( '' !== $plan ) {
					$data['attributes']['plan'] = $plan;
				}

				if ( '' !== $install_days && is_numeric( $install_days ) ) {
					$data['attributes']['install_days_number'] = (int) $install_days;
				}

				if ( $random_user_id ) {
					$data['userId'] = 'u_' . wp_generate_password( 8, false );
				} elseif ( '' !== $user_id ) {
					$data['userId'] = $user_id;
				}

				return $data;
			},
			9999,
			2
		);
	}

	/**
	 * Inspect survey data for known Themeisle product slugs.
	 *
	 * Calls apply_filters( 'themeisle-sdk/survey/{slug}', [], '' ) for a curated
	 * list of products and returns each result as a flat key/value pair.
	 *
	 * @return array<string,mixed>
	 */
	public function inspect_survey_data() {
		$known_products = array(
			'otter-blocks',
			'neve',
			'optimole-wp',
			'rop',
			'wp-hyve-lite',
			'feedzy-rss-feeds',
			'translatepress-multilingual',
		);

		$result = array(
			'_note' => __( 'Blank entries mean the product is inactive or has no survey handler registered.', 'themeisle-tester' ),
		);

		foreach ( $known_products as $slug ) {
			/** This filter is documented in themeisle-sdk-main/src/Modules/Script_loader.php. */
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Targets the SDK-defined hook name.
			$data = apply_filters( 'themeisle-sdk/survey/' . $slug, array(), '' );

			if ( ! is_array( $data ) || empty( $data ) ) {
				$result[ $slug ] = '—';
				continue;
			}

			$encoded         = wp_json_encode( $data );
			$result[ $slug ] = is_string( $encoded ) ? $encoded : '';
		}

		return $result;
	}
}
