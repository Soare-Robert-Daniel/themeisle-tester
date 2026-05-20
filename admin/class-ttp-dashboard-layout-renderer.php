<?php
/**
 * Dashboard tablist, panels, and group grids.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders category tabs and card grids inside panels.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Dashboard_Layout_Renderer {

	/**
	 * View partial loader.
	 *
	 * @var TTP_View_Loader
	 */
	private $view_loader;

	/**
	 * Scenario store.
	 *
	 * @var TTP_Scenario_Store
	 */
	private $scenario_store;

	/**
	 * Constructor.
	 *
	 * @param TTP_View_Loader    $view_loader    View loader.
	 * @param TTP_Scenario_Store $scenario_store Scenario store.
	 */
	public function __construct( TTP_View_Loader $view_loader, TTP_Scenario_Store $scenario_store ) {
		$this->view_loader    = $view_loader;
		$this->scenario_store = $scenario_store;
	}

	/**
	 * Render the tablist + panels for grouped categories.
	 *
	 * @phpstan-param array<string,array<string,NormalizedItem>> $categories
	 *
	 * @param TTP_Admin_Page                                  $page       Dashboard context.
	 * @param array<string,array<string,array<string,mixed>>> $categories Grouped items.
	 * @return void
	 */
	public function render_tabs( TTP_Admin_Page $page, $categories ) {
		$tabs = array();

		foreach ( $categories as $category => $items ) {
			$slug   = sanitize_title( $category );
			$tabs[] = array(
				'category' => $category,
				'items'    => $items,
				'tab_id'   => 'ttp-tab-' . $slug,
				'panel_id' => 'ttp-panel-' . $slug,
				'active'   => $this->count_active_scenarios( $items ),
			);
		}

		$this->view_loader->render( $page, 'tabs', array( 'tabs' => $tabs ) );
	}

	/**
	 * Render the items inside a panel, splitting into sub-grids by 'group' value.
	 *
	 * If items share a single group (or none), this renders one grid. When two or
	 * more distinct non-empty groups exist, each gets a labelled subheading + divider.
	 *
	 * @phpstan-param array<string,NormalizedItem> $items
	 *
	 * @param TTP_Admin_Page                    $page  Dashboard context.
	 * @param array<string,array<string,mixed>> $items Items keyed by ID, in registration order.
	 * @return void
	 */
	public function render_panel_groups( TTP_Admin_Page $page, $items ) {
		$groups = array();

		foreach ( $items as $item ) {
			$key = $item['group'];

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
			}

			$groups[ $key ][] = $item;
		}

		$show_headings = count( $groups ) > 1 && ! array_key_exists( '', $groups );

		echo '<div class="ttp-panel__grid">';

		foreach ( $groups as $label => $group_items ) {
			if ( $show_headings && '' !== $label ) {
				$count_total  = count( $group_items );
				$count_active = $this->count_active_scenarios_in_list( $group_items );

				/* translators: %d: total number of testing items in a group. */
				$count_label = sprintf( _n( '%d item', '%d items', $count_total, 'themeisle-tester' ), $count_total );

				echo '<h3 class="ttp-panel__group-title">';
				echo '<span class="ttp-panel__group-title-text">' . esc_html( $label ) . '</span>';
				echo '<span class="ttp-panel__group-meta">';
				echo '<span class="ttp-panel__group-count">' . esc_html( $count_label ) . '</span>';

				if ( $count_active > 0 ) {
					/* translators: %d: number of active scenarios in a group. */
					$active_label = sprintf( _n( '%d active', '%d active', $count_active, 'themeisle-tester' ), $count_active );
					echo '<span class="ttp-panel__group-active"><span class="ttp-panel__group-active-dot" aria-hidden="true"></span>' . esc_html( $active_label ) . '</span>';
				}

				echo '</span>';
				echo '</h3>';
			}

			foreach ( $group_items as $item ) {
				$this->view_loader->render( $page, 'card', array( 'item' => $item ) );
			}
		}

		echo '</div>';
	}

	/**
	 * Count enabled Scenarios in a flat list of normalised items.
	 *
	 * @phpstan-param array<int,NormalizedItem> $items
	 *
	 * @param array<int,array<string,mixed>> $items Items.
	 * @return int
	 */
	private function count_active_scenarios_in_list( array $items ) {
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
	 * Count enabled Scenarios in a category bucket.
	 *
	 * @phpstan-param array<string,NormalizedItem> $items
	 *
	 * @param array<string,array<string,mixed>> $items Items keyed by ID.
	 * @return int
	 */
	public function count_active_scenarios( $items ) {
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
}
