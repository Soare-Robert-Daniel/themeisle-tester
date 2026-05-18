<?php
/**
 * Bundled Themeisle Tester items.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers bundled shared SDK Scenarios and Utilities.
 */
class TTP_Bundled_Items {

	/**
	 * Register bundled Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$promos_surveys    = __( 'Black Friday & Surveys', 'themeisle-tester' );
		$install_licensing = __( 'Install & Licensing', 'themeisle-tester' );
		$shared_sdk        = __( 'Shared SDK', 'themeisle-tester' );
		$group_bf          = __( 'Black Friday', 'themeisle-tester' );
		$group_surveys     = __( 'Surveys', 'themeisle-tester' );

		$registry->register(
			array(
				'id'          => 'sdk_current_date',
				'type'        => 'scenario',
				'categories'  => array( $promos_surveys ),
				'group'       => $group_bf,
				'product'     => $shared_sdk,
				'label'       => __( 'Override SDK current date', 'themeisle-tester' ),
				'description' => __( 'Overrides the date returned by themeisle_sdk_current_date — useful to fast-forward into the sale window.', 'themeisle-tester' ),
				'fields'      => array(
					array(
						'id'    => 'date',
						'type'  => 'date',
						'label' => __( 'Override date', 'themeisle-tester' ),
					),
				),
				'apply'       => array( $this, 'apply_current_date' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'sdk_blackfriday_domain',
				'type'        => 'scenario',
				'categories'  => array( $promos_surveys ),
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
				'categories'  => array( $promos_surveys ),
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
				'categories'  => array( $promos_surveys ),
				'group'       => $group_bf,
				'product'     => $shared_sdk,
				'label'       => __( 'Black Friday quick dates', 'themeisle-tester' ),
				'description' => __( 'Shows sale start, Black Friday, and sale end dates for the current year.', 'themeisle-tester' ),
				'inspect'     => array( $this, 'inspect_black_friday_dates' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'license_data_editor',
				'type'        => 'danger_utility',
				'categories'  => array( $install_licensing ),
				'product'     => $shared_sdk,
				'label'       => __( 'License data scanner/editor', 'themeisle-tester' ),
				'description' => __( 'Scans and changes *_license_data option license statuses with backup support.', 'themeisle-tester' ),
				'fields'      => array(
					array(
						'id'      => 'status',
						'type'    => 'select',
						'label'   => __( 'License status', 'themeisle-tester' ),
						'options' => array( 'valid', 'expired', 'active-expired', 'invalid' ),
					),
				),
				'inspect'     => array( $this, 'inspect_license_data' ),
				'mutate'      => array( $this, 'mutate_license_data' ),
				'restore'     => array( $this, 'restore_option_backup' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'survey_data_override',
				'type'        => 'scenario',
				'categories'  => array( $promos_surveys ),
				'group'       => $group_surveys,
				'product'     => $shared_sdk,
				'label'       => __( 'Override Formbricks survey data', 'themeisle-tester' ),
				'description' => __( 'Hooks themeisle-sdk/survey/{product_slug} to override environment ID, plan, or install days for a Themeisle product.', 'themeisle-tester' ),
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
				'inspect'     => array( $this, 'inspect_survey_data' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'install_timestamp_editor',
				'type'        => 'danger_utility',
				'categories'  => array( $install_licensing ),
				'product'     => $shared_sdk,
				'label'       => __( 'Install timestamp scanner/editor', 'themeisle-tester' ),
				'description' => __( 'Scans and changes *_install timestamp options with backup support.', 'themeisle-tester' ),
				'fields'      => array(
					array(
						'id'    => 'date',
						'type'  => 'date',
						'label' => __( 'Install date', 'themeisle-tester' ),
					),
				),
				'inspect'     => array( $this, 'inspect_install_timestamps' ),
				'mutate'      => array( $this, 'mutate_install_timestamp' ),
				'restore'     => array( $this, 'restore_option_backup' ),
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
	 * @return array<string,mixed>
	 */
	public function inspect_black_friday_dates() {
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

	/**
	 * Inspect license data options.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>
	 */
	public function inspect_license_data( $item, $payload ) {
		$target = isset( $payload['target'] ) && is_string( $payload['target'] ) ? sanitize_text_field( $payload['target'] ) : '';

		if ( '' !== $target ) {
			$value = get_option( $target );

			return array(
				'target' => $target,
				'backup' => array(
					'exists' => $this->option_exists( $target ),
					'value'  => $value,
				),
				'row'    => $this->format_license_row( $target, $value ),
			);
		}

		return array(
			'rows' => $this->get_license_rows(),
		);
	}

	/**
	 * Mutate a license data option.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param string              $target  Target option name.
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function mutate_license_data( $item, $target, $payload ) {
		$status         = isset( $payload['status'] ) && is_string( $payload['status'] ) ? sanitize_text_field( $payload['status'] ) : '';
		$valid_statuses = array( 'valid', 'expired', 'active-expired', 'invalid' );

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return new WP_Error( 'ttp_invalid_license_status', __( 'Invalid license status.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		$current_data = get_option( $target );

		if ( ! is_object( $current_data ) && ! is_array( $current_data ) ) {
			return new WP_Error( 'ttp_invalid_license_data', __( 'Target option does not contain editable license data.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		if ( is_array( $current_data ) ) {
			$current_data['license'] = $status;
			$updated_data            = $current_data;
		} else {
			$object_data = clone $current_data;
			// @phpstan-ignore property.notFound
			$object_data->license = $status;
			$updated_data         = $object_data;
		}

		update_option( $target, $updated_data );
		$this->update_license_transient( $target, $updated_data );

		return array(
			'message' => __( 'License data updated.', 'themeisle-tester' ),
			'row'     => $this->format_license_row( $target, $updated_data ),
		);
	}

	/**
	 * Inspect install timestamp options.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>
	 */
	public function inspect_install_timestamps( $item, $payload ) {
		$target = isset( $payload['target'] ) && is_string( $payload['target'] ) ? sanitize_text_field( $payload['target'] ) : '';

		if ( '' !== $target ) {
			$value = get_option( $target );

			return array(
				'target' => $target,
				'backup' => array(
					'exists' => $this->option_exists( $target ),
					'value'  => $value,
				),
				'row'    => $this->format_install_row( $target, $value ),
			);
		}

		return array(
			'reference_date' => $this->get_reference_date()->format( 'Y-m-d H:i:s' ),
			'rows'           => $this->get_install_rows(),
		);
	}

	/**
	 * Mutate an install timestamp option.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param string              $target  Target option name.
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function mutate_install_timestamp( $item, $target, $payload ) {
		$date = isset( $payload['date'] ) && is_string( $payload['date'] ) ? sanitize_text_field( $payload['date'] ) : '';

		if ( '' === $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'ttp_invalid_install_date', __( 'Install date must use YYYY-MM-DD format.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		$timestamp = strtotime( $date . ' 00:00:00' );

		if ( false === $timestamp ) {
			return new WP_Error( 'ttp_invalid_install_date', __( 'Install date could not be parsed.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		update_option( $target, $timestamp );

		return array(
			'message' => __( 'Install timestamp updated.', 'themeisle-tester' ),
			'row'     => $this->format_install_row( $target, $timestamp ),
		);
	}

	/**
	 * Restore an option backup.
	 *
	 * @param array<string,mixed> $item   Item definition.
	 * @param string              $target Target option name.
	 * @param mixed               $backup Backup payload.
	 * @return array<string,mixed>
	 */
	public function restore_option_backup( $item, $target, $backup ) {
		if ( is_array( $backup ) ) {
			if ( empty( $backup['exists'] ) ) {
				delete_option( $target );
			} elseif ( array_key_exists( 'value', $backup ) ) {
				update_option( $target, $backup['value'] );
			}
		}

		return array(
			'message' => __( 'Backup restored.', 'themeisle-tester' ),
			'target'  => $target,
		);
	}

	/**
	 * Get license option rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_license_rows() {
		global $wpdb;

		$like          = '%' . $wpdb->esc_like( '_license_data' );
		$transient_not = '%' . $wpdb->esc_like( '_transient_' ) . '%';
		$query         = $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s ORDER BY option_name ASC",
			$like,
			$transient_not
		);
		$option_names  = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows          = array();

		foreach ( $option_names as $option_name ) {
			$rows[] = $this->format_license_row( $option_name, get_option( $option_name ) );
		}

		return $rows;
	}

	/**
	 * Format one license row.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 * @return array<string,mixed>
	 */
	private function format_license_row( $option_name, $value ) {
		$status = 'unknown';
		$key    = '';

		if ( is_object( $value ) ) {
			$raw_status = isset( $value->license ) ? $value->license : null; // phpcs:ignore
			$raw_key    = isset( $value->key ) ? $value->key : null; // phpcs:ignore
			$status     = is_scalar( $raw_status ) ? (string) $raw_status : 'unknown';
			$key        = is_scalar( $raw_key ) ? (string) $raw_key : '';
		} elseif ( is_array( $value ) ) {
			$raw_status = isset( $value['license'] ) ? $value['license'] : null;
			$raw_key    = isset( $value['key'] ) ? $value['key'] : null;
			$status     = is_scalar( $raw_status ) ? (string) $raw_status : 'unknown';
			$key        = is_scalar( $raw_key ) ? (string) $raw_key : '';
		}

		return array(
			'target'      => $option_name,
			'status'      => $status,
			'key_display' => $this->redact_key( $key ),
		);
	}

	/**
	 * Get install option rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_install_rows() {
		global $wpdb;

		$like          = '%' . $wpdb->esc_like( '_install' );
		$transient_not = '%' . $wpdb->esc_like( '_transient_' ) . '%';
		$query         = $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s ORDER BY option_name ASC",
			$like,
			$transient_not
		);
		$option_names  = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows          = array();

		foreach ( $option_names as $option_name ) {
			$rows[] = $this->format_install_row( $option_name, get_option( $option_name ) );
		}

		return $rows;
	}

	/**
	 * Format one install row.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 * @return array<string,mixed>
	 */
	private function format_install_row( $option_name, $value ) {
		$timestamp = is_numeric( $value ) ? (int) $value : 0;
		$date      = $timestamp > 0 ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
		$date_only = $timestamp > 0 ? gmdate( 'Y-m-d', $timestamp ) : '';
		$age       = '';

		if ( $timestamp > 0 ) {
			$age = $this->format_time_diff( $this->get_reference_date()->getTimestamp() - $timestamp );
		}

		return array(
			'target'    => $option_name,
			'timestamp' => $timestamp,
			'date'      => $date,
			'date_only' => $date_only,
			'age'       => $age,
		);
	}

	/**
	 * Update related transient data.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $data        Updated license data.
	 * @return void
	 */
	private function update_license_transient( $option_name, $data ) {
		$timeout = get_option( '_transient_timeout_' . $option_name );
		$ttl     = HOUR_IN_SECONDS;

		if ( $timeout && is_numeric( $timeout ) ) {
			$remaining = (int) $timeout - time();

			if ( $remaining > 0 ) {
				$ttl = $remaining;
			}
		}

		set_transient( $option_name, $data, $ttl );
	}

	/**
	 * Check whether an option exists.
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	private function option_exists( $option_name ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$option_name
		);

		return null !== $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get the SDK reference date.
	 *
	 * @return DateTime
	 */
	private function get_reference_date() {
		$date = apply_filters( 'themeisle_sdk_current_date', new DateTime( 'now' ) );

		return $date instanceof DateTime ? $date : new DateTime( 'now' );
	}

	/**
	 * Redact a license key for display.
	 *
	 * @param string $key License key.
	 * @return string
	 */
	private function redact_key( $key ) {
		if ( '' === $key ) {
			return __( 'N/A', 'themeisle-tester' );
		}

		if ( strlen( $key ) <= 12 ) {
			return str_repeat( '*', strlen( $key ) );
		}

		return substr( $key, 0, 6 ) . '...' . substr( $key, -6 );
	}

	/**
	 * Format a second difference.
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	private function format_time_diff( $seconds ) {
		$seconds = absint( $seconds );

		if ( $seconds < MINUTE_IN_SECONDS ) {
			return sprintf(
				/* translators: %s: number of seconds. */
				_n( '%s second', '%s seconds', $seconds, 'themeisle-tester' ),
				number_format_i18n( $seconds )
			);
		}

		$minutes = (int) floor( $seconds / MINUTE_IN_SECONDS );

		if ( $minutes < 60 ) {
			return sprintf(
				/* translators: %s: number of minutes. */
				_n( '%s minute', '%s minutes', $minutes, 'themeisle-tester' ),
				number_format_i18n( $minutes )
			);
		}

		$hours = (int) floor( $seconds / HOUR_IN_SECONDS );

		if ( $hours < 24 ) {
			return sprintf(
				/* translators: %s: number of hours. */
				_n( '%s hour', '%s hours', $hours, 'themeisle-tester' ),
				number_format_i18n( $hours )
			);
		}

		$days = (int) floor( $seconds / DAY_IN_SECONDS );

		return sprintf(
			/* translators: %s: number of days. */
			_n( '%s day', '%s days', $days, 'themeisle-tester' ),
			number_format_i18n( $days )
		);
	}
}
