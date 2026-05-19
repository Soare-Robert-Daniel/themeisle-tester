<?php
/**
 * Quick-install plugin shortcuts for the ZIP install utility card.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders compact one-click shortcuts for curated popular plugins.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Popular_Plugins_Renderer {

	/**
	 * Datastar attribute helpers.
	 *
	 * @var TTP_Datastar
	 */
	private $datastar;

	/**
	 * Constructor.
	 *
	 * @param TTP_Datastar $datastar Datastar helpers.
	 */
	public function __construct( TTP_Datastar $datastar ) {
		$this->datastar = $datastar;
	}

	/**
	 * Render quick-install shortcuts above the ZIP URL form.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item      Item definition.
	 * @param array<mixed>        $shortcuts Shortcut rows from inspect.
	 * @return void
	 */
	public function render_plugin_install_shortcuts( $item, $shortcuts ) {
		$pending = array();

		foreach ( $shortcuts as $row ) {
			$normalized = $this->normalize_shortcut_row( $row );

			if ( null === $normalized || 'active' === $normalized['status'] ) {
				continue;
			}

			$pending[] = $normalized;
		}

		if ( empty( $pending ) ) {
			return;
		}

		?>
		<div class="ttp-plugin-shortcuts">
			<p class="ttp-plugin-shortcuts__label"><?php esc_html_e( 'Quick install', 'themeisle-tester' ); ?></p>
			<ul class="ttp-plugin-shortcuts__list">
				<?php
				foreach ( $pending as $normalized ) {
					$this->render_shortcut_item( $item, $normalized );
				}
				?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Coerce an inspect row to a known shape.
	 *
	 * @param mixed $row Raw inspect row.
	 * @return array{slug:string,name:string,status:string}|null
	 */
	private function normalize_shortcut_row( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$slug   = isset( $row['slug'] ) && is_string( $row['slug'] ) ? $row['slug'] : '';
		$name   = isset( $row['name'] ) && is_string( $row['name'] ) ? $row['name'] : $slug;
		$status = isset( $row['status'] ) && is_string( $row['status'] ) ? $row['status'] : 'not_installed';

		if ( '' === $slug ) {
			return null;
		}

		return array(
			'slug'   => $slug,
			'name'   => $name,
			'status' => $status,
		);
	}

	/**
	 * Render one shortcut list item.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed>                          $item Item.
	 * @param array{slug:string,name:string,status:string} $row  Shortcut row.
	 * @return void
	 */
	private function render_shortcut_item( $item, $row ) {
		$slug   = $row['slug'];
		$name   = $row['name'];
		$status = $row['status'];

		$is_inactive = 'inactive' === $status;

		$button_label = $is_inactive
			? __( 'Activate', 'themeisle-tester' )
			: __( 'Install & activate', 'themeisle-tester' );

		$working_label = $is_inactive
			? __( 'Activating…', 'themeisle-tester' )
			: __( 'Installing…', 'themeisle-tester' );

		?>
		<li
			class="ttp-plugin-shortcuts__item"
			data-ttp-shortcut
			data-ttp-shortcut-state="<?php echo esc_attr( $status ); ?>"
			data-ttp-shortcut-working="<?php echo esc_attr( $working_label ); ?>"
		>
			<span class="ttp-plugin-shortcuts__name"><?php echo esc_html( $name ); ?></span>
			<span class="ttp-plugin-shortcuts__status" data-ttp-shortcut-status role="status" aria-live="polite" hidden></span>
			<?php $this->render_shortcut_action( $item, $slug, $button_label, $working_label ); ?>
		</li>
		<?php
	}

	/**
	 * Render per-shortcut install/activate form.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item         Item.
	 * @param string              $slug         Catalog slug.
	 * @param string              $button_label  Submit button label.
	 * @param string              $working_label In-progress label.
	 * @return void
	 */
	private function render_shortcut_action( $item, $slug, $button_label, $working_label ) {
		?>
		<form method="post" class="ttp-plugin-shortcuts__form" data-ttp-shortcut-form <?php echo $this->datastar->datastar_post_attr( 'utilities/' . $item['id'] . '/run' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="run_utility">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<input type="hidden" name="ttp_params[plugin_slug]" value="<?php echo esc_attr( $slug ); ?>">
			<button type="submit" class="button button-secondary button-small" data-ttp-shortcut-submit data-ttp-shortcut-default="<?php echo esc_attr( $button_label ); ?>" data-ttp-shortcut-working="<?php echo esc_attr( $working_label ); ?>"><?php echo esc_html( $button_label ); ?></button>
		</form>
		<?php
	}
}
