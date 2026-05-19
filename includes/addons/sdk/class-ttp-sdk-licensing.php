<?php
/**
 * SDK license and install timestamp Testing Items.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers license and install Danger Utilities.
 */
class TTP_SDK_Licensing {

	/**
	 * Register licensing Testing Items (license editor, then install editor).
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$this->register_license_item( $registry );
		$this->register_install_item( $registry );
	}

	/**
	 * Register the license data editor Danger Utility.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register_license_item( TTP_Item_Registry $registry ) {
		$install_licensing = __( 'Install & Licensing', 'themeisle-tester' );
		$shared_sdk        = __( 'Shared SDK', 'themeisle-tester' );

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
	}

	/**
	 * Register the install timestamp editor Danger Utility.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register_install_item( TTP_Item_Registry $registry ) {
		$install_licensing = __( 'Install & Licensing', 'themeisle-tester' );
		$shared_sdk        = __( 'Shared SDK', 'themeisle-tester' );

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
			$props            = get_object_vars( $current_data );
			$props['license'] = $status;
			$updated_data     = (object) $props;
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
