<?php
/**
 * PPOM field-group inspect renderer for the Dashboard.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders PPOM inspect output as structured groups with pretty-printed JSON.
 */
class TTP_PPOM_Inspect_Renderer {

	/**
	 * Maximum field groups shown per inspect page.
	 */
	private const GROUPS_PER_PAGE = 5;

	/**
	 * Keys shown in the per-group metadata list (excludes fields and raw JSON).
	 *
	 * @var list<string>
	 */
	private const GROUP_META_KEYS = array(
		'productmeta_id',
		'productmeta_name',
		'productmeta_disabled',
		'productmeta_validation',
		'productmeta_created',
		'productmeta_categories',
		'productmeta_tags',
		'dynamic_price_display',
		'send_file_attachment',
		'show_cart_thumb',
		'field_count',
	);

	/**
	 * Render PPOM field-group inspect results.
	 *
	 * @param mixed $result Inspect callback result.
	 * @return void
	 */
	public function render_field_groups_result( $result ) {
		if ( is_wp_error( $result ) ) {
			echo '<p class="ttp-note ttp-note--error">' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}

		if ( ! is_array( $result ) ) {
			return;
		}

		echo '<div class="ttp-ppom-inspect">';

		$this->render_summary( $result );

		if ( empty( $result['groups'] ) || ! is_array( $result['groups'] ) ) {
			echo '</div>';
			return;
		}

		$groups = array_values(
			array_filter(
				$result['groups'],
				static function ( $group ) {
					return is_array( $group );
				}
			)
		);

		echo '<div class="ttp-ppom-inspect__pager" data-ttp-ppom-pagination data-ttp-ppom-per-page="' . esc_attr( (string) self::GROUPS_PER_PAGE ) . '">';
		echo '<div class="ttp-ppom-inspect__groups">';
		foreach ( $groups as $group ) {
			$this->render_group( $group );
		}
		echo '</div>';
		$this->render_pagination_nav( count( $groups ) );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render client-side pagination controls for field groups.
	 *
	 * @param int $group_count Total groups in the inspect result.
	 * @return void
	 */
	private function render_pagination_nav( $group_count ) {
		if ( $group_count <= self::GROUPS_PER_PAGE ) {
			return;
		}

		$total_pages = (int) ceil( $group_count / self::GROUPS_PER_PAGE );

		/* translators: 1: current page number, 2: total page count. */
		$page_status_format = __( 'Page %1$d of %2$d', 'themeisle-tester' );

		?>
		<nav
			class="ttp-ppom-pagination"
			data-ttp-ppom-pagination-nav
			data-ttp-ppom-status-format="<?php echo esc_attr( $page_status_format ); ?>"
			aria-label="<?php esc_attr_e( 'PPOM field group pages', 'themeisle-tester' ); ?>"
		>
			<button type="button" class="button" data-ttp-ppom-prev disabled>
				<?php esc_html_e( 'Previous', 'themeisle-tester' ); ?>
			</button>
			<span class="ttp-ppom-pagination__status" data-ttp-ppom-page-status role="status" aria-live="polite">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current page number, 2: total page count. */
						__( 'Page %1$d of %2$d', 'themeisle-tester' ),
						1,
						$total_pages
					)
				);
				?>
			</span>
			<button type="button" class="button" data-ttp-ppom-next>
				<?php esc_html_e( 'Next', 'themeisle-tester' ); ?>
			</button>
		</nav>
		<?php
	}

	/**
	 * Render top-level summary fields.
	 *
	 * @param array<mixed,mixed> $result Inspect result.
	 * @return void
	 */
	private function render_summary( array $result ) {
		$summary_keys = array( 'database_table', 'group_count', '_note' );

		echo '<dl class="ttp-defs ttp-ppom-inspect__summary">';
		foreach ( $summary_keys as $key ) {
			if ( ! array_key_exists( $key, $result ) ) {
				continue;
			}

			$value = $result[ $key ];
			if ( is_array( $value ) ) {
				continue;
			}

			$display = is_scalar( $value ) ? (string) $value : '';
			if ( '' === $display ) {
				continue;
			}

			echo '<dt>' . esc_html( $key ) . '</dt>';
			echo '<dd>' . esc_html( $display ) . '</dd>';
		}
		echo '</dl>';
	}

	/**
	 * Render one field group block.
	 *
	 * @param array<mixed,mixed> $group Group row from inspect.
	 * @return void
	 */
	private function render_group( array $group ) {
		$group_id    = isset( $group['productmeta_id'] ) && is_numeric( $group['productmeta_id'] ) ? (int) $group['productmeta_id'] : 0;
		$group_name  = isset( $group['productmeta_name'] ) && is_string( $group['productmeta_name'] ) ? $group['productmeta_name'] : '';
		$field_count = isset( $group['field_count'] ) && is_numeric( $group['field_count'] ) ? (int) $group['field_count'] : 0;

		$title = '' !== $group_name
			/* translators: 1: numeric group ID, 2: group name. */
			? sprintf( __( '#%1$d — %2$s', 'themeisle-tester' ), $group_id, $group_name )
			/* translators: %d: numeric group ID. */
			: sprintf( __( '#%d', 'themeisle-tester' ), $group_id );

		?>
		<details class="ttp-ppom-group" data-ttp-ppom-group>
			<summary class="ttp-ppom-group__summary">
				<span class="ttp-ppom-group__title"><?php echo esc_html( $title ); ?></span>
				<span class="ttp-ppom-group__count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of fields in the group. */
							_n( '%d field', '%d fields', $field_count, 'themeisle-tester' ),
							$field_count
						)
					);
					?>
				</span>
			</summary>
			<div class="ttp-ppom-group__body">
				<?php $this->render_group_meta( $group ); ?>
				<?php $this->render_group_fields_table( $group ); ?>
				<?php $this->render_group_raw_json( $group ); ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Render group row metadata (excluding fields and the_meta JSON).
	 *
	 * @param array<mixed,mixed> $group Group row.
	 * @return void
	 */
	private function render_group_meta( array $group ) {
		echo '<dl class="ttp-defs ttp-defs--compact">';
		foreach ( self::GROUP_META_KEYS as $key ) {
			if ( ! array_key_exists( $key, $group ) ) {
				continue;
			}

			$value = $group[ $key ];
			if ( is_array( $value ) ) {
				continue;
			}

			$display = is_scalar( $value ) ? (string) $value : '';
			if ( '' === $display && 'productmeta_disabled' !== $key ) {
				continue;
			}

			echo '<dt>' . esc_html( $key ) . '</dt>';
			echo '<dd>' . esc_html( $display ) . '</dd>';
		}
		echo '</dl>';
	}

	/**
	 * Render decoded fields as a data table.
	 *
	 * @param array<mixed,mixed> $group Group row.
	 * @return void
	 */
	private function render_group_fields_table( array $group ) {
		if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
			return;
		}

		?>
		<table class="ttp-data-table ttp-ppom-group__fields">
			<thead>
				<tr>
					<th><?php esc_html_e( 'data_name', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'type', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'title', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'status', 'themeisle-tester' ); ?></th>
					<th><?php esc_html_e( 'ppom_id', 'themeisle-tester' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $group['fields'] as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}

					$data_name = isset( $field['data_name'] ) && is_string( $field['data_name'] ) ? $field['data_name'] : '';
					$type      = isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : '';
					$title     = isset( $field['title'] ) && is_string( $field['title'] ) ? $field['title'] : '';
					$status    = isset( $field['status'] ) && is_string( $field['status'] ) ? $field['status'] : '';
					$ppom_id   = isset( $field['ppom_id'] ) && ( is_string( $field['ppom_id'] ) || is_int( $field['ppom_id'] ) ) ? (string) $field['ppom_id'] : '';
					?>
					<tr>
						<td><code><?php echo esc_html( $data_name ); ?></code></td>
						<td><?php echo esc_html( $type ); ?></td>
						<td><?php echo esc_html( $title ); ?></td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><?php echo esc_html( $ppom_id ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render pretty-printed the_meta JSON in a collapsible block.
	 *
	 * @param array<mixed,mixed> $group Group row.
	 * @return void
	 */
	private function render_group_raw_json( array $group ) {
		if ( empty( $group['the_meta_json'] ) || ! is_string( $group['the_meta_json'] ) ) {
			return;
		}

		$pretty = $this->pretty_json_string( $group['the_meta_json'] );
		if ( '' === $pretty ) {
			return;
		}

		?>
		<details class="ttp-ppom-raw">
			<summary class="ttp-ppom-raw__summary"><?php esc_html_e( 'the_meta (raw JSON)', 'themeisle-tester' ); ?></summary>
			<pre class="ttp-code-block" tabindex="0"><?php echo esc_html( $pretty ); ?></pre>
		</details>
		<?php
	}

	/**
	 * Pretty-print a JSON string or encode an array/object.
	 *
	 * @param mixed $value JSON string or structure.
	 * @return string
	 */
	private function pretty_json_string( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$value = $decoded;
			}
		}

		if ( ! is_array( $value ) && ! is_object( $value ) ) {
			return is_scalar( $value ) ? (string) $value : '';
		}

		$flags   = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
		$encoded = wp_json_encode( $value, $flags );

		return is_string( $encoded ) ? $encoded : '';
	}
}
