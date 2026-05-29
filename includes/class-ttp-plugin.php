<?php
/**
 * Main plugin coordinator.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the Themeisle Tester services.
 */
class TTP_Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var TTP_Plugin|null
	 */
	private static $instance = null;

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
	private $scenario_store;

	/**
	 * Danger Utility backup store.
	 *
	 * @var TTP_Danger_Backup_Store
	 */
	private $backup_store;

	/**
	 * Dashboard activity store.
	 *
	 * @var TTP_Activity_Store
	 */
	private $activity_store;

	/**
	 * Schema sanitizer.
	 *
	 * @var TTP_Schema_Sanitizer
	 */
	private $schema_sanitizer;

	/**
	 * Get plugin instance.
	 *
	 * @return TTP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->registry         = new TTP_Item_Registry();
		$this->scenario_store   = new TTP_Scenario_Store();
		$this->backup_store     = new TTP_Danger_Backup_Store();
		$this->activity_store   = new TTP_Activity_Store();
		$this->schema_sanitizer = new TTP_Schema_Sanitizer();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'init', array( $this, 'register_items_and_apply_scenarios' ), 11 );

		$addon_loader = new TTP_Addon_Loader();
		$addon_loader->init();

		$dashboard_actions = new TTP_Dashboard_Actions( $this->registry, $this->scenario_store, $this->backup_store, $this->schema_sanitizer, $this->activity_store );

		$admin_page = new TTP_Admin_Page( $this->registry, $this->scenario_store, $this->backup_store, $dashboard_actions, $this->activity_store );
		$admin_page->init();

		$dashboard_renderer = new TTP_Dashboard_Renderer( $admin_page, $this->registry, $admin_page->get_flash_renderer(), $admin_page->get_activity_renderer() );

		$admin_notices = new TTP_Admin_Notices( $this->registry, $this->scenario_store );
		$admin_notices->init();

		$rest_controller = new TTP_REST_Controller( $this->registry, $this->scenario_store, $this->backup_store, $dashboard_actions, $dashboard_renderer );
		$rest_controller->init();
	}

	/**
	 * Load plugin translations (WordPress 6.7+ requires init or later).
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'themeisle-tester',
			false,
			dirname( plugin_basename( TTP_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Run item registration and then apply enabled Scenarios.
	 *
	 * @return void
	 */
	public function register_items_and_apply_scenarios() {
		/**
		 * Fires when Product plugins should register Themeisle Tester Testing Items.
		 *
		 * Product plugins should call $registry->register() with array schemas here.
		 *
		 * @param TTP_Item_Registry $registry Testing Item registry.
		 */
		do_action( 'ttp_register_items', $this->registry );

		$this->registry->finalize();

		/**
		 * Fires after Themeisle Tester has closed registration and normalized items.
		 *
		 * Product plugins may inspect registered items here, but should not register new ones.
		 *
		 * @param TTP_Item_Registry $registry Testing Item registry.
		 */
		do_action( 'ttp_items_registered', $this->registry );

		$applicator = new TTP_Hook_Applicator( $this->registry, $this->scenario_store );
		$applicator->apply();
	}
}
