<?php
/**
 * Scenario state storage.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores per-site Scenario state.
 */
class TTP_Scenario_Store {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'ttp_scenario_state';

	/**
	 * Get all raw Scenario state.
	 *
	 * @return array<mixed>
	 */
	public function get_all() {
		$state = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		/**
		 * Filters all saved Themeisle Tester Scenario state.
		 *
		 * Product plugins can use this for read-only state adaptation before runtime application.
		 *
		 * @param array<mixed> $state Scenario state keyed by Scenario ID.
		 */
		return (array) apply_filters( 'ttp_scenario_state', $state );
	}

	/**
	 * Get one Scenario state row.
	 *
	 * @param string $scenario_id Scenario ID.
	 * @return array<string,mixed>
	 */
	public function get( $scenario_id ) {
		$state = $this->get_all();
		$row   = array();

		if ( isset( $state[ $scenario_id ] ) && is_array( $state[ $scenario_id ] ) ) {
			$row = $state[ $scenario_id ];
		}

		$row = wp_parse_args(
			$row,
			array(
				'enabled' => false,
				'params'  => array(),
			)
		);

		/**
		 * Filters saved state for one Themeisle Tester Scenario.
		 *
		 * Product plugins can use this for read-only state adaptation before runtime application.
		 *
		 * @param array<string,mixed> $row         Scenario state row.
		 * @param string              $scenario_id Scenario ID.
		 */
		return (array) apply_filters( "ttp_scenario_state_{$scenario_id}", $row, $scenario_id );
	}

	/**
	 * Save one Scenario state row.
	 *
	 * @param string              $scenario_id Scenario ID.
	 * @param bool                $enabled     Enabled flag.
	 * @param array<string,mixed> $params      Sanitized params.
	 * @return void
	 */
	public function save( $scenario_id, $enabled, $params ) {
		$state                 = $this->get_all_without_filters();
		$state[ $scenario_id ] = array(
			'enabled' => (bool) $enabled,
			'params'  => $params,
		);

		$this->persist( $state );
	}

	/**
	 * Reset one Scenario state row.
	 *
	 * @param string $scenario_id Scenario ID.
	 * @return void
	 */
	public function reset( $scenario_id ) {
		$state = $this->get_all_without_filters();

		unset( $state[ $scenario_id ] );

		$this->persist( $state );
	}

	/**
	 * Get raw option state without public filters.
	 *
	 * @return array<mixed>
	 */
	private function get_all_without_filters() {
		$state = get_option( self::OPTION_NAME, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist state with autoload disabled.
	 *
	 * @param array<mixed> $state State.
	 * @return void
	 */
	private function persist( $state ) {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, $state, '', false );
			return;
		}

		update_option( self::OPTION_NAME, $state, false );
	}
}
