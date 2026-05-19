<?php
/**
 * Dashboard admin asset registration.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues Dashboard CSS/JS on the Themeisle Tester admin page only.
 */
class TTP_Admin_Assets {

	/**
	 * Admin menu slug.
	 *
	 * @var string
	 */
	private $menu_slug;

	/**
	 * Constructor.
	 *
	 * @param string $menu_slug Admin menu slug.
	 */
	public function __construct( $menu_slug ) {
		$this->menu_slug = $menu_slug;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_controls' ) );
		add_filter( 'script_loader_tag', array( $this, 'filter_script_loader_tag' ), 10, 2 );
	}

	/**
	 * Enqueue Dashboard stylesheet on the Dashboard page only.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 * @return void
	 */
	public function enqueue_dashboard_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, $this->menu_slug ) ) {
			return;
		}

		wp_enqueue_style(
			'ttp-dashboard',
			TTP_PLUGIN_URL . 'admin/css/dashboard.css',
			array(),
			TTP_VERSION
		);

		wp_enqueue_script(
			'ttp-datastar',
			TTP_PLUGIN_URL . 'admin/js/libs/datastar.min.js',
			array(),
			TTP_VERSION,
			true
		);
		wp_script_add_data( 'ttp-datastar', 'type', 'module' );

		wp_localize_script(
			'ttp-datastar',
			'ttpDashboard',
			array(
				'restUrl'   => rest_url( TTP_REST_Controller::NAMESPACE_NAME . '/' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_script(
			'ttp-dashboard',
			TTP_PLUGIN_URL . 'admin/js/dashboard.js',
			array( 'ttp-datastar' ),
			TTP_VERSION,
			true
		);
	}

	/**
	 * Ensure vendored ESM scripts are emitted as module scripts.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function filter_script_loader_tag( $tag, $handle ) {
		if ( 'ttp-datastar' !== $handle || false !== strpos( $tag, ' type=' ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	/**
	 * Fire Dashboard-only Control enqueue hook.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 * @return void
	 */
	public function enqueue_dashboard_controls( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, $this->menu_slug ) ) {
			return;
		}

		/**
		 * Fires when Product plugins should enqueue Themeisle Tester Dashboard Control scripts.
		 *
		 * Product scripts should register optional controls on window.ttpTester.
		 */
		do_action( 'ttp_enqueue_controls' );
	}
}
