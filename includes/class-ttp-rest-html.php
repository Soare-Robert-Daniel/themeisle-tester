<?php
/**
 * REST HTML fragment negotiation for Datastar morph responses.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects HTML accept headers and serves raw fragment bodies for Dashboard REST.
 */
class TTP_REST_Html {

	/**
	 * REST namespace used to scope HTML serving.
	 *
	 * @var string
	 */
	private $rest_namespace;

	/**
	 * Constructor.
	 *
	 * @param string $rest_namespace REST namespace (e.g. ttp/v1).
	 */
	public function __construct( $rest_namespace ) {
		$this->rest_namespace = $rest_namespace;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_html_response' ), 10, 4 );
	}

	/**
	 * Whether the client wants an HTML morph response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function wants_html( WP_REST_Request $request ) {
		$accept = $request->get_header( 'accept' );

		if ( is_string( $accept ) && false !== stripos( $accept, 'text/html' ) ) {
			return true;
		}

		return 'true' === $request->get_header( 'datastar-request' );
	}

	/**
	 * Build an HTML REST response for Datastar morphing.
	 *
	 * @param string $body HTML fragment(s).
	 * @return WP_REST_Response
	 */
	public function html_response( $body ) {
		$charset        = 'UTF-8';
		$charset_option = get_option( 'blog_charset' );

		if ( is_string( $charset_option ) && '' !== $charset_option ) {
			$charset = $charset_option;
		}

		$response = new WP_REST_Response( $body, 200 );
		$response->set_headers(
			array(
				'Content-Type'   => 'text/html; charset=' . $charset,
				'X-TTP-Response' => 'html-fragments',
			)
		);

		return $response;
	}

	/**
	 * Serve Dashboard HTML fragment responses without REST JSON encoding.
	 *
	 * WordPress REST serializes response data by default. Datastar expects raw
	 * HTML for text/html responses, so these fragments must bypass JSON output.
	 *
	 * @param bool             $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param WP_REST_Server   $server  REST server instance.
	 * @return bool
	 */
	public function serve_html_response( $served, $result, $request, $server ) {
		if ( $served ) {
			return $served;
		}

		$route = $request->get_route();

		if ( 0 !== strpos( $route, '/' . $this->rest_namespace . '/' ) ) {
			return $served;
		}

		$headers = $result->get_headers();
		$marker  = isset( $headers['X-TTP-Response'] ) && 'html-fragments' === $headers['X-TTP-Response'];
		$data    = $result->get_data();

		if ( ! $marker || ! is_string( $data ) ) {
			return $served;
		}

		$server->send_header( 'Content-Type', isset( $headers['Content-Type'] ) && is_string( $headers['Content-Type'] ) ? $headers['Content-Type'] : 'text/html; charset=UTF-8' );

		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fragment markup is escaped by the Dashboard renderer.

		return true;
	}
}
