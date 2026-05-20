<?php
/**
 * Classic Dashboard form POST handler.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles nonce-verified admin form posts when Datastar is unavailable.
 */
class TTP_Admin_Form_Handler {

	/**
	 * Registry.
	 *
	 * @var TTP_Item_Registry
	 */
	private $registry;

	/**
	 * Shared Dashboard actions.
	 *
	 * @var TTP_Dashboard_Actions
	 */
	private $dashboard_actions;

	/**
	 * Last admin action result.
	 *
	 * @var array<string,mixed>|WP_Error|null
	 */
	private $action_result = null;

	/**
	 * Constructor.
	 *
	 * @param TTP_Item_Registry     $registry          Registry.
	 * @param TTP_Dashboard_Actions $dashboard_actions Shared actions.
	 */
	public function __construct( TTP_Item_Registry $registry, TTP_Dashboard_Actions $dashboard_actions ) {
		$this->registry          = $registry;
		$this->dashboard_actions = $dashboard_actions;
	}

	/**
	 * Handle PHP-only admin form posts.
	 *
	 * @return void
	 */
	public function handle_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || empty( $_POST['ttp_action'] ) ) {
			return;
		}

		check_admin_referer( 'ttp_admin_action', 'ttp_nonce' );

		$action  = is_string( $_POST['ttp_action'] )
			? sanitize_key( wp_unslash( $_POST['ttp_action'] ) )
			: '';
		$item_id = isset( $_POST['ttp_item_id'] ) && is_string( $_POST['ttp_item_id'] )
			? sanitize_key( wp_unslash( $_POST['ttp_item_id'] ) )
			: '';
		$item    = $this->registry->get_item( $item_id );

		if ( null === $item ) {
			$this->action_result = new WP_Error( 'ttp_item_not_found', __( 'Testing Item not found.', 'themeisle-tester' ) );
			return;
		}

		if ( empty( $item['available'] ) ) {
			$reason              = '' !== $item['unavailable_reason']
				? $item['unavailable_reason']
				: __( 'Testing Item is unavailable.', 'themeisle-tester' );
			$this->action_result = new WP_Error( 'ttp_item_unavailable', $reason );
			return;
		}

		if ( 'save_scenario' === $action ) {
			$enabled             = ! empty( $_POST['ttp_enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
			$this->action_result = $this->dashboard_actions->save_scenario( $item, $enabled, $this->get_post_payload() );
			return;
		}

		if ( 'toggle_scenario' === $action ) {
			$enabled             = ! empty( $_POST['ttp_enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
			$this->action_result = $this->dashboard_actions->set_scenario_enabled( $item, $enabled );
			return;
		}

		if ( 'reset_scenario' === $action ) {
			$this->action_result = $this->dashboard_actions->reset_scenario( $item );
			return;
		}

		if ( 'run_utility' === $action ) {
			$this->action_result = $this->dashboard_actions->run_utility( $item, $this->get_post_payload() );
			return;
		}

		if ( 'mutate_danger' === $action ) {
			$target              = isset( $_POST['ttp_target'] ) && is_string( $_POST['ttp_target'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
				? sanitize_text_field( wp_unslash( $_POST['ttp_target'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
				: '';
			$this->action_result = $this->dashboard_actions->mutate_danger( $item, $target, $this->get_post_payload() );
			return;
		}

		if ( 'restore_danger' === $action ) {
			$target              = isset( $_POST['ttp_target'] ) && is_string( $_POST['ttp_target'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
				? sanitize_text_field( wp_unslash( $_POST['ttp_target'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
				: '';
			$this->action_result = $this->dashboard_actions->restore_danger( $item, $target );
		}
	}

	/**
	 * Last form action result for flash rendering.
	 *
	 * @return array<string,mixed>|WP_Error|null
	 */
	public function get_action_result() {
		return $this->action_result;
	}

	/**
	 * Read posted params.
	 *
	 * Uses sanitize_textarea_field so multi-line fields (url_list, etc.) keep
	 * their newlines. Single-line inputs are unaffected because browsers do
	 * not submit newlines through them.
	 *
	 * @return array<string,mixed>
	 */
	private function get_post_payload() {
		$clean = array();

		if ( ! empty( $_POST['ttp_params'] ) && is_array( $_POST['ttp_params'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
			$raw = map_deep( wp_unslash( $_POST['ttp_params'] ), 'sanitize_textarea_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().

			if ( is_array( $raw ) ) {
				foreach ( $raw as $key => $value ) {
					if ( is_string( $key ) ) {
						$clean[ $key ] = $value;
					}
				}
			}
		}

		foreach ( array( 'ttp_product_index', 'ttp_total' ) as $key ) {
			if ( ! isset( $_POST[ $key ] ) || ! is_numeric( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
				continue;
			}

			$clean[ $key ] = absint( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
		}

		if ( isset( $_POST['ttp_batch'] ) && is_scalar( $_POST['ttp_batch'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
			$clean['ttp_batch'] = sanitize_text_field( wp_unslash( (string) $_POST['ttp_batch'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
		}

		return $clean;
	}
}
