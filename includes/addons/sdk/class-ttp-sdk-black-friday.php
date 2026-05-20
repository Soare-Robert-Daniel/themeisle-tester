<?php
/**
 * SDK Black Friday Testing Items.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Black Friday Scenarios and Utilities.
 */
class TTP_SDK_Black_Friday {

	/**
	 * Register Black Friday Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$sdk_category = __( 'SDK', 'themeisle-tester' );
		$shared_sdk   = __( 'Shared SDK', 'themeisle-tester' );
		$group_bf     = __( 'Black Friday', 'themeisle-tester' );

		$bf_dates = $this->compute_black_friday_dates();

		$registry->register(
			array(
				'id'          => 'sdk_current_date',
				'type'        => 'scenario',
				'categories'  => array( $sdk_category ),
				'group'       => $group_bf,
				'product'     => $shared_sdk,
				'label'       => __( 'Override SDK current date', 'themeisle-tester' ),
				'description' => __( 'Overrides the date returned by themeisle_sdk_current_date — useful to fast-forward into the sale window.', 'themeisle-tester' ),
				'fields'      => array(
					array(
						'id'      => 'date',
						'type'    => 'date',
						'label'   => __( 'Override date', 'themeisle-tester' ),
						'presets' => array(
							array(
								'label' => sprintf(
									/* translators: %s: year (e.g. 2026). */
									__( 'Sale start (%s)', 'themeisle-tester' ),
									$bf_dates['year']
								),
								'value' => $bf_dates['sale_start'],
							),
							array(
								'label' => sprintf(
									/* translators: %s: year (e.g. 2026). */
									__( 'Black Friday (%s)', 'themeisle-tester' ),
									$bf_dates['year']
								),
								'value' => $bf_dates['black_friday'],
							),
							array(
								'label' => sprintf(
									/* translators: %s: year (e.g. 2026). */
									__( 'Sale end (%s)', 'themeisle-tester' ),
									$bf_dates['year']
								),
								'value' => $bf_dates['sale_end'],
							),
						),
					),
				),
				'apply'       => array( $this, 'apply_current_date' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'sdk_blackfriday_domain',
				'type'        => 'scenario',
				'categories'  => array( $sdk_category ),
				'group'       => $group_bf,
				'product'     => $shared_sdk,
				'label'       => __( 'Swap Black Friday sale URL domains', 'themeisle-tester' ),
				'description' => __( 'Replaces sale URL hosts in themeisle_sdk_blackfriday_data.', 'themeisle-tester' ),
				'fields'      => array(
					array(
						'id'    => 'domain',
						'type'  => 'text',
						'label' => __( 'Replacement domain', 'themeisle-tester' ),
					),
				),
				'apply'       => array( $this, 'apply_black_friday_domain' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'clear_black_friday_dismissal',
				'type'        => 'utility',
				'categories'  => array( $sdk_category ),
				'group'       => $group_bf,
				'product'     => $shared_sdk,
				'label'       => __( 'Clear Black Friday dismissed notice', 'themeisle-tester' ),
				'description' => __( 'Clears the current user dismissal for the Black Friday notice.', 'themeisle-tester' ),
				'run'         => array( $this, 'run_clear_black_friday_dismissal' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'black_friday_dates',
				'type'        => 'utility',
				'categories'  => array( $sdk_category ),
				'group'       => $group_bf,
				'product'     => $shared_sdk,
				'label'       => __( 'Black Friday quick dates', 'themeisle-tester' ),
				'description' => __( 'Shows sale start, Black Friday, and sale end dates for the current year.', 'themeisle-tester' ),
				'inspect'     => array( $this, 'inspect_black_friday_dates' ),
			)
		);
	}

	/**
	 * Apply current date override.
	 *
	 * @param array<string,mixed> $item  Item definition.
	 * @param array<string,mixed> $state Scenario state.
	 * @return void
	 */
	public function apply_current_date( $item, $state ) {
		$params = isset( $state['params'] ) && is_array( $state['params'] ) ? $state['params'] : array();
		$date   = isset( $params['date'] ) && is_string( $params['date'] ) ? $params['date'] : '';

		if ( '' === $date ) {
			return;
		}

		add_filter(
			'themeisle_sdk_current_date',
			function () use ( $date ) {
				try {
					return new DateTime( $date . ' 00:00:00' );
				} catch ( Exception $exception ) {
					return new DateTime( 'now' );
				}
			},
			999
		);
	}

	/**
	 * Apply Black Friday domain replacement.
	 *
	 * @param array<string,mixed> $item  Item definition.
	 * @param array<string,mixed> $state Scenario state.
	 * @return void
	 */
	public function apply_black_friday_domain( $item, $state ) {
		$params = isset( $state['params'] ) && is_array( $state['params'] ) ? $state['params'] : array();
		$domain = isset( $params['domain'] ) && is_string( $params['domain'] ) ? sanitize_text_field( $params['domain'] ) : '';

		if ( '' === $domain ) {
			return;
		}

		add_filter(
			'themeisle_sdk_blackfriday_data',
			function ( $configs ) use ( $domain ) {
				if ( ! is_array( $configs ) ) {
					return $configs;
				}

				foreach ( $configs as $key => $config ) {
					if ( ! is_array( $config ) || empty( $config['sale_url'] ) || ! is_string( $config['sale_url'] ) ) {
						continue;
					}

					$sale_url   = $config['sale_url'];
					$parsed_url = wp_parse_url( $sale_url );

					if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) ) {
						continue;
					}

					$config['sale_url'] = esc_url_raw( str_replace( $parsed_url['host'], $domain, $sale_url ) );
					$configs[ $key ]    = $config;
				}

				return $configs;
			},
			9999
		);
	}

	/**
	 * Clear Black Friday notice dismissal for current user.
	 *
	 * @return array<string,mixed>
	 */
	public function run_clear_black_friday_dismissal() {
		delete_user_meta( get_current_user_id(), 'themeisle_sdk_dismissed_notice_black_friday' );

		return array(
			'message' => __( 'Black Friday notice dismissal cleared for the current user.', 'themeisle-tester' ),
		);
	}

	/**
	 * Inspect Black Friday quick dates.
	 *
	 * @return array{year:string,sale_start:string,black_friday:string,sale_end:string}
	 */
	public function inspect_black_friday_dates() {
		return $this->compute_black_friday_dates();
	}

	/**
	 * Compute the sale window dates that the SDK Black Friday module uses.
	 *
	 * Mirrors the math in ThemeisleSDK\Modules\Black_Friday: Black Friday is
	 * the last Friday of November, sale_start is the Monday of that week,
	 * sale_end is seven days later.
	 *
	 * @return array{year:string,sale_start:string,black_friday:string,sale_end:string}
	 */
	private function compute_black_friday_dates() {
		$now          = new DateTime( 'now' );
		$current_year = $now->format( 'Y' );
		$black_friday = new DateTime( 'last Friday of November ' . $current_year );
		$sale_start   = clone $black_friday;
		$sale_start->modify( 'monday this week' );
		$sale_start->setTime( 0, 0, 0 );
		$sale_end = clone $sale_start;
		$sale_end->modify( '+7 days' );
		$sale_end->setTime( 23, 59, 59 );

		return array(
			'year'         => $current_year,
			'sale_start'   => $sale_start->format( 'Y-m-d' ),
			'black_friday' => $black_friday->format( 'Y-m-d' ),
			'sale_end'     => $sale_end->format( 'Y-m-d' ),
		);
	}
}
