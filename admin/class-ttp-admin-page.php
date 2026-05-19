<?php
/**
 * Admin Dashboard page.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a PHP-only Dashboard shell.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Admin_Page {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'themeisle-tester';

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
	 * Activity store.
	 *
	 * @var TTP_Activity_Store
	 */
	private $activity_store;

	/**
	 * View partial loader.
	 *
	 * @var TTP_View_Loader
	 */
	private $view_loader;

	/**
	 * Tab/panel layout renderer.
	 *
	 * @var TTP_Dashboard_Layout_Renderer
	 */
	private $layout_renderer;

	/**
	 * Classic form POST handler.
	 *
	 * @var TTP_Admin_Form_Handler
	 */
	private $form_handler;

	/**
	 * Dashboard asset registration.
	 *
	 * @var TTP_Admin_Assets
	 */
	private $assets;

	/**
	 * Datastar attribute helpers.
	 *
	 * @var TTP_Datastar
	 */
	private $datastar;

	/**
	 * Form field renderer.
	 *
	 * @var TTP_Field_Renderer
	 */
	private $field_renderer;

	/**
	 * Flash notice renderer.
	 *
	 * @var TTP_Flash_Renderer
	 */
	private $flash_renderer;

	/**
	 * Danger Utility table renderer.
	 *
	 * @var TTP_Danger_Table_Renderer
	 */
	private $danger_table_renderer;

	/**
	 * Activity log renderer.
	 *
	 * @var TTP_Activity_Renderer
	 */
	private $activity_renderer;

	/**
	 * Inspect result renderer.
	 *
	 * @var TTP_Inspect_Result_Renderer
	 */
	private $inspect_result_renderer;

	/**
	 * Scenario saved-summary renderer.
	 *
	 * @var TTP_Scenario_Summary_Renderer
	 */
	private $scenario_summary_renderer;

	/**
	 * Constructor.
	 *
	 * @param TTP_Item_Registry       $registry          Registry.
	 * @param TTP_Scenario_Store      $scenario_store    Scenario store.
	 * @param TTP_Danger_Backup_Store $backup_store      Backup store.
	 * @param TTP_Dashboard_Actions   $dashboard_actions Shared actions.
	 * @param TTP_Activity_Store      $activity_store    Activity store.
	 */
	public function __construct( TTP_Item_Registry $registry, TTP_Scenario_Store $scenario_store, TTP_Danger_Backup_Store $backup_store, TTP_Dashboard_Actions $dashboard_actions, TTP_Activity_Store $activity_store ) {
		$this->registry                  = $registry;
		$this->scenario_store            = $scenario_store;
		$this->backup_store              = $backup_store;
		$this->activity_store            = $activity_store;
		$this->view_loader               = new TTP_View_Loader();
		$this->layout_renderer           = new TTP_Dashboard_Layout_Renderer( $this->view_loader, $this->scenario_store );
		$this->form_handler              = new TTP_Admin_Form_Handler( $registry, $dashboard_actions );
		$this->assets                    = new TTP_Admin_Assets( self::MENU_SLUG );
		$this->datastar                  = new TTP_Datastar();
		$this->field_renderer            = new TTP_Field_Renderer();
		$this->flash_renderer            = new TTP_Flash_Renderer();
		$this->danger_table_renderer     = new TTP_Danger_Table_Renderer( $this->datastar, $this->backup_store );
		$this->activity_renderer         = new TTP_Activity_Renderer( $this->activity_store );
		$this->inspect_result_renderer   = new TTP_Inspect_Result_Renderer();
		$this->scenario_summary_renderer = new TTP_Scenario_Summary_Renderer();
	}

	/**
	 * Activity renderer for REST morph fragments.
	 *
	 * @return TTP_Activity_Renderer
	 */
	public function get_activity_renderer() {
		return $this->activity_renderer;
	}

	/**
	 * Flash renderer for REST morph fragments.
	 *
	 * @return TTP_Flash_Renderer
	 */
	public function get_flash_renderer() {
		return $this->flash_renderer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		$this->assets->init();
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Themeisle Tester', 'themeisle-tester' ),
			__( 'Themeisle Tester', 'themeisle-tester' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-admin-tools',
			25
		);
	}

	/**
	 * REST URL for a Dashboard action path.
	 *
	 * @param string $path Path relative to ttp/v1 (e.g. scenarios/foo).
	 * @return string
	 */
	public function rest_endpoint( $path ) {
		return $this->datastar->rest_endpoint( $path );
	}

	/**
	 * Datastar @post() attribute for a form submit handler.
	 *
	 * @param string $path REST path relative to ttp/v1.
	 * @return string HTML attribute string (no leading space).
	 */
	public function datastar_post_attr( $path ) {
		return $this->datastar->datastar_post_attr( $path );
	}

	/**
	 * Datastar fetch lifecycle attribute for card busy state.
	 *
	 * @return string HTML attribute string (no leading space).
	 */
	public function datastar_busy_attr() {
		return $this->datastar->datastar_busy_attr();
	}

	/**
	 * Handle PHP-only admin form posts.
	 *
	 * @return void
	 */
	public function handle_post() {
		$this->form_handler->handle_post();
	}

	/**
	 * Render Dashboard.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->render_view(
			'dashboard',
			array( 'categories' => $this->registry->get_items_by_category() )
		);
	}

	/**
	 * Render a view partial from admin/views/{name}.php.
	 *
	 * Partials run in their own function scope (no leakage into the caller).
	 * They receive `$page` (this instance) plus every key in `$vars` extracted
	 * as a local variable.
	 *
	 * @param string              $name View slug (no `.php`, no path separators).
	 * @param array<string,mixed> $vars Locals passed to the partial.
	 * @return void
	 */
	public function render_view( $name, array $vars = array() ) {
		$this->view_loader->render( $this, $name, $vars );
	}

	/**
	 * Read scenario state for use inside view partials.
	 *
	 * @param string $scenario_id Scenario ID.
	 * @return array<string,mixed>
	 */
	public function get_scenario_state( $scenario_id ) {
		return $this->scenario_store->get( $scenario_id );
	}

	/**
	 * Render the tablist + panels for grouped categories.
	 *
	 * @phpstan-param array<string,array<string,NormalizedItem>> $categories
	 *
	 * @param array<string,array<string,array<string,mixed>>> $categories Grouped items.
	 * @return void
	 */
	public function render_tabs( $categories ) {
		$this->layout_renderer->render_tabs( $this, $categories );
	}

	/**
	 * Render the items inside a panel, splitting into sub-grids by 'group' value.
	 *
	 * If items share a single group (or none), this renders one grid. When two or
	 * more distinct non-empty groups exist, each gets a labelled subheading + divider.
	 *
	 * @phpstan-param array<string,NormalizedItem> $items
	 *
	 * @param array<string,array<string,mixed>> $items Items keyed by ID, in registration order.
	 * @return void
	 */
	public function render_panel_groups( $items ) {
		$this->layout_renderer->render_panel_groups( $this, $items );
	}

	/**
	 * Count enabled Scenarios in a category bucket.
	 *
	 * @phpstan-param array<string,NormalizedItem> $items
	 *
	 * @param array<string,array<string,mixed>> $items Items keyed by ID.
	 * @return int
	 */
	public function count_active_scenarios( $items ) {
		return $this->layout_renderer->count_active_scenarios( $items );
	}

	/**
	 * Human label for an item type.
	 *
	 * Called by admin/views/card.php.
	 *
	 * @param string $type Item type.
	 * @return string
	 */
	public function type_label( $type ) {
		if ( 'scenario' === $type ) {
			return __( 'Scenario', 'themeisle-tester' );
		}

		if ( 'danger_utility' === $type ) {
			return __( 'Danger Utility', 'themeisle-tester' );
		}

		return __( 'Utility', 'themeisle-tester' );
	}

	/**
	 * Render a compact "Saved" summary listing the scenario's currently stored params.
	 *
	 * Output is skipped entirely when no field has a non-empty saved value, so cards
	 * without saved state stay clean.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item.
	 * @param array<string,mixed> $params Saved params keyed by field id.
	 * @return void
	 */
	public function render_saved_summary( $item, $params ) {
		$this->scenario_summary_renderer->render_saved_summary( $item, $params );
	}

	/**
	 * Render fields.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item.
	 * @param array<string,mixed> $params Params.
	 * @return void
	 */
	public function render_fields( $item, $params ) {
		$this->field_renderer->render_fields( $item, $params );
	}

	/**
	 * Render license rows.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @param array<mixed>        $rows Rows.
	 * @return void
	 */
	public function render_license_rows( $item, $rows ) {
		$this->danger_table_renderer->render_license_rows( $item, $rows );
	}

	/**
	 * Render install rows.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @param array<mixed>        $rows Rows.
	 * @return void
	 */
	public function render_install_rows( $item, $rows ) {
		$this->danger_table_renderer->render_install_rows( $item, $rows );
	}

	/**
	 * Render action result notice.
	 *
	 * @return void
	 */
	public function render_action_result() {
		echo '<div id="ttp-flash" class="ttp-flash-region" aria-live="polite">';
		$this->render_flash_markup( $this->form_handler->get_action_result() );
		echo '</div>';
	}

	/**
	 * Render flash notice markup (inner content of #ttp-flash).
	 *
	 * @param mixed $result Action result, error, or null.
	 * @return void
	 */
	public function render_flash_markup( $result ) {
		$this->flash_renderer->render_flash_markup( $result );
	}

	/**
	 * Render recent Dashboard activity.
	 *
	 * @return void
	 */
	public function render_activity() {
		$this->activity_renderer->render_activity();
	}

	/**
	 * Render a generic result table.
	 *
	 * @param mixed $result Result.
	 * @return void
	 */
	public function render_result_table( $result ) {
		$this->inspect_result_renderer->render_result_table( $result );
	}
}
