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
	 * Schema sanitizer.
	 *
	 * @var TTP_Schema_Sanitizer
	 */
	private $schema_sanitizer;

	/**
	 * Last admin action result.
	 *
	 * @var array<string,mixed>|WP_Error|null
	 */
	private $action_result = null;

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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_controls' ) );
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
	 * Enqueue Dashboard stylesheet on the Dashboard page only.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 * @return void
	 */
	public function enqueue_dashboard_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'ttp-dashboard',
			TTP_PLUGIN_URL . 'admin/css/dashboard.css',
			array(),
			TTP_VERSION
		);

		wp_enqueue_script(
			'ttp-dashboard',
			TTP_PLUGIN_URL . 'admin/js/dashboard.js',
			array(),
			TTP_VERSION,
			true
		);
	}

	/**
	 * Fire Dashboard-only Control enqueue hook.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 * @return void
	 */
	public function enqueue_dashboard_controls( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		/**
		 * Fires when Product plugins should enqueue Themeisle Tester Dashboard Control scripts.
		 *
		 * Product scripts should register optional controls on window.ttpTester.
		 */
		do_action( 'ttp_enqueue_controls' );
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

		if ( 'save_scenario' === $action ) {
			$this->action_result = $this->handle_save_scenario( $item );
			return;
		}

		if ( 'reset_scenario' === $action ) {
			$this->scenario_store->reset( $item['id'] );
			$this->action_result = array( 'message' => __( 'Scenario reset.', 'themeisle-tester' ) );
			return;
		}

		if ( 'run_utility' === $action && is_callable( $item['run'] ) ) {
			$payload = $this->get_post_payload();

			/**
			 * Fires before a Themeisle Tester Utility is run from the PHP Dashboard.
			 *
			 * Product plugins can observe Utility execution for debugging.
			 *
			 * @param array<string,mixed> $item    Normalized Utility definition.
			 * @param array<string,mixed> $payload Request payload.
			 */
			do_action( 'ttp_before_run_utility', $item, $payload );

			$result = call_user_func( $item['run'], $item, $payload );

			if ( ! is_wp_error( $result ) ) {
				/**
				 * Fires after a Themeisle Tester Utility has run from the PHP Dashboard.
				 *
				 * Product plugins can observe Utility execution results for debugging.
				 *
				 * @param array<string,mixed> $item    Normalized Utility definition.
				 * @param array<string,mixed> $payload Request payload.
				 * @param mixed               $result  Utility result.
				 */
				do_action( 'ttp_after_run_utility', $item, $payload, $result );
			}

			$this->action_result = $result;
			return;
		}

		if ( 'mutate_danger' === $action ) {
			$this->action_result = $this->handle_mutate_danger( $item );
			return;
		}

		if ( 'restore_danger' === $action ) {
			$this->action_result = $this->handle_restore_danger( $item );
		}
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

		$categories = $this->registry->get_items_by_category();
		$stats      = $this->collect_stats( $categories );

		?>
		<div class="wrap ttp-dashboard">
			<header class="ttp-dashboard__intro">
				<div>
					<h1><?php esc_html_e( 'Themeisle Tester', 'themeisle-tester' ); ?></h1>
					<p><?php esc_html_e( 'Create, inspect, and reset controlled Themeisle testing conditions.', 'themeisle-tester' ); ?></p>
				</div>
				<div class="ttp-dashboard__stats">
					<span class="ttp-stat"><strong><?php echo esc_html( (string) $stats['total'] ); ?></strong> <?php esc_html_e( 'items', 'themeisle-tester' ); ?></span>
					<span class="ttp-stat ttp-stat--active"><strong><?php echo esc_html( (string) $stats['active'] ); ?></strong> <?php esc_html_e( 'active scenarios', 'themeisle-tester' ); ?></span>
					<span class="ttp-stat"><strong><?php echo esc_html( (string) $stats['danger'] ); ?></strong> <?php esc_html_e( 'danger utilities', 'themeisle-tester' ); ?></span>
				</div>
			</header>

			<?php $this->render_action_result(); ?>

			<?php if ( empty( $categories ) ) : ?>
				<p class="ttp-empty"><?php esc_html_e( 'No Testing Items have been registered yet.', 'themeisle-tester' ); ?></p>
			<?php else : ?>
				<?php $this->render_tabs( $categories ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the tablist + panels for grouped categories.
	 *
	 * @phpstan-param array<string,array<string,NormalizedItem>> $categories
	 *
	 * @param array<string,array<string,array<string,mixed>>> $categories Grouped items.
	 * @return void
	 */
	private function render_tabs( $categories ) {
		$tabs = array();

		foreach ( $categories as $category => $items ) {
			$slug   = sanitize_title( $category );
			$tabs[] = array(
				'category' => $category,
				'items'    => $items,
				'tab_id'   => 'ttp-tab-' . $slug,
				'panel_id' => 'ttp-panel-' . $slug,
				'active'   => $this->count_active_scenarios( $items ),
				'count'    => count( $items ),
			);
		}

		?>
		<ul class="ttp-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Testing categories', 'themeisle-tester' ); ?>">
			<?php foreach ( $tabs as $index => $tab ) : ?>
				<li role="presentation">
					<button
						type="button"
						class="ttp-tab"
						role="tab"
						id="<?php echo esc_attr( $tab['tab_id'] ); ?>"
						aria-controls="<?php echo esc_attr( $tab['panel_id'] ); ?>"
						aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
						tabindex="<?php echo 0 === $index ? '0' : '-1'; ?>"
					>
						<span><?php echo esc_html( $tab['category'] ); ?></span>
						<span class="ttp-tab__count"><?php echo esc_html( (string) $tab['count'] ); ?></span>
						<?php
						if ( $tab['active'] > 0 ) :
							/* translators: %d: number of active scenarios in this category. */
							$indicator_label = sprintf( _n( '%d active scenario', '%d active scenarios', $tab['active'], 'themeisle-tester' ), $tab['active'] );
							?>
							<span class="ttp-tab__indicator" aria-label="<?php echo esc_attr( $indicator_label ); ?>"></span>
						<?php endif; ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="ttp-panels">
			<?php foreach ( $tabs as $index => $tab ) : ?>
				<section
					class="ttp-panel"
					id="<?php echo esc_attr( $tab['panel_id'] ); ?>"
					role="tabpanel"
					aria-labelledby="<?php echo esc_attr( $tab['tab_id'] ); ?>"
					<?php echo 0 === $index ? '' : 'hidden'; ?>
				>
					<?php $this->render_panel_groups( $tab['items'] ); ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
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
	private function render_panel_groups( $items ) {
		$groups = array();

		foreach ( $items as $item ) {
			$key = $item['group'];

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
			}

			$groups[ $key ][] = $item;
		}

		$show_headings = count( $groups ) > 1 && ! array_key_exists( '', $groups );

		foreach ( $groups as $label => $group_items ) {
			if ( $show_headings && '' !== $label ) {
				echo '<h3 class="ttp-panel__group-title">' . esc_html( $label ) . '</h3>';
			}

			echo '<div class="ttp-panel__grid">';

			foreach ( $group_items as $item ) {
				$this->render_item( $item );
			}

			echo '</div>';
		}
	}

	/**
	 * Count enabled Scenarios in a category bucket.
	 *
	 * @phpstan-param array<string,NormalizedItem> $items
	 *
	 * @param array<string,array<string,mixed>> $items Items keyed by ID.
	 * @return int
	 */
	private function count_active_scenarios( $items ) {
		$count = 0;

		foreach ( $items as $item ) {
			if ( 'scenario' !== $item['type'] || empty( $item['available'] ) ) {
				continue;
			}

			$state = $this->scenario_store->get( $item['id'] );

			if ( ! empty( $state['enabled'] ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Collect quick header stats from grouped items.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $categories Grouped items.
	 * @return array{total:int,active:int,danger:int}
	 */
	private function collect_stats( $categories ) {
		$seen   = array();
		$active = 0;
		$danger = 0;

		foreach ( $categories as $items ) {
			foreach ( $items as $item ) {
				$id          = isset( $item['id'] ) && is_string( $item['id'] ) ? $item['id'] : '';
				$type        = isset( $item['type'] ) && is_string( $item['type'] ) ? $item['type'] : '';
				$seen[ $id ] = true;

				if ( 'scenario' === $type ) {
					$state = $this->scenario_store->get( $id );

					if ( ! empty( $state['enabled'] ) ) {
						++$active;
					}
				}

				if ( 'danger_utility' === $type ) {
					++$danger;
				}
			}
		}

		return array(
			'total'  => count( $seen ),
			'active' => $active,
			'danger' => $danger,
		);
	}

	/**
	 * Render one item.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	private function render_item( $item ) {
		$is_active = false;

		if ( 'scenario' === $item['type'] && ! empty( $item['available'] ) ) {
			$state     = $this->scenario_store->get( $item['id'] );
			$is_active = ! empty( $state['enabled'] );
		}

		$classes = array( 'ttp-card', 'ttp-card--' . $item['type'] );

		if ( $is_active ) {
			$classes[] = 'ttp-card--scenario-active';
		}

		if ( 'danger_utility' === $item['type'] ) {
			$classes[] = 'ttp-card--danger';
		}

		if ( empty( $item['available'] ) ) {
			$classes[] = 'ttp-card--unavailable';
		}

		?>
		<article class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-ttp-item-id="<?php echo esc_attr( $item['id'] ); ?>" data-ttp-item-type="<?php echo esc_attr( $item['type'] ); ?>">
			<header class="ttp-card__header">
				<div class="ttp-card__meta">
					<span class="ttp-badge ttp-badge--<?php echo esc_attr( $item['type'] ); ?>"><?php echo esc_html( $this->type_label( $item['type'] ) ); ?></span>
					<?php if ( $is_active ) : ?>
						<span class="ttp-badge ttp-badge--active"><?php esc_html_e( 'Active', 'themeisle-tester' ); ?></span>
					<?php endif; ?>
					<span class="ttp-product"><?php echo esc_html( $item['product'] ); ?></span>
				</div>
				<h3 class="ttp-card__title"><?php echo esc_html( $item['label'] ); ?></h3>
				<?php if ( '' !== $item['description'] ) : ?>
					<p class="ttp-card__description"><?php echo esc_html( $item['description'] ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( empty( $item['available'] ) ) : ?>
				<p class="ttp-note ttp-note--error"><?php echo esc_html( '' !== $item['unavailable_reason'] ? $item['unavailable_reason'] : __( 'Testing Item is unavailable.', 'themeisle-tester' ) ); ?></p>
			<?php elseif ( 'scenario' === $item['type'] ) : ?>
				<?php $this->render_scenario( $item ); ?>
			<?php elseif ( 'utility' === $item['type'] ) : ?>
				<?php $this->render_utility( $item ); ?>
			<?php elseif ( 'danger_utility' === $item['type'] ) : ?>
				<?php $this->render_danger_utility( $item ); ?>
			<?php endif; ?>
		</article>
		<?php
	}

	/**
	 * Human label for an item type.
	 *
	 * @param string $type Item type.
	 * @return string
	 */
	private function type_label( $type ) {
		if ( 'scenario' === $type ) {
			return __( 'Scenario', 'themeisle-tester' );
		}

		if ( 'danger_utility' === $type ) {
			return __( 'Danger Utility', 'themeisle-tester' );
		}

		return __( 'Utility', 'themeisle-tester' );
	}

	/**
	 * Render Scenario controls.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	private function render_scenario( $item ) {
		$state   = $this->scenario_store->get( $item['id'] );
		$params  = isset( $state['params'] ) && is_array( $state['params'] ) ? $state['params'] : array();
		$form_id = 'ttp-scenario-' . sanitize_html_class( $item['id'] );
		?>
		<form id="<?php echo esc_attr( $form_id ); ?>" method="post" class="ttp-card__body ttp-scenario-form">
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="save_scenario">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<label class="ttp-toggle">
				<input type="checkbox" name="ttp_enabled" value="1" <?php checked( ! empty( $state['enabled'] ) ); ?>>
				<?php esc_html_e( 'Enable this scenario', 'themeisle-tester' ); ?>
			</label>
			<?php $this->render_fields( $item, $params ); ?>
		</form>
		<div class="ttp-card__actions">
			<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="button button-primary">
				<?php esc_html_e( 'Save', 'themeisle-tester' ); ?>
			</button>
			<form method="post" class="ttp-inline-form">
				<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
				<input type="hidden" name="ttp_action" value="reset_scenario">
				<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Reset', 'themeisle-tester' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Utility controls.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	private function render_utility( $item ) {
		if ( is_callable( $item['inspect'] ) ) {
			$result = call_user_func( $item['inspect'], $item, array() );

			echo '<div class="ttp-card__body">';
			$this->render_result_table( $result );
			echo '</div>';
		}

		if ( is_callable( $item['run'] ) ) :
			?>
			<div class="ttp-card__actions">
				<form method="post" class="ttp-inline-form">
					<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
					<input type="hidden" name="ttp_action" value="run_utility">
					<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Run', 'themeisle-tester' ); ?>
					</button>
				</form>
			</div>
			<?php
		endif;
	}

	/**
	 * Render Danger Utility controls.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	private function render_danger_utility( $item ) {
		if ( ! is_callable( $item['inspect'] ) ) {
			return;
		}

		$result = call_user_func( $item['inspect'], $item, array() );

		echo '<div class="ttp-card__body">';

		if ( is_wp_error( $result ) ) {
			echo '<p class="ttp-note ttp-note--error">' . esc_html( $result->get_error_message() ) . '</p>';
			echo '</div>';
			return;
		}

		if ( 'license_data_editor' === $item['id'] && isset( $result['rows'] ) && is_array( $result['rows'] ) ) {
			$this->render_license_rows( $item, $result['rows'] );
			echo '</div>';
			return;
		}

		if ( 'install_timestamp_editor' === $item['id'] && isset( $result['rows'] ) && is_array( $result['rows'] ) ) {
			if ( isset( $result['reference_date'] ) && is_string( $result['reference_date'] ) ) {
				echo '<p class="ttp-card__reference"><strong>' . esc_html__( 'Reference date:', 'themeisle-tester' ) . '</strong> ' . esc_html( $result['reference_date'] ) . '</p>';
			}

			$this->render_install_rows( $item, $result['rows'] );
			echo '</div>';
			return;
		}

		$this->render_result_table( $result );
		echo '</div>';
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
	private function render_fields( $item, $params ) {
		foreach ( $item['fields'] as $field ) {
			if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				continue;
			}

			$id          = sanitize_key( $field['id'] );
			$type        = isset( $field['type'] ) && is_string( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
			$label_value = isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $id;
			$raw_value   = isset( $params[ $id ] ) ? $params[ $id ] : '';
			$value       = is_scalar( $raw_value ) ? (string) $raw_value : '';
			$control_id  = 'ttp-' . $item['id'] . '-' . $id;

			if ( 'toggle' === $type || 'boolean' === $type ) {
				$checked = filter_var( $raw_value, FILTER_VALIDATE_BOOLEAN );
				echo '<div class="ttp-card__field ttp-card__field--toggle">';
				echo '<label class="ttp-toggle">';
				echo '<input type="checkbox" id="' . esc_attr( $control_id ) . '" name="ttp_params[' . esc_attr( $id ) . ']" value="1" ' . checked( $checked, true, false ) . '>';
				echo '<span>' . esc_html( $label_value ) . '</span>';
				echo '</label>';
				echo '</div>';
				continue;
			}

			echo '<div class="ttp-card__field">';
			echo '<label for="' . esc_attr( $control_id ) . '" class="ttp-card__field-label">' . esc_html( $label_value ) . '</label>';

			if ( 'select' === $type && isset( $field['options'] ) && is_array( $field['options'] ) ) {
				echo '<select id="' . esc_attr( $control_id ) . '" name="ttp_params[' . esc_attr( $id ) . ']">';
				echo '<option value="">' . esc_html__( 'Select', 'themeisle-tester' ) . '</option>';
				foreach ( $field['options'] as $option ) {
					if ( ! is_scalar( $option ) ) {
						continue;
					}

					$option_value = (string) $option;
					echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( ucwords( str_replace( '-', ' ', $option_value ) ) ) . '</option>';
				}
				echo '</select>';
			} else {
				$input_type = 'date' === $type ? 'date' : 'text';
				echo '<input id="' . esc_attr( $control_id ) . '" type="' . esc_attr( $input_type ) . '" name="ttp_params[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '">';
			}

			echo '</div>';
		}
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
	private function render_license_rows( $item, $rows ) {
		if ( empty( $rows ) ) {
			echo '<p class="ttp-empty">' . esc_html__( 'No license data options found.', 'themeisle-tester' ) . '</p>';
			return;
		}

		?>
		<table class="ttp-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Option', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'Status', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'License Key', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'Change', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'Restore', 'themeisle-tester' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) :
					if ( ! is_array( $row ) ) {
						continue;
					}

					$target      = isset( $row['target'] ) && is_string( $row['target'] ) ? $row['target'] : '';
					$status      = isset( $row['status'] ) && is_scalar( $row['status'] ) ? (string) $row['status'] : '';
					$key_display = isset( $row['key_display'] ) && is_scalar( $row['key_display'] ) ? (string) $row['key_display'] : '';
					?>
					<tr>
						<td><code><?php echo esc_html( $target ); ?></code></td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><code><?php echo esc_html( $key_display ); ?></code></td>
						<td><?php $this->render_danger_mutate_form( $item, $target, array( 'status' => $status ) ); ?></td>
						<td><?php $this->render_danger_restore_form( $item, $target ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
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
	private function render_install_rows( $item, $rows ) {
		if ( empty( $rows ) ) {
			echo '<p class="ttp-empty">' . esc_html__( 'No install timestamp options found.', 'themeisle-tester' ) . '</p>';
			return;
		}

		?>
		<table class="ttp-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Option', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'Date', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'Age', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'Change', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'Restore', 'themeisle-tester' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) :
					if ( ! is_array( $row ) ) {
						continue;
					}

					$target    = isset( $row['target'] ) && is_string( $row['target'] ) ? $row['target'] : '';
					$timestamp = isset( $row['timestamp'] ) && is_scalar( $row['timestamp'] ) ? (string) $row['timestamp'] : '';
					$date      = isset( $row['date'] ) && is_scalar( $row['date'] ) ? (string) $row['date'] : '';
					$age       = isset( $row['age'] ) && is_scalar( $row['age'] ) ? (string) $row['age'] : '';
					$date_only = isset( $row['date_only'] ) && is_string( $row['date_only'] ) ? $row['date_only'] : '';
					?>
					<tr>
						<td>
							<code><?php echo esc_html( $target ); ?></code>
							<?php if ( '' !== $timestamp ) : ?>
								<br><span class="ttp-data-table__muted"><?php echo esc_html( $timestamp ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $date ); ?></td>
						<td><?php echo esc_html( $age ); ?></td>
						<td><?php $this->render_danger_mutate_form( $item, $target, array( 'date' => $date_only ) ); ?></td>
						<td><?php $this->render_danger_restore_form( $item, $target ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render Danger Utility mutate form.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item.
	 * @param string              $target Target.
	 * @param array<string,mixed> $params Params.
	 * @return void
	 */
	private function render_danger_mutate_form( $item, $target, $params ) {
		?>
		<form method="post" class="ttp-data-table__form">
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="mutate_danger">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<input type="hidden" name="ttp_target" value="<?php echo esc_attr( $target ); ?>">
			<?php $this->render_inline_fields( $item, $params ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Apply', 'themeisle-tester' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render inline fields (no separate label) for table-row forms.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item.
	 * @param array<string,mixed> $params Params.
	 * @return void
	 */
	private function render_inline_fields( $item, $params ) {
		foreach ( $item['fields'] as $field ) {
			if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				continue;
			}

			$id    = sanitize_key( $field['id'] );
			$type  = isset( $field['type'] ) && is_string( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
			$label = isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $id;
			$value = isset( $params[ $id ] ) && is_scalar( $params[ $id ] ) ? (string) $params[ $id ] : '';

			if ( 'select' === $type && isset( $field['options'] ) && is_array( $field['options'] ) ) {
				echo '<select name="ttp_params[' . esc_attr( $id ) . ']" aria-label="' . esc_attr( $label ) . '">';
				foreach ( $field['options'] as $option ) {
					if ( ! is_scalar( $option ) ) {
						continue;
					}

					$option_value = (string) $option;
					echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( ucwords( str_replace( '-', ' ', $option_value ) ) ) . '</option>';
				}
				echo '</select>';
				continue;
			}

			$input_type = 'date' === $type ? 'date' : 'text';
			echo '<input type="' . esc_attr( $input_type ) . '" name="ttp_params[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( $label ) . '">';
		}
	}

	/**
	 * Render Danger Utility restore form.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item.
	 * @param string              $target Target.
	 * @return void
	 */
	private function render_danger_restore_form( $item, $target ) {
		if ( ! $this->backup_store->has( $item['id'], $target ) ) {
			echo '<span class="ttp-data-table__muted" aria-hidden="true">—</span>';
			return;
		}

		?>
		<form method="post" class="ttp-data-table__form">
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="restore_danger">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<input type="hidden" name="ttp_target" value="<?php echo esc_attr( $target ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Restore', 'themeisle-tester' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Save Scenario.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return array<string,string>|WP_Error
	 */
	private function handle_save_scenario( $item ) {
		$params = $this->get_post_payload();
		$params = $this->schema_sanitizer->sanitize_params( $item, $params );

		if ( is_wp_error( $params ) ) {
			return $params;
		}

		$enabled = ! empty( $_POST['ttp_enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
		$this->scenario_store->save( $item['id'], $enabled, $params );

		return array( 'message' => __( 'Scenario saved.', 'themeisle-tester' ) );
	}

	/**
	 * Mutate Danger Utility.
	 *
	 * @phpstan-param NormalizedItem $item
	 * @phpstan-return array<string,mixed>|WP_Error
	 *
	 * @param array<string,mixed> $item Item.
	 * @return array<string,mixed>|WP_Error
	 */
	private function handle_mutate_danger( $item ) {
		$applicator = new TTP_Hook_Applicator( $this->registry, $this->scenario_store );

		if ( ! $applicator->is_runtime_enabled() ) {
			return new WP_Error( 'ttp_runtime_disabled', __( 'Themeisle Tester runtime behavior is disabled.', 'themeisle-tester' ) );
		}

		$target = isset( $_POST['ttp_target'] ) && is_string( $_POST['ttp_target'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
			? sanitize_text_field( wp_unslash( $_POST['ttp_target'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
			: '';

		if ( '' === $target ) {
			return new WP_Error( 'ttp_missing_target', __( 'Danger Utility mutation requires a target.', 'themeisle-tester' ) );
		}

		$params = $this->get_post_payload();
		$params = $this->schema_sanitizer->sanitize_params( $item, $params );

		if ( is_wp_error( $params ) ) {
			return $params;
		}

		if ( ! is_callable( $item['inspect'] ) || ! is_callable( $item['mutate'] ) ) {
			return new WP_Error( 'ttp_utility_not_runnable', __( 'This Danger Utility is missing required callbacks.', 'themeisle-tester' ) );
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
		 * Fires before a Themeisle Tester Danger Utility mutates a target from the PHP Dashboard.
		 *
		 * Product plugins can observe controlled mutation for debugging.
		 *
		 * @param array<string,mixed> $item   Normalized Danger Utility definition.
		 * @param string              $target Target identifier.
		 * @param array<string,mixed> $params Sanitized params.
		 */
		do_action( 'ttp_before_mutate_danger_utility', $item, $target, $params );

		$result = call_user_func( $item['mutate'], $item, $target, $params );

		if ( ! is_wp_error( $result ) ) {
			/**
			 * Fires after a Themeisle Tester Danger Utility mutates a target from the PHP Dashboard.
			 *
			 * Product plugins can observe controlled mutation results for debugging.
			 *
			 * @param array<string,mixed> $item   Normalized Danger Utility definition.
			 * @param string              $target Target identifier.
			 * @param array<string,mixed> $params Sanitized params.
			 * @param mixed               $result Mutation result.
			 */
			do_action( 'ttp_after_mutate_danger_utility', $item, $target, $params, $result );
		}

		return $result;
	}

	/**
	 * Restore Danger Utility.
	 *
	 * @phpstan-param NormalizedItem $item
	 * @phpstan-return array<string,mixed>|WP_Error
	 *
	 * @param array<string,mixed> $item Item.
	 * @return array<string,mixed>|WP_Error
	 */
	private function handle_restore_danger( $item ) {
		$target = isset( $_POST['ttp_target'] ) && is_string( $_POST['ttp_target'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
			? sanitize_text_field( wp_unslash( $_POST['ttp_target'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
			: '';

		if ( '' === $target ) {
			return new WP_Error( 'ttp_missing_target', __( 'Danger Utility restore requires a target.', 'themeisle-tester' ) );
		}

		if ( ! is_callable( $item['restore'] ) ) {
			return new WP_Error( 'ttp_utility_not_runnable', __( 'This Danger Utility cannot restore backups.', 'themeisle-tester' ) );
		}

		$item_id = $item['id'];
		$backup  = $this->backup_store->get( $item_id, $target );

		if ( null === $backup ) {
			return new WP_Error( 'ttp_missing_backup', __( 'No backup exists for this target.', 'themeisle-tester' ) );
		}

		/**
		 * Fires before a Themeisle Tester Danger Utility restores a target from the PHP Dashboard.
		 *
		 * Product plugins can observe restore behavior for debugging.
		 *
		 * @param array<string,mixed> $item   Normalized Danger Utility definition.
		 * @param string              $target Target identifier.
		 */
		do_action( 'ttp_before_restore_danger_utility', $item, $target );

		$result = call_user_func( $item['restore'], $item, $target, $backup );

		if ( ! is_wp_error( $result ) ) {
			$this->backup_store->delete( $item_id, $target );

			/**
			 * Fires after a Themeisle Tester Danger Utility restores a target from the PHP Dashboard.
			 *
			 * Product plugins can observe restore results for debugging.
			 *
			 * @param array<string,mixed> $item   Normalized Danger Utility definition.
			 * @param string              $target Target identifier.
			 * @param mixed               $result Restore result.
			 */
			do_action( 'ttp_after_restore_danger_utility', $item, $target, $result );
		}

		return $result;
	}

	/**
	 * Read posted params.
	 *
	 * @return array<string,mixed>
	 */
	private function get_post_payload() {
		if ( empty( $_POST['ttp_params'] ) || ! is_array( $_POST['ttp_params'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
			return array();
		}

		$raw   = map_deep( wp_unslash( $_POST['ttp_params'] ), 'sanitize_text_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_post().
		$clean = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $key => $value ) {
				if ( is_string( $key ) ) {
					$clean[ $key ] = $value;
				}
			}
		}

		return $clean;
	}

	/**
	 * Render action result notice.
	 *
	 * @return void
	 */
	private function render_action_result() {
		if ( null === $this->action_result ) {
			return;
		}

		if ( is_wp_error( $this->action_result ) ) {
			echo '<div class="ttp-flash ttp-flash--error">' . esc_html( $this->action_result->get_error_message() ) . '</div>';
			return;
		}

		if ( isset( $this->action_result['message'] ) && is_string( $this->action_result['message'] ) ) {
			echo '<div class="ttp-flash ttp-flash--success">' . esc_html( $this->action_result['message'] ) . '</div>';
		}
	}

	/**
	 * Render a generic result table.
	 *
	 * @param mixed $result Result.
	 * @return void
	 */
	private function render_result_table( $result ) {
		if ( is_wp_error( $result ) ) {
			echo '<p class="ttp-note ttp-note--error">' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}

		if ( ! is_array( $result ) ) {
			return;
		}

		if ( empty( $result ) ) {
			echo '<p class="ttp-empty">' . esc_html__( 'No data to display.', 'themeisle-tester' ) . '</p>';
			return;
		}

		echo '<dl class="ttp-defs">';
		foreach ( $result as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$display = is_scalar( $value ) ? (string) $value : '';

			echo '<dt>' . esc_html( (string) $key ) . '</dt><dd>' . esc_html( $display ) . '</dd>';
		}
		echo '</dl>';
	}
}
