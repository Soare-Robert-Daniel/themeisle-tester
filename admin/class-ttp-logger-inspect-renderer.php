<?php
/**
 * SDK logger inspector renderer for the Dashboard.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders SDK logger / telemetry inspect output.
 */
class TTP_Logger_Inspect_Renderer {

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
	 * Render logger inspect results.
	 *
	 * @param mixed $result Inspect callback result.
	 * @return void
	 */
	public function render_logger_result( $result ) {
		if ( is_wp_error( $result ) ) {
			echo '<p class="ttp-note ttp-note--error">' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}

		if ( ! is_array( $result ) ) {
			return;
		}

		echo '<div class="ttp-logger-inspect">';

		$this->render_globals( isset( $result['globals'] ) && is_array( $result['globals'] ) ? $result['globals'] : array() );
		$this->render_products_table( $this->normalize_product_rows( isset( $result['products'] ) && is_array( $result['products'] ) ? $result['products'] : array() ) );
		$this->render_telemetry( isset( $result['telemetry'] ) && is_array( $result['telemetry'] ) ? $result['telemetry'] : array() );

		if ( isset( $result['_note'] ) && is_string( $result['_note'] ) && '' !== $result['_note'] ) {
			echo '<p class="ttp-note">' . esc_html( $result['_note'] ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Render site-wide logger settings.
	 *
	 * @param array<string,mixed> $globals Global inspect row.
	 * @return void
	 */
	private function render_globals( array $globals ) {
		echo '<section class="ttp-logger-inspect__section">';
		echo '<h3 class="ttp-logger-inspect__heading">' . esc_html__( 'Global', 'themeisle-tester' ) . '</h3>';
		echo '<dl class="ttp-defs ttp-defs--compact">';

		$rows = array(
			'tracking_endpoint'           => $this->string_value( $globals, 'tracking_endpoint' ),
			'telemetry_endpoint'          => $this->string_value( $globals, 'telemetry_endpoint' ),
			'global_telemetry_disabled'   => $this->format_bool( ! empty( $globals['global_telemetry_disabled'] ) ),
			'js_telemetry_filter_enabled' => $this->format_bool( ! empty( $globals['js_telemetry_filter_enabled'] ) ),
		);

		foreach ( $rows as $label => $value ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}

		echo '</dl>';
		$this->render_send_all_form();
		echo '</section>';
	}

	/**
	 * Render a control to send logs for every product with logging active.
	 *
	 * @return void
	 */
	private function render_send_all_form() {
		$utility_id = 'sdk_inspect_logger';
		$working    = __( 'Sending…', 'themeisle-tester' );

		?>
		<form
			method="post"
			class="ttp-logger-send-all"
			data-ttp-logger-send-form
			<?php echo $this->datastar->datastar_post_attr( 'utilities/' . $utility_id . '/run' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
		>
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="run_utility">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $utility_id ); ?>">
			<input type="hidden" name="ttp_params[product_key]" value="_all_active">
			<button
				type="submit"
				class="button button-secondary"
				data-ttp-logger-send-submit
				data-ttp-default-label="<?php echo esc_attr__( 'Send all active logs', 'themeisle-tester' ); ?>"
				data-ttp-working-label="<?php echo esc_attr( $working ); ?>"
			>
				<?php esc_html_e( 'Send all active logs', 'themeisle-tester' ); ?>
			</button>
			<span class="ttp-logger-send-all__status" data-ttp-logger-send-status role="status" aria-live="polite" hidden data-ttp-working-label="<?php echo esc_attr( $working ); ?>"></span>
		</form>
		<?php
	}

	/**
	 * Render per-product logger rows.
	 *
	 * @param array<int,array<string,mixed>> $products Product rows.
	 * @return void
	 */
	private function render_products_table( array $products ) {
		echo '<section class="ttp-logger-inspect__section">';
		echo '<h3 class="ttp-logger-inspect__heading">';
		echo esc_html__( 'SDK products', 'themeisle-tester' );
		echo ' <span class="ttp-logger-inspect__count">(' . esc_html( (string) count( $products ) ) . ')</span>';
		echo '</h3>';

		if ( empty( $products ) ) {
			echo '<p class="ttp-empty">' . esc_html__( 'No SDK products to display.', 'themeisle-tester' ) . '</p>';
			echo '</section>';
			return;
		}

		foreach ( $products as $product ) {
			$this->render_product_card( $product );
		}

		echo '</section>';
	}

	/**
	 * Render one product logger card.
	 *
	 * @param array<string,mixed> $product Product inspect row.
	 * @return void
	 */
	private function render_product_card( array $product ) {
		$name        = isset( $product['friendly_name'] ) && is_string( $product['friendly_name'] ) ? $product['friendly_name'] : '';
		$slug        = isset( $product['slug'] ) && is_string( $product['slug'] ) ? $product['slug'] : '';
		$active      = ! empty( $product['logger_active'] );
		$consent     = isset( $product['effective_consent'] ) && is_string( $product['effective_consent'] ) ? $product['effective_consent'] : 'no';
		$flag_option = isset( $product['logger_flag_option'] ) && is_string( $product['logger_flag_option'] ) ? $product['logger_flag_option'] : '';
		$stored      = array_key_exists( 'stored_flag', $product ) ? $product['stored_flag'] : null;
		$default     = isset( $product['default_flag'] ) && is_string( $product['default_flag'] ) ? $product['default_flag'] : 'no';
		$cron_hook   = isset( $product['cron_hook'] ) && is_string( $product['cron_hook'] ) ? $product['cron_hook'] : '';
		$cron_next   = isset( $product['cron_next_utc'] ) && is_string( $product['cron_next_utc'] ) ? $product['cron_next_utc'] : '';
		$cron_on     = ! empty( $product['cron_scheduled'] );
		$logger_data = isset( $product['logger_data'] ) && is_array( $product['logger_data'] ) ? $product['logger_data'] : array();
		$data_filter = isset( $product['logger_data_filter'] ) && is_string( $product['logger_data_filter'] ) ? $product['logger_data_filter'] : '';
		$collection  = isset( $product['collection'] ) && is_array( $product['collection'] ) ? $product['collection'] : array();

		echo '<details class="ttp-logger-product">';
		echo '<summary class="ttp-logger-product__summary">';
		echo '<span class="ttp-logger-product__title">' . esc_html( $name ) . '</span>';
		echo '<code class="ttp-logger-product__slug">' . esc_html( $slug ) . '</code>';
		echo $this->render_consent_badge( $consent, $active ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_html.
		echo '</summary>';

		echo '<div class="ttp-logger-product__body">';

		echo '<dl class="ttp-defs ttp-defs--compact">';
		$this->def_row( 'product_key', $this->string_value( $product, 'product_key' ) );
		$this->def_row( 'logger_flag_option', $flag_option );
		$this->def_row( 'stored_flag', null === $stored ? '(not set)' : ( is_scalar( $stored ) ? (string) $stored : '' ) );
		$this->def_row( 'default_flag', $default );
		$this->def_row( 'effective_consent', $consent );
		$this->def_row( 'logger_active', $this->format_bool( $active ) );
		$this->def_row( 'module_enabled_filter', $this->format_bool( ! empty( $product['module_enabled_filter'] ) ) );
		$this->def_row( 'wordpress_available', $this->format_bool( ! empty( $product['wordpress_available'] ) ) );
		$this->def_row( 'requires_license', $this->format_bool( ! empty( $product['requires_license'] ) ) );
		$this->def_row( 'version', $this->string_value( $product, 'version' ) );
		$this->def_row( 'install_time', $this->string_value( $product, 'install_time' ) );
		$this->def_row( 'license_in_payload', $this->string_value( $product, 'license_in_payload' ) );
		$this->def_row( 'cron_hook', $cron_hook );
		$this->def_row( 'cron_scheduled', $this->format_bool( $cron_on ) );
		if ( $cron_on && '' !== $cron_next ) {
			$this->def_row( 'cron_next_utc', $cron_next );
		}
		echo '</dl>';

		$this->render_send_log_form( $this->string_value( $product, 'product_key' ) );

		$this->render_collection_block( $this->normalize_collection( $collection ) );

		echo '<details class="ttp-logger-raw">';
		echo '<summary class="ttp-logger-raw__summary">';
		printf(
			/* translators: %s: filter hook name */
			esc_html__( '%s (payload preview)', 'themeisle-tester' ),
			esc_html( $data_filter )
		);
		echo '</summary>';
		$encoded = wp_json_encode( $logger_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		echo '<pre class="ttp-code-block">';
		echo esc_html( is_string( $encoded ) ? $encoded : '{}' );
		echo '</pre>';
		echo '</details>';

		echo '</div>';
		echo '</details>';
	}

	/**
	 * Render an inline form to trigger an immediate logger POST for one product.
	 *
	 * @param string $product_key SDK product key.
	 * @return void
	 */
	private function render_send_log_form( $product_key ) {
		if ( '' === $product_key ) {
			return;
		}

		$utility_id = 'sdk_inspect_logger';
		$working    = __( 'Sending…', 'themeisle-tester' );

		?>
		<form
			method="post"
			class="ttp-logger-product__send-form"
			data-ttp-logger-send-form
			<?php echo $this->datastar->datastar_post_attr( 'utilities/' . $utility_id . '/run' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
		>
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="run_utility">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $utility_id ); ?>">
			<input type="hidden" name="ttp_params[product_key]" value="<?php echo esc_attr( $product_key ); ?>">
			<button
				type="submit"
				class="button button-secondary button-small"
				data-ttp-logger-send-submit
				data-ttp-default-label="<?php echo esc_attr__( 'Send log now', 'themeisle-tester' ); ?>"
				data-ttp-working-label="<?php echo esc_attr( $working ); ?>"
			>
				<?php esc_html_e( 'Send log now', 'themeisle-tester' ); ?>
			</button>
			<span class="ttp-logger-product__send-status" data-ttp-logger-send-status role="status" aria-live="polite" hidden data-ttp-working-label="<?php echo esc_attr( $working ); ?>"></span>
		</form>
		<?php
	}

	/**
	 * Render how data is collected for one product.
	 *
	 * @param array<string,array<string,string>> $collection Collection descriptors.
	 * @return void
	 */
	private function render_collection_block( array $collection ) {
		echo '<details class="ttp-logger-collection-details">';
		echo '<summary class="ttp-logger-collection-details__summary">';
		esc_html_e( 'How data is collected', 'themeisle-tester' );
		echo '</summary>';
		echo '<div class="ttp-logger-collection">';

		foreach ( $collection as $channel => $block ) {
			$title = isset( $block['mechanism'] ) ? $block['mechanism'] : (string) $channel;

			echo '<div class="ttp-logger-collection__channel">';
			echo '<p class="ttp-logger-collection__title">' . esc_html( $title ) . '</p>';
			echo '<dl class="ttp-defs ttp-defs--compact">';

			foreach ( $block as $key => $value ) {
				if ( 'mechanism' === $key || '' === $value ) {
					continue;
				}
				$this->def_row( $key, $value );
			}

			echo '</dl>';
			echo '</div>';
		}

		echo '</div>';
		echo '</details>';
	}

	/**
	 * Render JS telemetry product list.
	 *
	 * @param array<string,mixed> $telemetry Telemetry inspect block.
	 * @return void
	 */
	private function render_telemetry( array $telemetry ) {
		$js_on    = ! empty( $telemetry['js_enabled'] );
		$products = isset( $telemetry['products'] ) && is_array( $telemetry['products'] ) ? $telemetry['products'] : array();

		echo '<section class="ttp-logger-inspect__section">';
		echo '<h3 class="ttp-logger-inspect__heading">' . esc_html__( 'JS telemetry queue', 'themeisle-tester' ) . '</h3>';

		echo '<p class="ttp-logger-inspect__lead">';
		if ( $js_on ) {
			esc_html_e( 'themeisle_sdk_enable_telemetry is enabled. Eligible products with consent may appear in tiTelemetry.products on admin pages.', 'themeisle-tester' );
		} else {
			esc_html_e( 'JS telemetry is off — add_filter( \'themeisle_sdk_enable_telemetry\', \'__return_true\' ) to load the tracking script.', 'themeisle-tester' );
		}
		echo '</p>';

		if ( empty( $products ) ) {
			echo '<p class="ttp-empty">' . esc_html__( 'No products in the telemetry queue.', 'themeisle-tester' ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<table class="ttp-data-table ttp-logger-telemetry-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'slug', 'themeisle-tester' ) . '</th>';
		echo '<th>' . esc_html__( 'trackHash', 'themeisle-tester' ) . '</th>';
		echo '<th>' . esc_html__( 'consent', 'themeisle-tester' ) . '</th>';
		echo '<th>' . esc_html__( 'source product', 'themeisle-tester' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $products as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$slug = isset( $row['slug'] ) && is_string( $row['slug'] ) ? $row['slug'] : '';
			$hash = $this->string_value( $row, 'trackHash' );
			if ( '' === $hash ) {
				$hash = $this->string_value( $row, 'track_hash' );
			}
			$source = isset( $row['source'] ) && is_string( $row['source'] ) ? $row['source'] : '';

			echo '<tr>';
			echo '<td><code>' . esc_html( $slug ) . '</code></td>';
			echo '<td><code>' . esc_html( $hash ) . '</code></td>';
			echo '<td>' . esc_html( ! empty( $row['consent'] ) ? 'true' : 'false' ) . '</td>';
			echo '<td class="ttp-data-table__muted"><code>' . esc_html( $source ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</section>';
	}

	/**
	 * Keep only array product rows from inspect output.
	 *
	 * @param array<mixed> $products Raw products list.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_product_rows( array $products ) {
		$rows = array();

		foreach ( $products as $product ) {
			if ( is_array( $product ) ) {
				$rows[] = $product;
			}
		}

		return $rows;
	}

	/**
	 * Normalize collection channel maps for rendering.
	 *
	 * @param array<mixed> $collection Raw collection block.
	 * @return array<string, array<string, string>>
	 */
	private function normalize_collection( array $collection ) {
		$normalized = array();

		foreach ( array( 'php_log', 'js_telemetry' ) as $channel ) {
			if ( ! isset( $collection[ $channel ] ) || ! is_array( $collection[ $channel ] ) ) {
				continue;
			}

			$block  = array();
			$source = $collection[ $channel ];

			foreach ( $source as $key => $value ) {
				if ( is_string( $key ) && is_string( $value ) ) {
					$block[ $key ] = $value;
				}
			}

			$normalized[ $channel ] = $block;
		}

		return $normalized;
	}

	/**
	 * Read a string field from an associative row.
	 *
	 * @param array<string, mixed> $row Source row.
	 * @param string               $key Field key.
	 * @return string
	 */
	private function string_value( array $row, $key ) {
		if ( ! isset( $row[ $key ] ) || ! is_scalar( $row[ $key ] ) ) {
			return '';
		}

		return (string) $row[ $key ];
	}

	/**
	 * Output one definition row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private function def_row( $label, $value ) {
		echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
	}

	/**
	 * Format boolean for display.
	 *
	 * @param bool $value Value.
	 * @return string
	 */
	private function format_bool( $value ) {
		return $value ? 'true' : 'false';
	}

	/**
	 * Render consent / active badge markup.
	 *
	 * @param string $consent Effective consent flag.
	 * @param bool   $active  Whether logger is active.
	 * @return string HTML (escaped).
	 */
	private function render_consent_badge( $consent, $active ) {
		if ( $active ) {
			return '<span class="ttp-badge ttp-badge--active">' . esc_html__( 'logging on', 'themeisle-tester' ) . '</span>';
		}

		if ( 'yes' === $consent ) {
			return '<span class="ttp-badge ttp-badge--status">' . esc_html__( 'consent yes', 'themeisle-tester' ) . '</span>';
		}

		return '<span class="ttp-badge ttp-badge--status-inactive">' . esc_html__( 'consent no', 'themeisle-tester' ) . '</span>';
	}
}
