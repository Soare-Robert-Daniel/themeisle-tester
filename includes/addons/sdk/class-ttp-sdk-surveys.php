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
 * Registers the Formbricks survey override Scenario.
 */
class TTP_SDK_Surveys {

	/**
	 * Register survey Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$sdk_category  = __( 'SDK', 'themeisle-tester' );
		$shared_sdk    = __( 'Shared SDK', 'themeisle-tester' );
		$group_surveys = __( 'Surveys', 'themeisle-tester' );

		$registry->register(
			array(
				'id'          => 'survey_data_override',
				'type'        => 'scenario',
				'categories'  => array( $sdk_category ),
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
}
