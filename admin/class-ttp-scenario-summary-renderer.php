<?php
/**
 * Scenario saved-params summary renderer.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the compact "Saved" summary on Scenario cards.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Scenario_Summary_Renderer {

	/**
	 * Render a compact "Saved" summary listing the scenario's currently stored params.
	 *
	 * Output is skipped entirely when no field has a non-empty saved value, so cards
	 * without saved state stay clean.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item   Item.
	 * @param array<string,mixed> $params Saved params keyed by field id.
	 * @return void
	 */
	public function render_saved_summary( $item, $params ) {
		if ( empty( $item['fields'] ) ) {
			echo '<p class="ttp-readout ttp-readout--empty">' . esc_html__( 'No parameters.', 'themeisle-tester' ) . '</p>';
			return;
		}

		$pairs = array();

		foreach ( $item['fields'] as $field ) {
			if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				continue;
			}

			$id      = sanitize_key( $field['id'] );
			$label   = isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $id;
			$type    = isset( $field['type'] ) && is_string( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
			$display = array_key_exists( $id, $params )
				? $this->format_saved_value( $type, $params[ $id ] )
				: '';

			$pairs[] = array(
				'key'   => $id,
				'label' => $label,
				'value' => $display,
			);
		}

		if ( empty( $pairs ) ) {
			echo '<p class="ttp-readout ttp-readout--empty">' . esc_html__( 'Not configured.', 'themeisle-tester' ) . '</p>';
			return;
		}

		echo '<dl class="ttp-readout">';
		foreach ( $pairs as $pair ) {
			echo '<div class="ttp-readout__row">';
			echo '<dt class="ttp-readout__key">' . esc_html( $pair['label'] ) . '</dt>';

			if ( '' === $pair['value'] ) {
				echo '<dd class="ttp-readout__value ttp-readout__value--empty">' . esc_html__( '(unset)', 'themeisle-tester' ) . '</dd>';
			} else {
				echo '<dd class="ttp-readout__value">' . esc_html( $pair['value'] ) . '</dd>';
			}

			echo '</div>';
		}
		echo '</dl>';
	}

	/**
	 * Format a saved param value for display in the "Saved" summary.
	 *
	 * Returns an empty string when the value is effectively absent (empty string,
	 * empty array, null). Callers skip pairs that render to an empty string.
	 *
	 * @param string $type  Field type from the registered schema.
	 * @param mixed  $value Raw saved value.
	 * @return string
	 */
	private function format_saved_value( $type, $value ) {
		if ( 'toggle' === $type || 'boolean' === $type ) {
			return filter_var( $value, FILTER_VALIDATE_BOOLEAN )
				? __( 'on', 'themeisle-tester' )
				: __( 'off', 'themeisle-tester' );
		}

		if ( 'url_list' === $type ) {
			$lines = array();

			if ( is_array( $value ) ) {
				foreach ( $value as $entry ) {
					if ( is_string( $entry ) && '' !== trim( $entry ) ) {
						$lines[] = trim( $entry );
					}
				}
			} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
				$pieces = preg_split( '/\r\n|\r|\n/', $value );

				if ( is_array( $pieces ) ) {
					foreach ( $pieces as $entry ) {
						$entry = trim( (string) $entry );
						if ( '' !== $entry ) {
							$lines[] = $entry;
						}
					}
				}
			}

			if ( empty( $lines ) ) {
				return '';
			}

			$count = count( $lines );

			if ( $count <= 2 ) {
				return implode( ', ', $lines );
			}

			/* translators: 1: first URL, 2: number of additional URLs. */
			return sprintf( _n( '%1$s (+%2$d more)', '%1$s (+%2$d more)', $count - 1, 'themeisle-tester' ), $lines[0], $count - 1 );
		}

		if ( is_scalar( $value ) ) {
			$out = trim( (string) $value );

			return '' === $out ? '' : $out;
		}

		return '';
	}
}
