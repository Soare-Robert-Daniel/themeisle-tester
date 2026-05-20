<?php
/**
 * Themeisle SDK logger inspector addon.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a read-only inspector for Themeisle SDK logger / telemetry state.
 */
class TTP_Addon_SDK_Logger implements TTP_Addon {

	/**
	 * Register the logger inspector Utility.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$sdk_category = __( 'SDK', 'themeisle-tester' );
		$shared_sdk   = __( 'Shared SDK', 'themeisle-tester' );

		$registry->register(
			array(
				'id'                 => 'sdk_inspect_logger',
				'type'               => 'utility',
				'categories'         => array( $sdk_category ),
				'product'            => $shared_sdk,
				'label'              => __( 'Logger inspector', 'themeisle-tester' ),
				'description'        => __( 'Shows each SDK product\'s logger consent flag, scheduled log payload, and how data is collected (PHP cron + optional JS telemetry).', 'themeisle-tester' ),
				'width'              => 'full',
				'is_available'       => array( $this, 'is_sdk_available' ),
				'unavailable_reason' => array( $this, 'sdk_unavailable_reason' ),
				'inspect'            => array( $this, 'inspect_logger' ),
				'render_inspect'     => array( $this, 'render_logger_inspect_panel' ),
				'inspect_on_load'    => false,
				'inspect_refresh'    => true,
				'run'                => array( $this, 'run_send_log' ),
				'render_run'         => array( $this, 'render_no_card_run' ),
			)
		);
	}

	/**
	 * Suppress the top-level Run section on the card body.
	 *
	 * The logger's run callback is invoked exclusively by the per-product
	 * "Send log now" buttons and the "Send all active logs" button rendered
	 * inside the inspect panel (see TTP_Logger_Inspect_Renderer). The card
	 * itself does not need its own form.
	 *
	 * @param array<string,mixed> $item Item definition (unused).
	 * @param TTP_Admin_Page      $page Admin page (unused).
	 * @return void
	 */
	public function render_no_card_run( $item, TTP_Admin_Page $page ) {
		unset( $item, $page );
	}

	/**
	 * Trigger an immediate logger POST for one SDK product or all active loggers.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Request payload (product_key or _all_active).
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_send_log( $item, $payload ) {
		unset( $item );

		if ( ! class_exists( 'ThemeisleSDK\Loader' ) ) {
			return new WP_Error(
				'ttp_sdk_unavailable',
				__( 'Themeisle SDK is not available.', 'themeisle-tester' )
			);
		}

		$product_key = '';

		if ( isset( $payload['product_key'] ) && is_scalar( $payload['product_key'] ) ) {
			$product_key = sanitize_key( (string) $payload['product_key'] );
		}

		if ( '_all_active' === $product_key ) {
			return $this->send_logs_for_all_active();
		}

		if ( '' === $product_key ) {
			return new WP_Error(
				'ttp_logger_missing_product',
				__( 'No product key was provided for the log send.', 'themeisle-tester' ),
				array( 'status' => 400 )
			);
		}

		$product = $this->find_product_by_key( $product_key );

		if ( null === $product ) {
			return new WP_Error(
				'ttp_logger_unknown_product',
				__( 'That SDK product key is not registered on this site.', 'themeisle-tester' ),
				array( 'status' => 404 )
			);
		}

		$result = $this->post_logger_for_product( $product );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$friendly = $this->product_method_string( $product, 'get_friendly_name', $product_key );

		return array(
			'message' => sprintf(
				/* translators: %s: product friendly name. */
				__( 'Logger payload sent for %s.', 'themeisle-tester' ),
				$friendly
			),
			'details' => $result['details'],
		);
	}

	/**
	 * Whether the Themeisle SDK is loaded on this site.
	 *
	 * @param array<string,mixed> $item Item definition (unused).
	 * @return bool
	 */
	public function is_sdk_available( $item ) {
		unset( $item );

		return class_exists( 'ThemeisleSDK\Loader' );
	}

	/**
	 * Unavailable reason when the SDK is missing.
	 *
	 * @param array<string,mixed> $item Item definition (unused).
	 * @return string
	 */
	public function sdk_unavailable_reason( $item ) {
		unset( $item );

		return __( 'Themeisle SDK is not loaded — no products have registered with Loader::add_product().', 'themeisle-tester' );
	}

	/**
	 * Inspect logger flags, payloads, and collection mechanics for all SDK products.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Sanitized inspect payload (unused).
	 * @return array<string,mixed>|WP_Error
	 */
	public function inspect_logger( $item, $payload ) {
		unset( $item, $payload );

		if ( ! class_exists( 'ThemeisleSDK\Loader' ) ) {
			return new WP_Error(
				'ttp_sdk_unavailable',
				__( 'Themeisle SDK is not available.', 'themeisle-tester' )
			);
		}

		$products = \ThemeisleSDK\Loader::get_products();

		if ( empty( $products ) ) {
			return array(
				'product_count' => 0,
				'globals'       => $this->build_globals(),
				'products'      => array(),
				'telemetry'     => array(
					'js_enabled' => $this->is_js_telemetry_enabled(),
					'products'   => array(),
				),
				'_note'         => __( 'No SDK products are registered on this site.', 'themeisle-tester' ),
			);
		}

		$rows = array();

		foreach ( $products as $slug => $product ) {
			if ( ! method_exists( $product, 'get_key' ) ) {
				continue;
			}

			$rows[] = $this->format_product_row( $product, (string) $slug, $products );
		}

		return array(
			'product_count' => count( $rows ),
			'globals'       => $this->build_globals(),
			'products'      => $rows,
			'telemetry'     => array(
				'js_enabled' => $this->is_js_telemetry_enabled(),
				'products'   => $this->build_telemetry_products( $products ),
			),
		);
	}

	/**
	 * Site-wide logger / telemetry switches and API endpoints.
	 *
	 * @return array<string,mixed>
	 */
	private function build_globals() {
		return array(
			'tracking_endpoint'           => 'https://api.themeisle.com/tracking/log',
			'telemetry_endpoint'          => 'https://api.themeisle.com/tracking/events',
			'global_telemetry_disabled'   => (bool) apply_filters( 'themeisle_sdk_disable_telemetry', false ),
			'js_telemetry_filter_enabled' => $this->is_js_telemetry_enabled(),
		);
	}

	/**
	 * Whether the themeisle_sdk_enable_telemetry filter opts in to JS telemetry.
	 *
	 * @return bool
	 */
	private function is_js_telemetry_enabled() {
		return (bool) apply_filters( 'themeisle_sdk_enable_telemetry', false );
	}

	/**
	 * Default consent flag when the option has never been saved (mirrors Logger::is_logger_active).
	 *
	 * @param object                $product      SDK product instance.
	 * @param array<string, object> $all_products All registered products.
	 * @return string yes|no
	 */
	private function get_default_logger_flag( $product, array $all_products ) {
		if ( $this->product_method_bool( $product, 'is_wordpress_available' ) ) {
			foreach ( $all_products as $candidate ) {
				if ( $this->product_method_bool( $candidate, 'requires_license' ) ) {
					return 'yes';
				}
			}

			return 'no';
		}

		return 'yes';
	}

	/**
	 * Build one product row for the inspector table.
	 *
	 * @param object                $product      SDK product instance.
	 * @param string                $slug         Product slug key in Loader map.
	 * @param array<string, object> $all_products All SDK products.
	 * @return array<string,mixed>
	 */
	private function format_product_row( $product, $slug, array $all_products ) {
		$key                = $this->product_method_string( $product, 'get_key' );
		$flag_key           = $key . '_logger_flag';
		$default            = $this->get_default_logger_flag( $product, $all_products );
		$stored_raw         = get_option( $flag_key, false );
		$effective          = ( false === $stored_raw ) ? $default : ( is_scalar( $stored_raw ) ? (string) $stored_raw : $default );
		$module_on          = (bool) apply_filters( $this->product_method_string( $product, 'get_slug' ) . '_sdk_enable_logger', true );
		$global_off         = (bool) apply_filters( 'themeisle_sdk_disable_telemetry', false );
		$is_active          = $module_on && ! $global_off && 'yes' === $effective;
		$cron_hook          = $key . '_log_activity';
		$cron_next          = wp_next_scheduled( $cron_hook );
		$logger_data_filter = $key . '_logger_data';
		$logger_data        = apply_filters( $logger_data_filter, array() );

		$row = array(
			'slug'                  => $slug,
			'friendly_name'         => $this->product_method_string( $product, 'get_friendly_name', $slug ),
			'product_key'           => $key,
			'logger_flag_option'    => $flag_key,
			'stored_flag'           => false === $stored_raw ? null : ( is_scalar( $stored_raw ) ? (string) $stored_raw : null ),
			'default_flag'          => $default,
			'effective_consent'     => $effective,
			'logger_active'         => $is_active,
			'module_enabled_filter' => $module_on,
			'wordpress_available'   => $this->product_method_bool( $product, 'is_wordpress_available' ),
			'requires_license'      => $this->product_method_bool( $product, 'requires_license' ),
			'version'               => $this->product_method_string( $product, 'get_version' ),
			'install_time'          => $this->product_method_string( $product, 'get_install_time' ),
			'cron_hook'             => $cron_hook,
			'cron_scheduled'        => false !== $cron_next,
			'cron_next_utc'         => false !== $cron_next ? gmdate( 'Y-m-d H:i:s', $cron_next ) : '',
			'license_in_payload'    => (string) apply_filters( $key . '_license_status', '' ),
			'logger_data'           => is_array( $logger_data ) ? $logger_data : array(),
			'logger_data_filter'    => $logger_data_filter,
			'collection'            => $this->describe_collection( $product, $is_active ),
		);

		$pro_slug = $this->product_method_string( $product, 'get_pro_slug' );
		if ( '' !== $pro_slug ) {
			$row['pro_slug'] = $pro_slug;
		}

		return $row;
	}

	/**
	 * Human-readable summary of how this product collects logger data.
	 *
	 * @param object $product   SDK product instance.
	 * @param bool   $is_active Whether PHP logging is active.
	 * @return array<string, array<string, string>>
	 */
	private function describe_collection( $product, $is_active ) {
		$key = $this->product_method_string( $product, 'get_key' );

		$php = array(
			'mechanism' => __( 'WP-Cron single event', 'themeisle-tester' ),
			'hook'      => $key . '_log_activity',
			'endpoint'  => 'https://api.themeisle.com/tracking/log',
			'payload'   => sprintf(
				/* translators: %s: product key prefix for filters */
				__( 'site, slug, version, wp_version, install_time, locale, license (%1$s_license_status), data (%2$s_logger_data), environment (theme + active_plugins)', 'themeisle-tester' ),
				$key,
				$key
			),
		);

		if ( ! $is_active ) {
			$php['status'] = __( 'Inactive — consent is off, module disabled, or global telemetry kill switch is on.', 'themeisle-tester' );
		} else {
			$php['status'] = __( 'Active — cron fires once (random 1–24h after wp_loaded), POSTs payload, not auto-rescheduled.', 'themeisle-tester' );
		}

		$js = array(
			'mechanism' => __( 'Admin JS telemetry (optional)', 'themeisle-tester' ),
			'filter'    => 'themeisle_sdk_enable_telemetry',
			'endpoint'  => 'https://api.themeisle.com/tracking/events',
			'global'    => 'tiTelemetry / tiTrk',
		);

		if ( ! $this->is_js_telemetry_enabled() ) {
			$js['status'] = __( 'Filter not enabled on this site.', 'themeisle-tester' );
		} else {
			$js['status'] = __( 'May load when consent is yes and product is eligible (see telemetry table below).', 'themeisle-tester' );
		}

		return array(
			'php_log'      => $php,
			'js_telemetry' => $js,
		);
	}

	/**
	 * Replicate Logger::load_telemetry product list for the inspector (read-only).
	 *
	 * @param array<string, object> $all_products Registered SDK products.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_telemetry_products( array $all_products ) {
		$eligible = array();

		foreach ( $all_products as $product_slug => $product ) {
			if ( false !== strstr( (string) $product_slug, 'pro' ) ) {
				continue;
			}

			$pro_slug   = $this->product_method_string( $product, 'get_pro_slug' );
			$logger_key = $this->product_method_string( $product, 'get_key' ) . '_logger_flag';

			if ( ! $this->product_method_bool( $product, 'is_wordpress_available' ) || ( '' !== $pro_slug && isset( $all_products[ $pro_slug ] ) ) ) {
				$logger_flag = get_option( $logger_key );
				if ( false === $logger_flag ) {
					// Mirrors SDK auto opt-in; inspector shows effective state only.
					$effective_flag = 'yes';
				} else {
					$effective_flag = is_scalar( $logger_flag ) ? (string) $logger_flag : 'no';
				}
			} else {
				$stored_flag    = get_option( $logger_key, 'no' );
				$effective_flag = is_scalar( $stored_flag ) ? (string) $stored_flag : 'no';
			}

			if ( 'yes' !== $effective_flag ) {
				continue;
			}

			$main_slug  = explode( '-', (string) $product_slug );
			$main_slug  = $main_slug[0];
			$track_hash = 'free';

			if ( class_exists( 'ThemeisleSDK\Modules\Licenser' ) ) {
				$hash_slug  = str_replace( '-', '_', ! empty( $pro_slug ) ? $pro_slug : (string) $product_slug );
				$track_hash = \ThemeisleSDK\Modules\Licenser::create_license_hash( $hash_slug );
				if ( ! is_string( $track_hash ) || '' === $track_hash ) {
					$track_hash = 'free';
				}
			}

			$duplicate = false;
			foreach ( $eligible as $entry ) {
				if ( $entry['slug'] === $main_slug ) {
					$duplicate = true;
					break;
				}
			}

			if ( $duplicate ) {
				continue;
			}

			$eligible[] = array(
				'slug'      => $main_slug,
				'trackHash' => $track_hash,
				'consent'   => true,
				'source'    => (string) $product_slug,
			);
		}

		$filtered = apply_filters( 'themeisle_sdk_telemetry_products', $eligible );

		if ( ! is_array( $filtered ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $filtered as $row ) {
			if ( is_array( $row ) ) {
				$normalized[] = $row;
			}
		}

		return $normalized;
	}

	/**
	 * POST logger payloads for every product that currently has logging active.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function send_logs_for_all_active() {
		$products = \ThemeisleSDK\Loader::get_products();
		$details  = array();
		$sent     = 0;
		$failed   = 0;

		foreach ( $products as $product ) {
			if ( ! method_exists( $product, 'get_key' ) ) {
				continue;
			}

			$key = $this->product_method_string( $product, 'get_key' );

			if ( '' === $key || ! $this->is_product_logger_active( $product, $products ) ) {
				continue;
			}

			$result = $this->post_logger_for_product( $product );
			$label  = $this->product_method_string( $product, 'get_friendly_name', $key );

			if ( is_wp_error( $result ) ) {
				++$failed;
				$details[] = sprintf(
					/* translators: 1: product name, 2: error message. */
					__( '%1$s: failed — %2$s', 'themeisle-tester' ),
					$label,
					$result->get_error_message()
				);
				continue;
			}

			++$sent;
			$details   = array_merge( $details, $result['details'] );
			$details[] = '---';
		}

		if ( 0 === $sent && 0 === $failed ) {
			return new WP_Error(
				'ttp_logger_none_active',
				__( 'No SDK products currently have logging active.', 'themeisle-tester' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'message' => sprintf(
				/* translators: 1: sent count, 2: failed count. */
				__( 'Sent logger payloads for %1$d product(s); %2$d failed.', 'themeisle-tester' ),
				$sent,
				$failed
			),
			'details' => $details,
		);
	}

	/**
	 * Find a registered SDK product by its key.
	 *
	 * @param string $product_key Product key.
	 * @return object|null
	 */
	private function find_product_by_key( $product_key ) {
		foreach ( \ThemeisleSDK\Loader::get_products() as $product ) {
			if ( ! method_exists( $product, 'get_key' ) ) {
				continue;
			}

			if ( $this->product_method_string( $product, 'get_key' ) === $product_key ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Whether logging is active for a product (mirrors inspect row logic).
	 *
	 * @param object                $product      SDK product.
	 * @param array<string, object> $all_products All SDK products.
	 * @return bool
	 */
	private function is_product_logger_active( $product, array $all_products ) {
		$key        = $this->product_method_string( $product, 'get_key' );
		$flag_key   = $key . '_logger_flag';
		$default    = $this->get_default_logger_flag( $product, $all_products );
		$stored_raw = get_option( $flag_key, false );
		$effective  = ( false === $stored_raw ) ? $default : ( is_scalar( $stored_raw ) ? (string) $stored_raw : $default );
		$module_on  = (bool) apply_filters( $this->product_method_string( $product, 'get_slug' ) . '_sdk_enable_logger', true );
		$global_off = (bool) apply_filters( 'themeisle_sdk_disable_telemetry', false );

		return $module_on && ! $global_off && 'yes' === $effective;
	}

	/**
	 * POST the SDK logger payload for one product (same shape as Logger::send_log).
	 *
	 * @param object $product SDK product instance.
	 * @return array{details:array<int,string>}|WP_Error
	 */
	private function post_logger_for_product( $product ) {
		$key   = $this->product_method_string( $product, 'get_key' );
		$theme = wp_get_theme();

		$environment = array(
			'theme'   => array(
				'name'   => $theme->get( 'Name' ),
				'author' => $theme->get( 'Author' ),
				'parent' => false !== $theme->parent() ? $theme->parent()->get( 'Name' ) : $theme->get( 'Name' ),
			),
			'plugins' => get_option( 'active_plugins' ),
		);

		$logger_data = apply_filters( $key . '_logger_data', array() );

		if ( ! is_array( $logger_data ) ) {
			$logger_data = array();
		}

		global $wp_version;

		$endpoint = 'https://api.themeisle.com/tracking/log';
		$response = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'POST',
				'timeout'     => 15,
				'redirection' => 5,
				'body'        => array(
					'site'         => get_site_url(),
					'slug'         => $this->product_method_string( $product, 'get_slug' ),
					'version'      => $this->product_method_string( $product, 'get_version' ),
					'wp_version'   => $wp_version,
					'install_time' => $this->product_method_string( $product, 'get_install_time' ),
					'locale'       => get_locale(),
					'data'         => $logger_data,
					'environment'  => $environment,
					'license'      => (string) apply_filters( $key . '_license_status', '' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$label       = $this->product_method_string( $product, 'get_friendly_name', $key );
		$details     = array(
			sprintf(
				/* translators: 1: product name, 2: tracking endpoint, 3: HTTP status. */
				__( '%1$s → POST %2$s (HTTP %3$d)', 'themeisle-tester' ),
				$label,
				$endpoint,
				$status_code
			),
		);

		if ( '' !== $body ) {
			$decoded = json_decode( $body, true );

			if ( is_array( $decoded ) ) {
				$encoded = wp_json_encode( $decoded );

				if ( is_string( $encoded ) ) {
					$details[] = $encoded;
				}
			} else {
				$details[] = sanitize_text_field( $body );
			}
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'ttp_logger_send_failed',
				sprintf(
					/* translators: 1: product name, 2: HTTP status code. */
					__( 'Logger send failed for %1$s (HTTP %2$d).', 'themeisle-tester' ),
					$label,
					$status_code
				),
				array(
					'status'  => $status_code,
					'details' => $details,
				)
			);
		}

		return array(
			'details' => $details,
		);
	}

	/**
	 * Call a string-returning method on an SDK product instance.
	 *
	 * @param object $product  SDK product.
	 * @param string $method   Method name.
	 * @param string $fallback Fallback when missing or non-scalar.
	 * @return string
	 */
	private function product_method_string( $product, $method, $fallback = '' ) {
		if ( ! method_exists( $product, $method ) ) {
			return $fallback;
		}

		$handler = array( $product, $method );

		if ( ! is_callable( $handler ) ) {
			return $fallback;
		}

		$value = $handler();

		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Call a bool-returning method on an SDK product instance.
	 *
	 * @param object $product SDK product.
	 * @param string $method  Method name.
	 * @return bool
	 */
	private function product_method_bool( $product, $method ) {
		if ( ! method_exists( $product, $method ) ) {
			return false;
		}

		$handler = array( $product, $method );

		if ( ! is_callable( $handler ) ) {
			return false;
		}

		return (bool) $handler();
	}

	/**
	 * Render SDK logger inspect output.
	 *
	 * @param array<string,mixed> $item           Item definition.
	 * @param mixed               $inspect_result Inspect callback result.
	 * @param TTP_Admin_Page      $page           Admin page.
	 * @return void
	 */
	public function render_logger_inspect_panel( $item, $inspect_result, TTP_Admin_Page $page ) {
		unset( $item );
		$page->render_logger_inspect( $inspect_result );
	}
}
