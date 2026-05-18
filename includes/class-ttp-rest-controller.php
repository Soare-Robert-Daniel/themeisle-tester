<?php
/**
 * REST API controller.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps Dashboard REST requests to PHP services.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE_NAME = 'ttp/v1';

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
	 * Constructor.
	 *
	 * @param TTP_Item_Registry       $registry         Registry.
	 * @param TTP_Scenario_Store      $scenario_store   Scenario store.
	 * @param TTP_Danger_Backup_Store $backup_store     Backup store.
	 * @param TTP_Schema_Sanitizer    $schema_sanitizer Schema sanitizer.
	 */
	public function __construct( TTP_Item_Registry $registry, TTP_Scenario_Store $scenario_store, TTP_Danger_Backup_Store $backup_store, TTP_Schema_Sanitizer $schema_sanitizer ) {
		$this->registry         = $registry;
		$this->scenario_store   = $scenario_store;
		$this->backup_store     = $backup_store;
		$this->schema_sanitizer = $schema_sanitizer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_NAME,
			'/items',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/scenarios/(?P<id>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_scenario' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/scenarios/(?P<id>[a-z0-9_-]+)/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_scenario' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/utilities/(?P<id>[a-z0-9_-]+)/inspect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'inspect_utility' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/utilities/(?P<id>[a-z0-9_-]+)/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_utility' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/danger/(?P<id>[a-z0-9_-]+)/inspect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'inspect_danger_utility' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/danger/(?P<id>[a-z0-9_-]+)/mutate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mutate_danger_utility' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/danger/(?P<id>[a-z0-9_-]+)/restore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restore_danger_utility' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Read an item ID from the request URL.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function request_item_id( WP_REST_Request $request ) {
		$id = $request['id'];

		return is_string( $id ) ? $id : '';
	}

	/**
	 * Return boot data.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		return rest_ensure_response(
			array(
				'items'          => $this->registry->get_items(),
				'scenarioState'  => $this->scenario_store->get_all(),
				'dangerBackups'  => $this->backup_store->get_all(),
				'restNamespace'  => self::NAMESPACE_NAME,
				'restNonce'      => wp_create_nonce( 'wp_rest' ),
				'runtimeEnabled' => $this->is_runtime_enabled(),
			)
		);
	}

	/**
	 * Save Scenario state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_scenario( WP_REST_Request $request ) {
		$item = $this->get_item_for_type( $this->request_item_id( $request ), 'scenario' );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$params = $request->get_param( 'params' );
		$params = is_array( $params ) ? $params : array();
		$params = $this->schema_sanitizer->sanitize_params( $item, $params );

		if ( is_wp_error( $params ) ) {
			return $params;
		}

		$item_id = $item['id'];
		$this->scenario_store->save( $item_id, (bool) $request->get_param( 'enabled' ), $params );

		return rest_ensure_response(
			array(
				'state' => $this->scenario_store->get( $item_id ),
			)
		);
	}

	/**
	 * Reset Scenario state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_scenario( WP_REST_Request $request ) {
		$item = $this->get_item_for_type( $this->request_item_id( $request ), 'scenario' );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$item_id = $item['id'];
		$this->scenario_store->reset( $item_id );

		return rest_ensure_response(
			array(
				'state' => $this->scenario_store->get( $item_id ),
			)
		);
	}

	/**
	 * Inspect Utility.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function inspect_utility( WP_REST_Request $request ) {
		$item = $this->get_item_for_type( $this->request_item_id( $request ), 'utility' );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		if ( ! is_callable( $item['inspect'] ) ) {
			return new WP_Error( 'ttp_utility_not_inspectable', __( 'This Utility does not provide inspector data.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		$result = call_user_func( $item['inspect'], $item, $this->get_payload( $request ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Run Utility.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_utility( WP_REST_Request $request ) {
		$item = $this->get_item_for_type( $this->request_item_id( $request ), 'utility' );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		if ( ! is_callable( $item['run'] ) ) {
			return new WP_Error( 'ttp_utility_not_runnable', __( 'This Utility does not provide an action.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		$payload = $this->get_payload( $request );

		/**
		 * Fires before a Themeisle Tester Utility is run.
		 *
		 * Product plugins can observe Utility execution for debugging.
		 *
		 * @param array<string,mixed> $item    Normalized Utility definition.
		 * @param array<string,mixed> $payload Request payload.
		 */
		do_action( 'ttp_before_run_utility', $item, $payload );

		$result = call_user_func( $item['run'], $item, $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a Themeisle Tester Utility has run.
		 *
		 * Product plugins can observe Utility execution results for debugging.
		 *
		 * @param array<string,mixed> $item    Normalized Utility definition.
		 * @param array<string,mixed> $payload Request payload.
		 * @param mixed               $result  Utility result.
		 */
		do_action( 'ttp_after_run_utility', $item, $payload, $result );

		return rest_ensure_response( $result );
	}

	/**
	 * Inspect Danger Utility.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function inspect_danger_utility( WP_REST_Request $request ) {
		$item = $this->get_item_for_type( $this->request_item_id( $request ), 'danger_utility' );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		if ( ! is_callable( $item['inspect'] ) ) {
			return new WP_Error( 'ttp_utility_not_inspectable', __( 'This Danger Utility does not provide inspector data.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		$result = call_user_func( $item['inspect'], $item, $this->get_payload( $request ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Mutate Danger Utility target.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mutate_danger_utility( WP_REST_Request $request ) {
		if ( ! $this->is_runtime_enabled() ) {
			return new WP_Error( 'ttp_runtime_disabled', __( 'Themeisle Tester runtime behavior is disabled.', 'themeisle-tester' ), array( 'status' => 403 ) );
		}

		$item = $this->get_item_for_type( $this->request_item_id( $request ), 'danger_utility' );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$target_param = $request->get_param( 'target' );
		$target       = is_string( $target_param ) ? sanitize_text_field( $target_param ) : '';

		if ( '' === $target ) {
			return new WP_Error( 'ttp_missing_target', __( 'Danger Utility mutation requires a target.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		$payload = $this->get_payload( $request );
		$params  = $this->schema_sanitizer->sanitize_params( $item, $payload );

		if ( is_wp_error( $params ) ) {
			return $params;
		}

		if ( ! is_callable( $item['inspect'] ) || ! is_callable( $item['mutate'] ) ) {
			return new WP_Error( 'ttp_utility_not_runnable', __( 'This Danger Utility is missing required callbacks.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		$inspect = call_user_func( $item['inspect'], $item, array( 'target' => $target ) );

		if ( is_wp_error( $inspect ) ) {
			return $inspect;
		}

		$item_id = $item['id'];

		if ( is_array( $inspect ) && array_key_exists( 'backup', $inspect ) ) {
			$this->backup_store->backup_once( $item_id, $target, $inspect['backup'] );
		}

		/**
		 * Fires before a Themeisle Tester Danger Utility mutates a target.
		 *
		 * Product plugins can observe controlled mutation for debugging.
		 *
		 * @param array<string,mixed> $item    Normalized Danger Utility definition.
		 * @param string              $target  Target identifier.
		 * @param array<string,mixed> $payload Sanitized payload.
		 */
		do_action( 'ttp_before_mutate_danger_utility', $item, $target, $params );

		$result = call_user_func( $item['mutate'], $item, $target, $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a Themeisle Tester Danger Utility mutates a target.
		 *
		 * Product plugins can observe controlled mutation results for debugging.
		 *
		 * @param array<string,mixed> $item    Normalized Danger Utility definition.
		 * @param string              $target  Target identifier.
		 * @param array<string,mixed> $payload Sanitized payload.
		 * @param mixed               $result  Mutation result.
		 */
		do_action( 'ttp_after_mutate_danger_utility', $item, $target, $params, $result );

		return rest_ensure_response( $result );
	}

	/**
	 * Restore Danger Utility target backup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_danger_utility( WP_REST_Request $request ) {
		$item = $this->get_item_for_type( $this->request_item_id( $request ), 'danger_utility' );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$target_param = $request->get_param( 'target' );
		$target       = is_string( $target_param ) ? sanitize_text_field( $target_param ) : '';

		if ( '' === $target ) {
			return new WP_Error( 'ttp_missing_target', __( 'Danger Utility restore requires a target.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		if ( ! is_callable( $item['restore'] ) ) {
			return new WP_Error( 'ttp_utility_not_runnable', __( 'This Danger Utility cannot restore backups.', 'themeisle-tester' ), array( 'status' => 400 ) );
		}

		$item_id = $item['id'];
		$backup  = $this->backup_store->get( $item_id, $target );

		if ( null === $backup ) {
			return new WP_Error( 'ttp_missing_backup', __( 'No backup exists for this target.', 'themeisle-tester' ), array( 'status' => 404 ) );
		}

		/**
		 * Fires before a Themeisle Tester Danger Utility restores a target.
		 *
		 * Product plugins can observe restore behavior for debugging.
		 *
		 * @param array<string,mixed> $item   Normalized Danger Utility definition.
		 * @param string              $target Target identifier.
		 */
		do_action( 'ttp_before_restore_danger_utility', $item, $target );

		$result = call_user_func( $item['restore'], $item, $target, $backup );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->backup_store->delete( $item_id, $target );

		/**
		 * Fires after a Themeisle Tester Danger Utility restores a target.
		 *
		 * Product plugins can observe restore results for debugging.
		 *
		 * @param array<string,mixed> $item   Normalized Danger Utility definition.
		 * @param string              $target Target identifier.
		 * @param mixed               $result Restore result.
		 */
		do_action( 'ttp_after_restore_danger_utility', $item, $target, $result );

		return rest_ensure_response( $result );
	}

	/**
	 * Get one item and verify type.
	 *
	 * @phpstan-return NormalizedItem|WP_Error
	 *
	 * @param string $id   Item ID.
	 * @param string $type Expected type.
	 * @return array<string,mixed>|WP_Error
	 */
	private function get_item_for_type( $id, $type ) {
		$item = $this->registry->get_item( sanitize_key( $id ) );

		if ( null === $item || $type !== $item['type'] ) {
			return new WP_Error( 'ttp_item_not_found', __( 'Testing Item not found.', 'themeisle-tester' ), array( 'status' => 404 ) );
		}

		if ( empty( $item['available'] ) ) {
			$reason = '' !== $item['unavailable_reason']
				? $item['unavailable_reason']
				: __( 'Testing Item is unavailable.', 'themeisle-tester' );

			return new WP_Error( 'ttp_item_unavailable', $reason, array( 'status' => 400 ) );
		}

		return $item;
	}

	/**
	 * Read request payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function get_payload( WP_REST_Request $request ) {
		$payload = $request->get_param( 'payload' );

		if ( is_array( $payload ) ) {
			$clean = array();

			foreach ( $payload as $key => $value ) {
				if ( is_string( $key ) ) {
					$clean[ $key ] = $value;
				}
			}

			return $clean;
		}

		$params = $request->get_params();
		unset( $params['id'] );

		return $params;
	}

	/**
	 * Check runtime safety gate.
	 *
	 * @return bool
	 */
	private function is_runtime_enabled() {
		$applicator = new TTP_Hook_Applicator( $this->registry, $this->scenario_store );

		return $applicator->is_runtime_enabled();
	}
}
