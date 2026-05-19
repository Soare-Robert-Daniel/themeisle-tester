<?php
/**
 * Dashboard field rendering.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders Testing Item form fields for Dashboard cards.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Field_Renderer {

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

			if ( 'url_list' === $type ) {
				$this->render_url_list_field( $id, $label_value, $raw_value );
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
				$input_type = in_array( $type, array( 'date', 'url', 'email', 'number' ), true ) ? $type : 'text';
				echo '<input id="' . esc_attr( $control_id ) . '" type="' . esc_attr( $input_type ) . '" name="ttp_params[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '">';
			}

			echo '</div>';
		}
	}

	/**
	 * Render a `url_list` field as repeatable URL rows with [×] + Add controls.
	 *
	 * The DOM exposes data attributes (`data-ttp-list`, `data-ttp-list-row`,
	 * `data-ttp-list-add`, `data-ttp-list-remove`, `data-ttp-list-template`)
	 * that `admin/js/dashboard.js` hooks for client-side add/remove.
	 *
	 * @param string $id        Sanitized field id.
	 * @param string $label     Human label.
	 * @param mixed  $raw_value Saved/posted value (string|array|other).
	 * @return void
	 */
	private function render_url_list_field( $id, $label, $raw_value ) {
		$lines = array();

		if ( is_array( $raw_value ) ) {
			foreach ( $raw_value as $entry ) {
				if ( is_string( $entry ) && '' !== trim( $entry ) ) {
					$lines[] = trim( $entry );
				}
			}
		} elseif ( is_string( $raw_value ) && '' !== trim( $raw_value ) ) {
			$lines[] = trim( $raw_value );
		}

		if ( empty( $lines ) ) {
			$lines[] = '';
		}

		$name        = 'ttp_params[' . $id . '][]';
		$placeholder = __( 'https://example.com/plugin.zip', 'themeisle-tester' );
		$remove_lbl  = __( 'Remove URL', 'themeisle-tester' );

		echo '<div class="ttp-card__field ttp-card__field--list" data-ttp-list data-ignore>';
		echo '<span class="ttp-card__field-label">' . esc_html( $label ) . '</span>';
		echo '<div class="ttp-list" data-ttp-list-rows>';

		foreach ( $lines as $line ) {
			echo '<div class="ttp-list__row" data-ttp-list-row>';
			echo '<input type="url" name="' . esc_attr( $name ) . '" value="' . esc_attr( $line ) . '" placeholder="' . esc_attr( $placeholder ) . '">';
			echo '<button type="button" class="ttp-list__remove" data-ttp-list-remove aria-label="' . esc_attr( $remove_lbl ) . '">&times;</button>';
			echo '</div>';
		}

		echo '</div>';
		echo '<button type="button" class="ttp-list__add" data-ttp-list-add>+ ' . esc_html__( 'Add another', 'themeisle-tester' ) . '</button>';

		// Template cloned by JS for new rows.
		echo '<template data-ttp-list-template>';
		echo '<div class="ttp-list__row" data-ttp-list-row>';
		echo '<input type="url" name="' . esc_attr( $name ) . '" value="" placeholder="' . esc_attr( $placeholder ) . '">';
		echo '<button type="button" class="ttp-list__remove" data-ttp-list-remove aria-label="' . esc_attr( $remove_lbl ) . '">&times;</button>';
		echo '</div>';
		echo '</template>';
		echo '</div>';
	}
}
