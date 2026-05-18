<?php
/**
 * Admin notices.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders Dashboard-independent notices.
 */
class TTP_Admin_Notices {

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
	private $store;

	/**
	 * Constructor.
	 *
	 * @param TTP_Item_Registry  $registry Registry.
	 * @param TTP_Scenario_Store $store    Scenario store.
	 */
	public function __construct( TTP_Item_Registry $registry, TTP_Scenario_Store $store ) {
		$this->registry = $registry;
		$this->store    = $store;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'render_active_scenarios_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_registry_errors' ) );
	}

	/**
	 * Render active Scenario notice.
	 *
	 * @return void
	 */
	public function render_active_scenarios_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active = array();

		foreach ( $this->registry->get_items() as $item ) {
			if ( 'scenario' !== $item['type'] ) {
				continue;
			}

			$state = $this->store->get( $item['id'] );

			if ( ! empty( $state['enabled'] ) ) {
				$active[] = $item['label'];
			}
		}

		if ( empty( $active ) ) {
			return;
		}

		?>
		<div class="notice notice-warning ttp-active-scenarios-notice">
			<p>
				<strong><?php esc_html_e( 'Themeisle Tester active Scenarios:', 'themeisle-tester' ); ?></strong>
				<?php echo esc_html( implode( ', ', $active ) ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render registry errors.
	 *
	 * @return void
	 */
	public function render_registry_errors() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$errors = $this->registry->get_errors();

		if ( empty( $errors ) ) {
			return;
		}

		?>
		<div class="notice notice-error ttp-registry-errors">
			<p><strong><?php esc_html_e( 'Themeisle Tester registration errors:', 'themeisle-tester' ); ?></strong></p>
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
