<?php
/**
 * Runtime Scenario applicator.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies enabled Scenarios after registration closes.
 */
class TTP_Hook_Applicator {

	/**
	 * Item registry.
	 *
	 * @var TTP_Item_Registry
	 */
	private $registry;

	/**
	 * Scenario store.
	 *
	 * @var TTP_Scenario_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param TTP_Item_Registry  $registry Item registry.
	 * @param TTP_Scenario_Store $store    Scenario store.
	 */
	public function __construct( TTP_Item_Registry $registry, TTP_Scenario_Store $store ) {
		$this->registry = $registry;
		$this->store    = $store;
	}

	/**
	 * Apply enabled Scenarios.
	 *
	 * @return void
	 */
	public function apply() {
		if ( ! $this->is_runtime_enabled() ) {
			return;
		}

		foreach ( $this->registry->get_items() as $item ) {
			if ( 'scenario' !== $item['type'] || empty( $item['available'] ) || ! is_callable( $item['apply'] ) ) {
				continue;
			}

			$state = $this->store->get( $item['id'] );

			if ( empty( $state['enabled'] ) ) {
				continue;
			}

			/**
			 * Fires before an enabled Themeisle Tester Scenario is applied at runtime.
			 *
			 * Product plugins can observe Scenario application for debugging.
			 *
			 * @param array<string,mixed> $item  Normalized Scenario definition.
			 * @param array<string,mixed> $state Saved Scenario state.
			 */
			do_action( 'ttp_before_apply_scenario', $item, $state );

			call_user_func( $item['apply'], $item, $state );

			/**
			 * Fires after an enabled Themeisle Tester Scenario has been applied at runtime.
			 *
			 * Product plugins can observe Scenario application for debugging.
			 *
			 * @param array<string,mixed> $item  Normalized Scenario definition.
			 * @param array<string,mixed> $state Saved Scenario state.
			 */
			do_action( 'ttp_after_apply_scenario', $item, $state );
		}
	}

	/**
	 * Check runtime safety gate.
	 *
	 * @return bool
	 */
	public function is_runtime_enabled() {
		if ( defined( 'TTP_DISABLED' ) && TTP_DISABLED ) {
			return false;
		}

		$enabled = true;

		if ( function_exists( 'wp_get_environment_type' ) ) {
			$enabled = 'production' !== wp_get_environment_type();
		}

		/**
		 * Filters whether Themeisle Tester runtime behavior may execute.
		 *
		 * Environments can return true to allow Scenarios and Danger Utilities, or false to block them.
		 *
		 * @param bool $enabled Whether runtime behavior is enabled.
		 */
		return (bool) apply_filters( 'ttp_is_runtime_enabled', $enabled );
	}
}
