<?php
/**
 * Shared Dashboard action handlers.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes Dashboard mutations for admin POST and REST.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Dashboard_Actions {

	/**
	 * Registry.
	 *
	 * @var TTP_Item_Registry
	 */
	private $registry;

	/**
	 * Scenario store.
	 *
	 * @var TTP_Scenario_Store
	 */
	private $scenario_store;

	/**
	 * Backup store.
	 *
	 * @var TTP_Danger_Backup_Store
	 */
	private $backup_store;

	/**
	 * Schema sanitizer.
	 *
	 * @var TTP_Schema_Sanitizer
	 */
	private $schema_sanitizer;

	/**
	 * Activity store.
	 *
	 * @var TTP_Activity_Store
	 */
	private $activity_store;

	/**
	 * Constructor.
	 *
	 * @param TTP_Item_Registry       $registry         Registry.
	 * @param TTP_Scenario_Store      $scenario_store   Scenario store.
	 * @param TTP_Danger_Backup_Store $backup_store     Backup store.
	 * @param TTP_Schema_Sanitizer    $schema_sanitizer Schema sanitizer.
	 * @param TTP_Activity_Store      $activity_store   Activity store.
	 */
	public function __construct( TTP_Item_Registry $registry, TTP_Scenario_Store $scenario_store, TTP_Danger_Backup_Store $backup_store, TTP_Schema_Sanitizer $schema_sanitizer, TTP_Activity_Store $activity_store ) {
		$this->registry         = $registry;
		$this->scenario_store   = $scenario_store;
		$this->backup_store     = $backup_store;
		$this->schema_sanitizer = $schema_sanitizer;
		$this->activity_store   = $activity_store;
	}

	/**
	 * Save Scenario state.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item    Item.
	 * @param bool                $enabled Enabled flag.
	 * @param array<string,mixed> $params  Raw params (sanitized here).
	 * @return array<string,mixed>|WP_Error
	 */
	public function save_scenario( $item, $enabled, $params ) {
		$availability = $this->availability_error( $item );

		if ( is_wp_error( $availability ) ) {
			return $this->record_and_return( $item, __( 'Save Scenario', 'themeisle-tester' ), $availability, array() );
		}

		$params = $this->schema_sanitizer->sanitize_params( $item, $params );

		if ( is_wp_error( $params ) ) {
			return $this->record_and_return(
				$item,
				__( 'Save Scenario', 'themeisle-tester' ),
				$params,
				array()
			);
		}

		$this->scenario_store->save( $item['id'], $enabled, $params );

		return $this->record_and_return(
			$item,
			__( 'Save Scenario', 'themeisle-tester' ),
			array(
				'message' => __( 'Scenario saved.', 'themeisle-tester' ),
				'details' => array_merge(
					array(
						sprintf(
							/* translators: %s: enabled/disabled status. */
							__( 'State: %s', 'themeisle-tester' ),
							$enabled ? __( 'Enabled', 'themeisle-tester' ) : __( 'Disabled', 'themeisle-tester' )
						),
					),
					$this->params_details( $item, $params )
				),
			),
			array()
		);
	}

	/**
	 * Reset Scenario state.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return array<string,mixed>|WP_Error
	 */
	public function reset_scenario( $item ) {
		$this->scenario_store->reset( $item['id'] );

		return $this->record_and_return(
			$item,
			__( 'Reset Scenario', 'themeisle-tester' ),
			array(
				'message' => __( 'Scenario reset.', 'themeisle-tester' ),
			),
			array()
		);
	}

	/**
	 * Run Utility.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item    Item.
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_utility( $item, $payload ) {
		$availability = $this->availability_error( $item );

		if ( is_wp_error( $availability ) ) {
			return $this->record_and_return( $item, __( 'Run Utility', 'themeisle-tester' ), $availability, array() );
		}

		if ( ! is_callable( $item['run'] ) ) {
			return $this->record_and_return( $item, __( 'Run Utility', 'themeisle-tester' ), new WP_Error( 'ttp_utility_not_runnable', __( 'This Utility does not provide an action.', 'themeisle-tester' ), array( 'status' => 400 ) ), array() );
		}

		/**
		 * Fires before a Themeisle Tester Utility is run.
		 *
		 * @param array<string,mixed> $item    Normalized Utility definition.
		 * @param array<string,mixed> $payload Request payload.
		 */
		do_action( 'ttp_before_run_utility', $item, $payload );

		$result = call_user_func( $item['run'], $item, $payload );

		if ( ! is_wp_error( $result ) ) {
			/**
			 * Fires after a Themeisle Tester Utility has run.
			 *
			 * @param array<string,mixed> $item    Normalized Utility definition.
			 * @param array<string,mixed> $payload Request payload.
			 * @param mixed               $result  Utility result.
			 */
			do_action( 'ttp_after_run_utility', $item, $payload, $result );
		}

		return $this->record_and_return( $item, __( 'Run Utility', 'themeisle-tester' ), $this->with_action_details( $result, array() ), array() );
	}

	/**
	 * Mutate Danger Utility target.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item    Item.
	 * @param string              $target  Target identifier.
	 * @param array<string,mixed> $params  Raw params (sanitized here).
	 * @return array<string,mixed>|WP_Error
	 */
	public function mutate_danger( $item, $target, $params ) {
		$availability = $this->availability_error( $item );

		if ( is_wp_error( $availability ) ) {
			return $this->record_and_return( $item, __( 'Mutate Danger Utility', 'themeisle-tester' ), $availability, $this->target_details( $target ) );
		}

		$applicator = new TTP_Hook_Applicator( $this->registry, $this->scenario_store );

		if ( ! $applicator->is_runtime_enabled() ) {
			return $this->record_and_return( $item, __( 'Mutate Danger Utility', 'themeisle-tester' ), new WP_Error( 'ttp_runtime_disabled', __( 'Themeisle Tester runtime behavior is disabled.', 'themeisle-tester' ), array( 'status' => 403 ) ), array() );
		}

		if ( '' === $target ) {
			return $this->record_and_return( $item, __( 'Mutate Danger Utility', 'themeisle-tester' ), new WP_Error( 'ttp_missing_target', __( 'Danger Utility mutation requires a target.', 'themeisle-tester' ), array( 'status' => 400 ) ), array() );
		}

		$params = $this->schema_sanitizer->sanitize_params( $item, $params );

		if ( is_wp_error( $params ) ) {
			return $this->record_and_return( $item, __( 'Mutate Danger Utility', 'themeisle-tester' ), $params, $this->target_details( $target ) );
		}

		if ( ! is_callable( $item['inspect'] ) || ! is_callable( $item['mutate'] ) ) {
			return $this->record_and_return( $item, __( 'Mutate Danger Utility', 'themeisle-tester' ), new WP_Error( 'ttp_utility_not_runnable', __( 'This Danger Utility is missing required callbacks.', 'themeisle-tester' ), array( 'status' => 400 ) ), $this->target_details( $target ) );
		}

		$inspect = call_user_func( $item['inspect'], $item, array( 'target' => $target ) );

		if ( is_wp_error( $inspect ) ) {
			return $this->record_and_return( $item, __( 'Mutate Danger Utility', 'themeisle-tester' ), $inspect, $this->target_details( $target ) );
		}

		$item_id = $item['id'];

		if ( is_array( $inspect ) && array_key_exists( 'backup', $inspect ) ) {
			$this->backup_store->backup_once( $item_id, $target, $inspect['backup'] );
		}

		/**
		 * Fires before a Themeisle Tester Danger Utility mutates a target.
		 *
		 * @param array<string,mixed> $item   Normalized Danger Utility definition.
		 * @param string              $target Target identifier.
		 * @param array<string,mixed> $params Sanitized params.
		 */
		do_action( 'ttp_before_mutate_danger_utility', $item, $target, $params );

		$result = call_user_func( $item['mutate'], $item, $target, $params );

		if ( ! is_wp_error( $result ) ) {
			/**
			 * Fires after a Themeisle Tester Danger Utility mutates a target.
			 *
			 * @param array<string,mixed> $item   Normalized Danger Utility definition.
			 * @param string              $target Target identifier.
			 * @param array<string,mixed> $params Sanitized params.
			 * @param mixed               $result Mutation result.
			 */
			do_action( 'ttp_after_mutate_danger_utility', $item, $target, $params, $result );
		}

		$details = array_merge(
			$this->target_details( $target ),
			$this->params_details( $item, $params )
		);

		return $this->record_and_return( $item, __( 'Mutate Danger Utility', 'themeisle-tester' ), $this->with_action_details( $result, $details ), $details );
	}

	/**
	 * Restore Danger Utility target backup.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item.
	 * @param string              $target Target identifier.
	 * @return array<string,mixed>|WP_Error
	 */
	public function restore_danger( $item, $target ) {
		$availability = $this->availability_error( $item );

		if ( is_wp_error( $availability ) ) {
			return $this->record_and_return( $item, __( 'Restore Danger Utility', 'themeisle-tester' ), $availability, $this->target_details( $target ) );
		}

		if ( '' === $target ) {
			return $this->record_and_return( $item, __( 'Restore Danger Utility', 'themeisle-tester' ), new WP_Error( 'ttp_missing_target', __( 'Danger Utility restore requires a target.', 'themeisle-tester' ), array( 'status' => 400 ) ), array() );
		}

		if ( ! is_callable( $item['restore'] ) ) {
			return $this->record_and_return( $item, __( 'Restore Danger Utility', 'themeisle-tester' ), new WP_Error( 'ttp_utility_not_runnable', __( 'This Danger Utility cannot restore backups.', 'themeisle-tester' ), array( 'status' => 400 ) ), $this->target_details( $target ) );
		}

		$item_id = $item['id'];
		$backup  = $this->backup_store->get( $item_id, $target );

		if ( null === $backup ) {
			return $this->record_and_return( $item, __( 'Restore Danger Utility', 'themeisle-tester' ), new WP_Error( 'ttp_missing_backup', __( 'No backup exists for this target.', 'themeisle-tester' ), array( 'status' => 404 ) ), $this->target_details( $target ) );
		}

		/**
		 * Fires before a Themeisle Tester Danger Utility restores a target.
		 *
		 * @param array<string,mixed> $item   Normalized Danger Utility definition.
		 * @param string              $target Target identifier.
		 */
		do_action( 'ttp_before_restore_danger_utility', $item, $target );

		$result = call_user_func( $item['restore'], $item, $target, $backup );

		if ( is_wp_error( $result ) ) {
			return $this->record_and_return( $item, __( 'Restore Danger Utility', 'themeisle-tester' ), $result, $this->target_details( $target ) );
		}

		$this->backup_store->delete( $item_id, $target );

		/**
		 * Fires after a Themeisle Tester Danger Utility restores a target.
		 *
		 * @param array<string,mixed> $item   Normalized Danger Utility definition.
		 * @param string              $target Target identifier.
		 * @param mixed               $result Restore result.
		 */
		do_action( 'ttp_after_restore_danger_utility', $item, $target, $result );

		$details = $this->target_details( $target );

		return $this->record_and_return( $item, __( 'Restore Danger Utility', 'themeisle-tester' ), $this->with_action_details( $result, $details ), $details );
	}

	/**
	 * Add standard toast details to array results.
	 *
	 * @param mixed             $result  Action result.
	 * @param array<int,string> $details Details to append.
	 * @return array<string,mixed>|WP_Error
	 */
	private function with_action_details( $result, $details ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! is_array( $result ) ) {
			$result = array( 'message' => __( 'Action completed.', 'themeisle-tester' ) );
		}

		$existing = isset( $result['details'] ) && is_array( $result['details'] ) ? $result['details'] : array();

		$result['details'] = array_values(
			array_filter(
				array_merge( $details, $this->string_list( $existing ) ),
				'is_string'
			)
		);

		return $result;
	}

	/**
	 * Record activity and return the original action result.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed>          $item    Item.
	 * @param string                       $action  Action label.
	 * @param array<string,mixed>|WP_Error $result  Action result.
	 * @param array<int,string>            $details Fallback details.
	 * @return array<string,mixed>|WP_Error
	 */
	private function record_and_return( $item, $action, $result, $details ) {
		$is_error = is_wp_error( $result );
		$message  = $is_error
			? $result->get_error_message()
			: ( isset( $result['message'] ) && is_string( $result['message'] ) ? $result['message'] : __( 'Action completed.', 'themeisle-tester' ) );

		$result_details = ! $is_error && isset( $result['details'] ) && is_array( $result['details'] )
			? $this->string_list( $result['details'] )
			: $details;

		$this->activity_store->add(
			array(
				'time'       => current_time( 'mysql' ),
				'created_at' => time(),
				'item'       => $item['label'],
				'action'     => $action,
				'result'     => $is_error ? 'error' : 'success',
				'message'    => $message,
				'details'    => $result_details,
			)
		);

		return $result;
	}

	/**
	 * Build target details for Danger Utility actions.
	 *
	 * @param string $target Target identifier.
	 * @return array<int,string>
	 */
	private function target_details( $target ) {
		return array(
			sprintf(
				/* translators: %s: target identifier. */
				__( 'Target: %s', 'themeisle-tester' ),
				$target
			),
		);
	}

	/**
	 * Build details from sanitized params.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item.
	 * @param array<string,mixed> $params Sanitized params.
	 * @return array<int,string>
	 */
	private function params_details( $item, $params ) {
		$details = array();

		foreach ( $item['fields'] as $field ) {
			if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				continue;
			}

			$id = sanitize_key( $field['id'] );

			if ( ! array_key_exists( $id, $params ) ) {
				continue;
			}

			$label     = isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $id;
			$value     = $this->format_detail_value( $params[ $id ] );
			$details[] = sprintf(
				/* translators: 1: field label, 2: changed value. */
				__( '%1$s: %2$s', 'themeisle-tester' ),
				$label,
				$value
			);
		}

		return $details;
	}

	/**
	 * Format a changed value for toast details.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function format_detail_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'themeisle-tester' ) : __( 'No', 'themeisle-tester' );
		}

		if ( is_array( $value ) ) {
			$strings = $this->string_list( $value );

			if ( empty( $strings ) ) {
				return __( 'None', 'themeisle-tester' );
			}

			return implode( ', ', $strings );
		}

		if ( is_scalar( $value ) ) {
			$string = (string) $value;

			return '' !== $string ? $string : __( 'Empty', 'themeisle-tester' );
		}

		return __( 'Updated', 'themeisle-tester' );
	}

	/**
	 * Keep only scalar detail values as strings.
	 *
	 * @param array<mixed> $values Values.
	 * @return array<int,string>
	 */
	private function string_list( $values ) {
		$strings = array();

		foreach ( $values as $value ) {
			if ( is_scalar( $value ) ) {
				$strings[] = (string) $value;
			}
		}

		return $strings;
	}

	/**
	 * Return an error when a Testing Item is marked unavailable.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return WP_Error|null
	 */
	private function availability_error( $item ) {
		if ( ! empty( $item['available'] ) ) {
			return null;
		}

		$reason = '' !== $item['unavailable_reason']
			? $item['unavailable_reason']
			: __( 'Testing Item is unavailable.', 'themeisle-tester' );

		return new WP_Error( 'ttp_item_unavailable', $reason, array( 'status' => 400 ) );
	}
}
