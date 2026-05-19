<?php
/**
 * Field schema sanitizer.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes Testing Item params against field schemas.
 */
class TTP_Schema_Sanitizer {

	/**
	 * Sanitize params for a Testing Item.
	 *
	 * @param array<string,mixed> $item   Item definition.
	 * @param array<string,mixed> $params Raw params.
	 * @return array<string,mixed>|WP_Error
	 */
	public function sanitize_params( $item, $params ) {
		$fields    = isset( $item['fields'] ) && is_array( $item['fields'] ) ? $item['fields'] : array();
		$sanitized = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				continue;
			}

			$id      = sanitize_key( $field['id'] );
			$type    = isset( $field['type'] ) && is_string( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
			$value   = isset( $params[ $id ] ) ? $params[ $id ] : '';
			$allowed = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();

			if ( $this->should_apply_field_default( $value, $type ) && array_key_exists( 'default', $field ) ) {
				$value = $field['default'];
			}

			$sanitized_value = $this->sanitize_value( $value, $type, $allowed );

			if ( is_wp_error( $sanitized_value ) ) {
				return $sanitized_value;
			}

			$sanitized[ $id ] = $sanitized_value;
		}

		return $sanitized;
	}

	/**
	 * Sanitize one value.
	 *
	 * @param mixed                   $value   Raw value.
	 * @param string                  $type    Field type.
	 * @param array<int|string,mixed> $allowed Allowed options.
	 * @return mixed|WP_Error
	 */
	private function sanitize_value( $value, $type, $allowed ) {
		if ( 'boolean' === $type || 'toggle' === $type ) {
			return (bool) $value;
		}

		if ( 'integer' === $type ) {
			return is_numeric( $value ) ? (int) $value : 0;
		}

		if ( 'number' === $type ) {
			return is_numeric( $value ) ? (float) $value : 0.0;
		}

		if ( 'date' === $type ) {
			$value = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );

			if ( '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				return new WP_Error( 'ttp_invalid_date', __( 'Date values must use YYYY-MM-DD format.', 'themeisle-tester' ) );
			}

			return $value;
		}

		if ( 'select' === $type ) {
			$value          = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
			$allowed_values = array();

			foreach ( $allowed as $option_key => $option_value ) {
				if ( is_array( $option_value ) && isset( $option_value['value'] ) && is_scalar( $option_value['value'] ) ) {
					$allowed_values[] = (string) $option_value['value'];
				} elseif ( is_string( $option_key ) ) {
					$allowed_values[] = $option_key;
				} elseif ( is_scalar( $option_value ) ) {
					$allowed_values[] = (string) $option_value;
				}
			}

			if ( '' !== $value && ! in_array( $value, $allowed_values, true ) ) {
				return new WP_Error( 'ttp_invalid_select', __( 'Selected value is not allowed.', 'themeisle-tester' ) );
			}

			return $value;
		}

		if ( 'array' === $type || 'json' === $type ) {
			return is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : array();
		}

		if ( 'url' === $type ) {
			return esc_url_raw( is_scalar( $value ) ? (string) $value : '' );
		}

		if ( 'url_list' === $type ) {
			$lines = array();

			if ( is_array( $value ) ) {
				foreach ( $value as $entry ) {
					if ( is_string( $entry ) ) {
						$lines[] = $entry;
					}
				}
			} elseif ( is_string( $value ) ) {
				$split = preg_split( '/\r\n|\r|\n/', $value );
				$lines = is_array( $split ) ? $split : array();
			}

			$urls = array();

			foreach ( $lines as $line ) {
				$trimmed = trim( (string) $line );

				if ( '' === $trimmed ) {
					continue;
				}

				$clean = esc_url_raw( $trimmed );

				if ( '' !== $clean ) {
					$urls[] = $clean;
				}
			}

			return $urls;
		}

		return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Whether an empty posted value should fall back to the field default.
	 *
	 * @param mixed  $value Raw param value.
	 * @param string $type  Field type.
	 * @return bool
	 */
	private function should_apply_field_default( $value, $type ) {
		if ( 'toggle' === $type || 'boolean' === $type ) {
			return false;
		}

		if ( 'integer' === $type || 'number' === $type ) {
			return ! is_numeric( $value );
		}

		if ( is_string( $value ) ) {
			return '' === $value;
		}

		return ! is_scalar( $value ) || '' === (string) $value;
	}
}
