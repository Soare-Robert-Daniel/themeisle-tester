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
	 * Shared Dashboard actions.
	 *
	 * @var TTP_Dashboard_Actions
	 */
	private $dashboard_actions;

	/**
	 * HTML fragment renderer.
	 *
	 * @var TTP_Dashboard_Renderer
	 */
	private $dashboard_renderer;

	/**
	 * HTML negotiation for Datastar morph responses.
	 *
	 * @var TTP_REST_Html
	 */
	private $rest_html;

	/**
	 * Constructor.
	 *
	 * @param TTP_Item_Registry       $registry           Registry.
	 * @param TTP_Scenario_Store      $scenario_store     Scenario store.
	 * @param TTP_Danger_Backup_Store $backup_store       Backup store.
	 * @param TTP_Dashboard_Actions   $dashboard_actions  Shared actions.
	 * @param TTP_Dashboard_Renderer  $dashboard_renderer HTML fragments.
	 */
	public function __construct( TTP_Item_Registry $registry, TTP_Scenario_Store $scenario_store, TTP_Danger_Backup_Store $backup_store, TTP_Dashboard_Actions $dashboard_actions, TTP_Dashboard_Renderer $dashboard_renderer ) {
		$this->registry           = $registry;
		$this->scenario_store     = $scenario_store;
		$this->backup_store       = $backup_store;
		$this->dashboard_actions  = $dashboard_actions;
		$this->dashboard_renderer = $dashboard_renderer;
		$this->rest_html          = new TTP_REST_Html( self::NAMESPACE_NAME );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		$this->rest_html->init();
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
	 * Return JSON or HTML for a card action.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $item    Item.
	 * @param mixed               $result  Action result.
	 * @param array<string,mixed> $json    JSON body on success.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond_action( WP_REST_Request $request, $item, $result, array $json ) {
		if ( is_wp_error( $result ) ) {
			if ( $this->rest_html->wants_html( $request ) ) {
				return $this->rest_html->html_response( $this->dashboard_renderer->render_action_response( $item, $result ) );
			}

			return $result;
		}

		if ( $this->rest_html->wants_html( $request ) ) {
			return $this->rest_html->html_response( $this->dashboard_renderer->render_action_response( $item, $result ) );
		}

		return rest_ensure_response( $json );
	}

	/**
	 * Parse params from JSON or Dashboard form field names.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function parse_params_from_request( WP_REST_Request $request ) {
		$params = $request->get_param( 'params' );

		if ( is_array( $params ) ) {
			return $this->clean_string_keyed_array( $params );
		}

		$ttp_params = $request->get_param( 'ttp_params' );

		if ( is_array( $ttp_params ) ) {
			$raw = map_deep( $ttp_params, 'sanitize_textarea_field' );

			return $this->clean_string_keyed_array( is_array( $raw ) ? $raw : array() );
		}

		return array();
	}

	/**
	 * Parse enabled flag from JSON or form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function parse_enabled_from_request( WP_REST_Request $request ) {
		if ( null !== $request->get_param( 'enabled' ) ) {
			return (bool) $request->get_param( 'enabled' );
		}

		return ! empty( $request->get_param( 'ttp_enabled' ) );
	}

	/**
	 * Parse danger target from JSON or form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function parse_target_from_request( WP_REST_Request $request ) {
		$target = $request->get_param( 'target' );

		if ( is_string( $target ) && '' !== $target ) {
			return sanitize_text_field( $target );
		}

		$ttp_target = $request->get_param( 'ttp_target' );

		return is_string( $ttp_target ) ? sanitize_text_field( $ttp_target ) : '';
	}

	/**
	 * Keep only string keys in an array.
	 *
	 * @param array<mixed> $data Raw array.
	 * @return array<string,mixed>
	 */
	private function clean_string_keyed_array( array $data ) {
		$clean = array();

		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) ) {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
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

		$result = $this->dashboard_actions->save_scenario(
			$item,
			$this->parse_enabled_from_request( $request ),
			$this->parse_params_from_request( $request )
		);

		$item_id = $item['id'];

		return $this->respond_action(
			$request,
			$item,
			$result,
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

		$result  = $this->dashboard_actions->reset_scenario( $item );
		$item_id = $item['id'];

		return $this->respond_action(
			$request,
			$item,
			$result,
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

		$payload = $this->parse_params_from_request( $request );

		if ( empty( $payload ) ) {
			$payload = $this->get_payload( $request );
		}

		$result = $this->dashboard_actions->run_utility( $item, $payload );

		return $this->respond_action( $request, $item, $result, is_array( $result ) ? $result : array() );
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
		$item = $this->get_item_for_type( $this->request_item_id( $request ), 'danger_utility' );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$result = $this->dashboard_actions->mutate_danger(
			$item,
			$this->parse_target_from_request( $request ),
			$this->parse_params_from_request( $request )
		);

		return $this->respond_action( $request, $item, $result, is_array( $result ) ? $result : array() );
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

		$result = $this->dashboard_actions->restore_danger(
			$item,
			$this->parse_target_from_request( $request )
		);

		return $this->respond_action( $request, $item, $result, is_array( $result ) ? $result : array() );
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
			return $this->clean_string_keyed_array( $payload );
		}

		$params = $request->get_params();
		unset( $params['id'] );

		return $this->clean_string_keyed_array( $params );
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
