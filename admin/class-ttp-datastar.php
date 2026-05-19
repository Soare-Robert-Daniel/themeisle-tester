<?php
/**
 * Datastar attribute helpers for the Dashboard.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds REST URLs and Datastar HTML attributes for hypermedia actions.
 */
class TTP_Datastar {

	/**
	 * REST URL for a Dashboard action path.
	 *
	 * @param string $path Path relative to ttp/v1 (e.g. scenarios/foo).
	 * @return string
	 */
	public function rest_endpoint( $path ) {
		return rest_url( TTP_REST_Controller::NAMESPACE_NAME . '/' . ltrim( $path, '/' ) );
	}

	/**
	 * Datastar @post() attribute for a form submit handler.
	 *
	 * @param string $path REST path relative to ttp/v1.
	 * @return string HTML attribute string (no leading space).
	 */
	public function datastar_post_attr( $path ) {
		$url   = $this->json_string( $this->rest_endpoint( $path ) );
		$nonce = $this->json_string( wp_create_nonce( 'wp_rest' ) );

		return $this->datastar_on_attr(
			'submit',
			'@post(' . $url . ', {contentType: "form", headers: {"X-WP-Nonce": ' . $nonce . '}})',
			array( 'prevent' )
		);
	}

	/**
	 * Datastar fetch lifecycle attribute for card busy state.
	 *
	 * @return string HTML attribute string (no leading space).
	 */
	public function datastar_busy_attr() {
		return $this->datastar_on_attr(
			'datastar-fetch',
			'if (evt.detail.type === "started") { el.setAttribute("aria-busy", "true"); el.classList.add("ttp-card--busy"); } else if (evt.detail.type === "finished" || evt.detail.type === "error") { el.removeAttribute("aria-busy"); el.classList.remove("ttp-card--busy"); }'
		);
	}

	/**
	 * Render Datastar attributes with centralized escaping.
	 *
	 * @param array<string,string> $attributes Attribute name => expression.
	 * @return string HTML attribute string (no leading space).
	 */
	public function datastar_attrs( $attributes ) {
		$html = array();

		foreach ( $attributes as $name => $value ) {
			$name = $this->sanitize_datastar_attribute_name( (string) $name );

			if ( '' === $name || '' === $value ) {
				continue;
			}

			$html[] = $name . '="' . esc_attr( $value ) . '"';
		}

		return implode( ' ', $html );
	}

	/**
	 * Render a Datastar event binding attribute.
	 *
	 * @param string        $event      Event name after data-on:.
	 * @param string        $expression Datastar expression.
	 * @param array<string> $modifiers  Event modifiers.
	 * @return string HTML attribute string (no leading space).
	 */
	private function datastar_on_attr( $event, $expression, array $modifiers = array() ) {
		$name = 'data-on:' . $event;

		foreach ( $modifiers as $modifier ) {
			$modifier = sanitize_key( $modifier );

			if ( '' !== $modifier ) {
				$name .= '__' . $modifier;
			}
		}

		return $this->datastar_attrs( array( $name => $expression ) );
	}

	/**
	 * Keep generated Datastar attribute names inside the supported shape.
	 *
	 * @param string $name Attribute name.
	 * @return string
	 */
	private function sanitize_datastar_attribute_name( $name ) {
		$name = strtolower( $name );

		return preg_match( '/^data-[a-z0-9:_-]+$/', $name ) ? $name : '';
	}

	/**
	 * Encode a string for a Datastar JavaScript expression.
	 *
	 * @param string $value String value.
	 * @return string JSON string literal.
	 */
	private function json_string( $value ) {
		$encoded = wp_json_encode( $value );

		return is_string( $encoded ) ? $encoded : '""';
	}
}
