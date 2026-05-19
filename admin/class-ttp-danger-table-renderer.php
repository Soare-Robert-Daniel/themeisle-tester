<?php
/**
 * Danger Utility data-table renderer for the Dashboard.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders license/install rows and inline mutate/restore forms.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Danger_Table_Renderer {

	/**
	 * Datastar attribute helpers.
	 *
	 * @var TTP_Datastar
	 */
	private $datastar;

	/**
	 * Danger backup store.
	 *
	 * @var TTP_Danger_Backup_Store
	 */
	private $backup_store;

	/**
	 * Constructor.
	 *
	 * @param TTP_Datastar            $datastar     Datastar helpers.
	 * @param TTP_Danger_Backup_Store $backup_store Backup store.
	 */
	public function __construct( TTP_Datastar $datastar, TTP_Danger_Backup_Store $backup_store ) {
		$this->datastar     = $datastar;
		$this->backup_store = $backup_store;
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
						<td><code class="ttp-mono"><?php echo esc_html( $target ); ?></code></td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><code class="ttp-mono"><?php echo esc_html( $key_display ); ?></code></td>
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
	public function render_install_rows( $item, $rows ) {
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
							<code class="ttp-mono"><?php echo esc_html( $target ); ?></code>
							<?php if ( '' !== $timestamp ) : ?>
								<br><span class="ttp-data-table__muted ttp-mono"><?php echo esc_html( $timestamp ); ?></span>
							<?php endif; ?>
						</td>
						<td><code class="ttp-mono"><?php echo esc_html( $date ); ?></code></td>
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
		<form method="post" class="ttp-data-table__form" <?php echo $this->datastar->datastar_post_attr( 'danger/' . $item['id'] . '/mutate' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
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
	 * Render the embedded force license refresh action (inline on install timestamp card).
	 *
	 * @return void
	 */
	public function render_force_license_refresh_action() {
		$utility_id = 'force_license_refresh';
		$working    = __( 'Refreshing…', 'themeisle-tester' );
		$help       = __( 'Clears ti_license_cache and POSTs to /wp-json/ti/v1/license/refresh.', 'themeisle-tester' );

		?>
		<form
			method="post"
			class="ttp-card__toolbar-form"
			data-ttp-license-refresh-toolbar
			data-ttp-license-refresh-form
			<?php echo $this->datastar->datastar_post_attr( 'utilities/' . $utility_id . '/run' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
		>
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="run_utility">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $utility_id ); ?>">
			<span class="ttp-card__toolbar-lead"><?php esc_html_e( 'License cache', 'themeisle-tester' ); ?></span>
			<button
				type="submit"
				class="button button-secondary button-small"
				data-ttp-license-refresh-submit
				data-ttp-default-label="<?php echo esc_attr__( 'Force refresh', 'themeisle-tester' ); ?>"
				data-ttp-working-label="<?php echo esc_attr( $working ); ?>"
				title="<?php echo esc_attr( $help ); ?>"
			>
				<?php esc_html_e( 'Force refresh', 'themeisle-tester' ); ?>
			</button>
			<span class="ttp-card__toolbar-status" data-ttp-license-refresh-status role="status" aria-live="polite" hidden data-ttp-working-label="<?php echo esc_attr( $working ); ?>"></span>
		</form>
		<?php
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
		<form method="post" class="ttp-data-table__form" <?php echo $this->datastar->datastar_post_attr( 'danger/' . $item['id'] . '/restore' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="restore_danger">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<input type="hidden" name="ttp_target" value="<?php echo esc_attr( $target ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Restore', 'themeisle-tester' ); ?></button>
		</form>
		<?php
	}
}
