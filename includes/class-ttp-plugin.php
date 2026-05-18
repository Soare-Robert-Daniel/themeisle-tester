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
		$this->schema_sanitizer = new TTP_Schema_Sanitizer();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		$bundled_items = new TTP_Bundled_Items();

		add_action( 'ttp_register_items', array( $bundled_items, 'register' ) );
		add_action( 'plugins_loaded', array( $this, 'register_items_and_apply_scenarios' ), 20 );

		$admin_page = new TTP_Admin_Page( $this->registry, $this->scenario_store, $this->backup_store, $this->schema_sanitizer );
		$admin_page->init();

		$admin_notices = new TTP_Admin_Notices( $this->registry, $this->scenario_store );
		$admin_notices->init();

		$rest_controller = new TTP_REST_Controller( $this->registry, $this->scenario_store, $this->backup_store, $this->schema_sanitizer );
		$rest_controller->init();
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
