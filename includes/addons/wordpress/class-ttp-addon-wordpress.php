<?php
/**
 * WordPress platform Testing Items (install from ZIP, etc.).
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WordPress admin utilities for QA workflows.
 */
class TTP_Addon_WordPress implements TTP_Addon {

	/**
	 * Register WordPress Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$install_licensing = __( 'Install & Licensing', 'themeisle-tester' );
		$wordpress         = __( 'WordPress', 'themeisle-tester' );

		$registry->register(
			array(
				'id'          => 'install_plugin_from_zip',
				'type'        => 'utility',
				'categories'  => array( $install_licensing ),
				'product'     => $wordpress,
				'label'       => __( 'Install plugins from ZIP URLs', 'themeisle-tester' ),
				'description' => __( 'Downloads, installs, and optionally activates one or more plugins from ZIP URLs.', 'themeisle-tester' ),
				'width'       => 'wide',
				'fields'      => array(
					array(
						'id'    => 'zip_urls',
						'type'  => 'url_list',
						'label' => __( 'ZIP URLs (one per line, https://…/plugin.zip)', 'themeisle-tester' ),
					),
					array(
						'id'    => 'activate',
						'type'  => 'toggle',
						'label' => __( 'Activate after installation', 'themeisle-tester' ),
					),
				),
				'run'         => array( $this, 'run_install_plugin_from_zip' ),
			)
		);
	}

	/**
	 * Install (and optionally activate) plugins from one or more ZIP URLs.
	 *
	 * Each URL is processed independently; failures on one do not abort the
	 * rest. The result includes a summary message and a 'details' array with
	 * one human-readable string per URL.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Posted params.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_install_plugin_from_zip( $item, $payload ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'ttp_install_forbidden', __( 'You do not have permission to install plugins on this site.', 'themeisle-tester' ) );
		}

		$raw_urls = isset( $payload['zip_urls'] ) ? $payload['zip_urls'] : null;
		$urls     = $this->extract_url_list( $raw_urls );
		$activate = ! empty( $payload['activate'] );

		if ( empty( $urls ) ) {
			return new WP_Error( 'ttp_missing_zip_url', __( 'Provide at least one ZIP URL to install.', 'themeisle-tester' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'ttp_filesystem_unavailable', __( 'The site filesystem is not writable; cannot install plugins from here.', 'themeisle-tester' ) );
		}

		$details   = array();
		$succeeded = 0;
		$failed    = 0;

		foreach ( $urls as $zip_url ) {
			$details[] = $this->install_single_plugin( $zip_url, $activate, $succeeded, $failed );
		}

		return array(
			'message' => sprintf(
				/* translators: 1: number of successful installs, 2: number of failed installs. */
				_n(
					'%1$d succeeded, %2$d failed.',
					'%1$d succeeded, %2$d failed.',
					max( $succeeded, $failed ),
					'themeisle-tester'
				),
				$succeeded,
				$failed
			),
			'details' => $details,
		);
	}

	/**
	 * Install one plugin ZIP, optionally activate, and return a one-line status.
	 *
	 * @param string $zip_url   ZIP URL.
	 * @param bool   $activate  Whether to activate after install.
	 * @param int    $succeeded Counter (by reference).
	 * @param int    $failed    Counter (by reference).
	 * @return string Human-readable line describing the outcome.
	 */
	private function install_single_plugin( $zip_url, $activate, &$succeeded, &$failed ) {
		$scheme = wp_parse_url( $zip_url, PHP_URL_SCHEME );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			++$failed;
			return sprintf(
				/* translators: %s: ZIP URL. */
				__( '%s — invalid URL (must be http(s)).', 'themeisle-tester' ),
				$zip_url
			);
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $zip_url );

		if ( is_wp_error( $result ) ) {
			++$failed;
			return sprintf(
				/* translators: 1: ZIP URL, 2: error message. */
				__( '%1$s — install failed: %2$s', 'themeisle-tester' ),
				$zip_url,
				$result->get_error_message()
			);
		}

		if ( true !== $result ) {
			++$failed;
			$messages = $skin->get_upgrade_messages();
			$reason   = ! empty( $messages ) ? (string) end( $messages ) : __( 'Unknown installer error.', 'themeisle-tester' );
			return sprintf(
				/* translators: 1: ZIP URL, 2: error message. */
				__( '%1$s — install failed: %2$s', 'themeisle-tester' ),
				$zip_url,
				$reason
			);
		}

		$plugin_file = $upgrader->plugin_info();

		if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
			++$failed;
			return sprintf(
				/* translators: %s: ZIP URL. */
				__( '%s — installed but plugin file could not be determined.', 'themeisle-tester' ),
				$zip_url
			);
		}

		if ( ! $activate ) {
			++$succeeded;
			return sprintf(
				/* translators: 1: plugin file, 2: ZIP URL. */
				__( '%1$s installed (%2$s).', 'themeisle-tester' ),
				$plugin_file,
				$zip_url
			);
		}

		$activation = activate_plugin( $plugin_file );

		if ( is_wp_error( $activation ) ) {
			++$failed;
			return sprintf(
				/* translators: 1: plugin file, 2: activation error. */
				__( '%1$s installed but activation failed: %2$s', 'themeisle-tester' ),
				$plugin_file,
				$activation->get_error_message()
			);
		}

		++$succeeded;
		return sprintf(
			/* translators: 1: plugin file, 2: ZIP URL. */
			__( '%1$s installed and activated (%2$s).', 'themeisle-tester' ),
			$plugin_file,
			$zip_url
		);
	}

	/**
	 * Coerce a posted url_list value to an array of cleaned URL strings.
	 *
	 * @param mixed $value Raw posted value (string or array).
	 * @return array<int,string>
	 */
	private function extract_url_list( $value ) {
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
}
