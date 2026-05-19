<?php
/**
 * HTML fragment renderer for Datastar morph responses.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds morphable HTML fragments for Dashboard REST responses.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Dashboard_Renderer {

	/**
	 * Admin page (view partial host).
	 *
	 * @var TTP_Admin_Page
	 */
	private $admin_page;

	/**
	 * Flash notice renderer.
	 *
	 * @var TTP_Flash_Renderer
	 */
	private $flash_renderer;

	/**
	 * Activity log renderer.
	 *
	 * @var TTP_Activity_Renderer
	 */
	private $activity_renderer;

	/**
	 * Registry.
	 *
	 * @var TTP_Item_Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param TTP_Admin_Page        $admin_page         Admin page renderer.
	 * @param TTP_Item_Registry     $registry           Item registry.
	 * @param TTP_Flash_Renderer    $flash_renderer     Flash fragments.
	 * @param TTP_Activity_Renderer $activity_renderer  Activity fragments.
	 */
	public function __construct( TTP_Admin_Page $admin_page, TTP_Item_Registry $registry, TTP_Flash_Renderer $flash_renderer, TTP_Activity_Renderer $activity_renderer ) {
		$this->admin_page        = $admin_page;
		$this->registry          = $registry;
		$this->flash_renderer    = $flash_renderer;
		$this->activity_renderer = $activity_renderer;
	}

	/**
	 * Build a full morph response for a card action.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item acted on.
	 * @param mixed               $result Action result or error.
	 * @return string HTML body.
	 */
	public function render_action_response( $item, $result ) {
		$parts   = array();
		$parts[] = $this->render_flash_fragment( $result );

		if ( ! is_wp_error( $result ) ) {
			$fresh = $this->registry->get_item( $item['id'] );

			if ( null !== $fresh ) {
				$parts[] = $this->render_card_fragment( $fresh );
			}

			if ( 'scenario' === $item['type'] ) {
				foreach ( $item['categories'] as $category ) {
					if ( '' !== $category ) {
						$parts[] = $this->render_tab_indicator_fragment( $category );
					}
				}
			}
		}

		$parts[] = $this->render_activity_fragment();

		return implode( '', $parts );
	}

	/**
	 * Render the flash region fragment.
	 *
	 * @param mixed $result Action result.
	 * @return string
	 */
	public function render_flash_fragment( $result ) {
		ob_start();
		echo '<div id="ttp-flash" class="ttp-flash-region" aria-live="polite">';
		$this->flash_renderer->render_flash_markup( $result );
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Render one card article fragment.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	public function render_card_fragment( $item ) {
		ob_start();
		$this->admin_page->render_view( 'card', array( 'item' => $item ) );
		return (string) ob_get_clean();
	}

	/**
	 * Render one tab active-scenario indicator fragment.
	 *
	 * @param string $category Category label.
	 * @return string
	 */
	public function render_tab_indicator_fragment( $category ) {
		$slug   = sanitize_title( $category );
		$active = $this->count_active_scenarios_in_category( $category );

		ob_start();
		echo '<span id="ttp-tab-indicator-' . esc_attr( $slug ) . '" class="ttp-tab__indicator-slot">';

		if ( $active > 0 ) {
			/* translators: %d: number of active scenarios in this category. */
			$indicator_label = sprintf( _n( '%d active scenario', '%d active scenarios', $active, 'themeisle-tester' ), $active );
			echo '<span class="ttp-tab__indicator" aria-label="' . esc_attr( $indicator_label ) . '"></span>';
		}

		echo '</span>';
		return (string) ob_get_clean();
	}

	/**
	 * Render Dashboard activity fragment.
	 *
	 * @return string
	 */
	public function render_activity_fragment() {
		ob_start();
		$this->activity_renderer->render_activity();
		return (string) ob_get_clean();
	}

	/**
	 * Count enabled Scenarios in one category.
	 *
	 * @param string $category Category label.
	 * @return int
	 */
	private function count_active_scenarios_in_category( $category ) {
		$by_category = $this->registry->get_items_by_category();

		$items = $by_category[ $category ] ?? array();

		return $this->admin_page->count_active_scenarios( $items );
	}
}
