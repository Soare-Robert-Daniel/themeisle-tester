<?php
/**
 * Card body presentation for utilities and danger inspect sections.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders inspect and run sections from Testing Item presentation metadata.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Card_Presenter {

	/**
	 * Admin page host for field and inspect renderers.
	 *
	 * @var TTP_Admin_Page
	 */
	private $admin_page;

	/**
	 * Constructor.
	 *
	 * @param TTP_Admin_Page $admin_page Admin page.
	 */
	public function __construct( TTP_Admin_Page $admin_page ) {
		$this->admin_page = $admin_page;
	}

	/**
	 * Render the inspect region for a utility or danger card.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	public function render_inspect_section( $item ) {
		if ( ! is_callable( $item['inspect'] ) ) {
			return;
		}

		$region_id = $this->inspect_region_id( $item['id'] );

		if ( empty( $item['inspect_on_load'] ) ) {
			$this->render_inspect_lazy_shell( $item, $region_id );
			return;
		}

		$inspect_result = call_user_func( $item['inspect'], $item, array() );
		$this->render_inspect_region( $item, $inspect_result, $region_id );
	}

	/**
	 * Render inspect markup inside the morph target region.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item           Item.
	 * @param mixed               $inspect_result Inspect result.
	 * @param string              $region_id      Region element ID.
	 * @return void
	 */
	public function render_inspect_region( $item, $inspect_result, $region_id = '' ) {
		if ( '' === $region_id ) {
			$region_id = $this->inspect_region_id( $item['id'] );
		}

		echo '<div id="' . esc_attr( $region_id ) . '" class="ttp-card__body ttp-card__inspect ttp-card__inspect--loaded" data-ttp-inspect-region>';

		if ( ! empty( $item['inspect_refresh'] ) ) {
			$inspect_path = $this->inspect_rest_path( $item );

			echo '<div class="ttp-inspect-toolbar">';
			echo '<form class="ttp-inline-form" ' . $this->admin_page->datastar_post_attr( $inspect_path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
			echo '>';
			echo '<button type="submit" class="button button-secondary button-small">';
			esc_html_e( 'Refresh', 'themeisle-tester' );
			echo '</button>';
			echo '</form>';
			echo '</div>';
		}

		$this->render_inspect_body( $item, $inspect_result );
		echo '</div>';
	}

	/**
	 * Render inspect body content without the outer region wrapper.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item           Item.
	 * @param mixed               $inspect_result Inspect result.
	 * @return void
	 */
	public function render_inspect_body( $item, $inspect_result ) {
		if ( is_wp_error( $inspect_result ) ) {
			echo '<p class="ttp-note ttp-note--error">' . esc_html( $inspect_result->get_error_message() ) . '</p>';
			return;
		}

		if ( is_callable( $item['render_inspect'] ) ) {
			call_user_func( $item['render_inspect'], $item, $inspect_result, $this->admin_page );
			return;
		}

		$this->admin_page->render_result_table( $inspect_result );
	}

	/**
	 * Render the run form/actions for a utility card.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	public function render_run_section( $item ) {
		if ( ! is_callable( $item['run'] ) ) {
			return;
		}

		if ( is_callable( $item['render_run'] ) ) {
			call_user_func( $item['render_run'], $item, $this->admin_page );
			return;
		}

		$transport = $item['run_ui']['transport'];

		if ( 'zip_batch' === $transport ) {
			$this->render_zip_batch_run( $item );
			return;
		}

		if ( 'progressive' === $transport ) {
			$this->render_progressive_run( $item );
			return;
		}

		$this->render_datastar_run( $item );
	}

	/**
	 * REST path segment for inspect requests.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	public function inspect_rest_path( $item ) {
		if ( 'danger_utility' === $item['type'] ) {
			return 'danger/' . $item['id'] . '/inspect';
		}

		return 'utilities/' . $item['id'] . '/inspect';
	}

	/**
	 * Build the inspect region element ID.
	 *
	 * @param string $item_id Item ID.
	 * @return string
	 */
	public function inspect_region_id( $item_id ) {
		return 'ttp-card-inspect-' . sanitize_html_class( $item_id );
	}

	/**
	 * Render lazy-load shell with a Load button.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item      Item.
	 * @param string              $region_id Region element ID.
	 * @return void
	 */
	private function render_inspect_lazy_shell( $item, $region_id ) {
		$inspect_path = $this->inspect_rest_path( $item );

		echo '<div id="' . esc_attr( $region_id ) . '" class="ttp-card__body ttp-card__inspect ttp-card__inspect--lazy" data-ttp-inspect-region>';
		echo '<p class="ttp-note">' . esc_html__( 'Inspector data is not loaded until you request it.', 'themeisle-tester' ) . '</p>';
		echo '<form class="ttp-inline-form" ' . $this->admin_page->datastar_post_attr( $inspect_path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
		echo '>';
		echo '<button type="submit" class="button button-secondary">';
		esc_html_e( 'Load', 'themeisle-tester' );
		echo '</button>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Default Datastar run form.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	private function render_datastar_run( $item ) {
		$has_fields = ! empty( $item['fields'] );

		if ( $has_fields ) {
			$form_id = 'ttp-utility-' . sanitize_html_class( $item['id'] );
			?>
			<form id="<?php echo esc_attr( $form_id ); ?>" method="post" class="ttp-card__body" <?php echo $this->admin_page->datastar_post_attr( 'utilities/' . $item['id'] . '/run' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
				<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
				<input type="hidden" name="ttp_action" value="run_utility">
				<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<?php $this->admin_page->render_fields( $item, array() ); ?>
			</form>
			<div class="ttp-card__actions">
				<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="button button-primary">
					<?php esc_html_e( 'Run', 'themeisle-tester' ); ?>
				</button>
			</div>
			<?php
			return;
		}
		?>
		<div class="ttp-card__actions">
			<form method="post" class="ttp-inline-form" <?php echo $this->admin_page->datastar_post_attr( 'utilities/' . $item['id'] . '/run' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
				<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
				<input type="hidden" name="ttp_action" value="run_utility">
				<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Run', 'themeisle-tester' ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Progressive run UI: client-driven loop with progress bar.
	 *
	 * Used by any utility whose `run_ui.transport` is `progressive`. The
	 * client fires one POST per step against the form's
	 * `data-ttp-progressive-endpoint`, passing the current step number,
	 * total, and batch id; the server returns progress metadata.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	private function render_progressive_run( $item ) {
		$form_id  = 'ttp-utility-' . sanitize_html_class( $item['id'] );
		$endpoint = 'utilities/' . $item['id'] . '/run';
		?>
		<form
			id="<?php echo esc_attr( $form_id ); ?>"
			method="post"
			class="ttp-card__body"
			data-ttp-progressive-form
			data-ttp-progressive-endpoint="<?php echo esc_attr( $endpoint ); ?>"
		>
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="run_utility">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<?php $this->admin_page->render_fields( $item, array() ); ?>
		</form>
		<div class="ttp-card__actions">
			<div class="ttp-progressive-progress" data-ttp-progressive-progress hidden>
				<div
					class="ttp-progressive-progress__track"
					role="progressbar"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-valuenow="0"
					data-ttp-progressive-progress-track
				>
					<div class="ttp-progressive-progress__bar" data-ttp-progressive-progress-bar style="width: 0%"></div>
				</div>
				<p class="ttp-card__run-status ttp-progressive-progress__status" data-ttp-progressive-status role="status" aria-live="polite"></p>
			</div>
			<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="button button-primary" data-ttp-progressive-submit>
				<?php esc_html_e( 'Run', 'themeisle-tester' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Plugin ZIP install run UI.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	private function render_zip_batch_run( $item ) {
		$form_id        = 'ttp-utility-' . sanitize_html_class( $item['id'] );
		$form_item      = $item;
		$visible_fields = array();
		$hidden_slugs   = array( 'plugin_slug' );

		foreach ( $item['fields'] as $field ) {
			if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				continue;
			}

			if ( in_array( $field['id'], $hidden_slugs, true ) ) {
				continue;
			}

			$visible_fields[] = $field;
		}

		$form_item['fields'] = $visible_fields;
		?>
		<form id="<?php echo esc_attr( $form_id ); ?>" method="post" class="ttp-card__body" data-ttp-zip-install-form>
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="run_utility">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<?php $this->admin_page->render_fields( $form_item, array() ); ?>
		</form>
		<div class="ttp-card__actions">
			<span class="ttp-card__run-status" data-ttp-zip-install-status role="status" aria-live="polite" hidden><?php esc_html_e( 'Installing plugins…', 'themeisle-tester' ); ?></span>
			<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="button button-primary" data-ttp-zip-install-submit>
				<?php esc_html_e( 'Run', 'themeisle-tester' ); ?>
			</button>
		</div>
		<?php
	}
}
