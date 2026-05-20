<?php
/**
 * WordPress platform Testing Items (install from ZIP, popular plugins, etc.).
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WordPress admin utilities for QA workflows.
 *
 * @phpstan-import-type NormalizedItem from TTP_Item_Registry
 */
class TTP_Addon_WordPress implements TTP_Addon {

	/**
	 * Cached popular plugins catalog.
	 *
	 * @var array<string,array{name:string,source:string,slug?:string,zip_url?:string}>|null
	 */
	private $popular_catalog = null;

	/**
	 * Picsum image importer (delegates the import_random_images item).
	 *
	 * @var TTP_WordPress_Picsum_Importer
	 */
	private $picsum_importer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->picsum_importer = new TTP_WordPress_Picsum_Importer();
	}

	/**
	 * Register WordPress Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$wordpress     = __( 'WordPress', 'themeisle-tester' );
		$catalog_slugs = array_keys( $this->get_popular_catalog() );

		$registry->register(
			array(
				'id'                 => 'install_plugin_from_zip',
				'type'               => 'utility',
				'categories'         => array( $wordpress ),
				'product'            => $wordpress,
				'label'              => __( 'Install plugins', 'themeisle-tester' ),
				'description'        => __( 'Quick-install popular plugins or install custom builds from ZIP URLs.', 'themeisle-tester' ),
				'width'              => 'wide',
				'fields'             => array(
					array(
						'id'      => 'plugin_slug',
						'type'    => 'select',
						'label'   => __( 'Quick-install plugin', 'themeisle-tester' ),
						'options' => $catalog_slugs,
					),
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
				'is_available'       => array( $this, 'is_plugin_install_available' ),
				'unavailable_reason' => array( $this, 'plugin_install_unavailable_reason' ),
				'inspect'            => array( $this, 'inspect_plugin_install' ),
				'render_inspect'     => array( $this, 'render_plugin_install_inspect' ),
				'run_ui'             => array(
					'transport' => 'zip_batch',
				),
				'run'                => array( $this, 'run_install_plugin_from_zip' ),
			)
		);

		$this->picsum_importer->register( $registry, $wordpress );
	}

	/**
	 * Whether plugin installation utilities are available.
	 *
	 * @param array<string,mixed> $item Item definition (unused).
	 * @return bool
	 */
	public function is_plugin_install_available( $item ) {
		unset( $item );
		return current_user_can( 'install_plugins' );
	}

	/**
	 * Unavailable reason when install_plugins is missing.
	 *
	 * @param array<string,mixed> $item Item definition (unused).
	 * @return string
	 */
	public function plugin_install_unavailable_reason( $item ) {
		unset( $item );
		return __( 'You do not have permission to install plugins on this site.', 'themeisle-tester' );
	}

	/**
	 * Inspect quick-install shortcuts for the install utility card.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Request payload (unused).
	 * @return array<string,mixed>
	 */
	public function inspect_plugin_install( $item, $payload ) {
		unset( $item, $payload );

		$shortcuts = array();

		foreach ( $this->get_popular_catalog() as $catalog_slug => $entry ) {
			$folder_slug = $this->catalog_folder_slug( $catalog_slug, $entry );
			$status_row  = $this->get_plugin_row_status( $folder_slug );

			$shortcuts[] = array(
				'slug'   => $catalog_slug,
				'name'   => $entry['name'],
				'status' => $status_row['status'],
			);
		}

		return array( 'shortcuts' => $shortcuts );
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
		unset( $item );

		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'ttp_install_forbidden', __( 'You do not have permission to install plugins on this site.', 'themeisle-tester' ) );
		}

		$raw_slug = isset( $payload['plugin_slug'] ) && is_scalar( $payload['plugin_slug'] )
			? sanitize_key( (string) $payload['plugin_slug'] )
			: '';

		if ( '' !== $raw_slug ) {
			return $this->run_catalog_plugin_install( $raw_slug );
		}

		$raw_urls = isset( $payload['zip_urls'] ) ? $payload['zip_urls'] : null;
		$urls     = $this->extract_url_list( $raw_urls );
		$activate = ! empty( $payload['activate'] );

		if ( empty( $urls ) ) {
			return new WP_Error( 'ttp_missing_zip_url', __( 'Provide at least one ZIP URL to install.', 'themeisle-tester' ) );
		}

		$bootstrap = $this->bootstrap_plugin_upgrader();

		if ( is_wp_error( $bootstrap ) ) {
			return $bootstrap;
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
	 * Install and activate one catalog plugin (quick-install shortcut).
	 *
	 * @param string $raw_slug Catalog slug from posted payload.
	 * @return array<string,mixed>|WP_Error
	 */
	private function run_catalog_plugin_install( $raw_slug ) {
		$catalog = $this->get_popular_catalog();

		if ( ! isset( $catalog[ $raw_slug ] ) ) {
			return new WP_Error( 'ttp_invalid_plugin_slug', __( 'Select a valid plugin from the catalog.', 'themeisle-tester' ) );
		}

		$entry       = $catalog[ $raw_slug ];
		$name        = $entry['name'];
		$folder_slug = $this->catalog_folder_slug( $raw_slug, $entry );
		$status_row  = $this->get_plugin_row_status( $folder_slug );

		if ( 'active' === $status_row['status'] ) {
			return array(
				'message' => sprintf(
					/* translators: %s: plugin name. */
					__( '%s is already active.', 'themeisle-tester' ),
					$name
				),
				'details' => array(),
			);
		}

		if ( 'inactive' === $status_row['status'] && '' !== $status_row['plugin_file'] ) {
			$activation = $this->activate_plugin_file( $status_row['plugin_file'] );

			if ( is_wp_error( $activation ) ) {
				return $activation;
			}

			return array(
				'message' => sprintf(
					/* translators: %s: plugin name. */
					__( '%s activated.', 'themeisle-tester' ),
					$name
				),
				'details' => array( $status_row['plugin_file'] ),
			);
		}

		$bootstrap = $this->bootstrap_plugin_upgrader();

		if ( is_wp_error( $bootstrap ) ) {
			return $bootstrap;
		}

		$install = $this->install_catalog_entry( $entry, $folder_slug );

		if ( is_wp_error( $install ) ) {
			return $install;
		}

		$plugin_file = $install;

		$activation = $this->activate_plugin_file( $plugin_file );

		if ( is_wp_error( $activation ) ) {
			return array(
				'message' => sprintf(
					/* translators: 1: plugin name, 2: error message. */
					__( '%1$s installed but activation failed: %2$s', 'themeisle-tester' ),
					$name,
					$activation->get_error_message()
				),
				'details' => array( $plugin_file ),
			);
		}

		return array(
			'message' => sprintf(
				/* translators: %s: plugin name. */
				__( '%s installed and activated.', 'themeisle-tester' ),
				$name
			),
			'details' => array( $plugin_file ),
		);
	}

	/**
	 * Load the popular plugins catalog.
	 *
	 * @return array<string,array{name:string,source:string,slug?:string,zip_url?:string}>
	 */
	private function get_popular_catalog() {
		if ( null !== $this->popular_catalog ) {
			return $this->popular_catalog;
		}

		$path = TTP_PLUGIN_DIR . 'includes/addons/wordpress/ttp-popular-plugins-catalog.php';

		if ( ! is_readable( $path ) ) {
			$this->popular_catalog = array();
			return $this->popular_catalog;
		}

		/**
		 * Catalog array from ttp-popular-plugins-catalog.php.
		 *
		 * @var mixed $catalog
		 */
		$catalog = require $path;

		if ( ! is_array( $catalog ) ) {
			$this->popular_catalog = array();
			return $this->popular_catalog;
		}

		$normalized = array();

		foreach ( $catalog as $key => $entry ) {
			if ( ! is_string( $key ) || ! is_array( $entry ) ) {
				continue;
			}

			$catalog_slug = sanitize_key( $key );

			if ( '' === $catalog_slug || ! isset( $entry['name'], $entry['source'] ) ) {
				continue;
			}

			if ( ! is_string( $entry['name'] ) || ! is_string( $entry['source'] ) ) {
				continue;
			}

			$normalized[ $catalog_slug ] = array(
				'name'   => $entry['name'],
				'source' => $entry['source'],
			);

			if ( isset( $entry['slug'] ) && is_string( $entry['slug'] ) ) {
				$normalized[ $catalog_slug ]['slug'] = sanitize_key( $entry['slug'] );
			}

			if ( isset( $entry['zip_url'] ) && is_string( $entry['zip_url'] ) ) {
				$normalized[ $catalog_slug ]['zip_url'] = esc_url_raw( $entry['zip_url'] );
			}
		}

		$this->popular_catalog = $normalized;

		return $this->popular_catalog;
	}

	/**
	 * Folder slug used to locate an installed plugin on disk.
	 *
	 * @param string              $catalog_slug Catalog key.
	 * @param array<string,mixed> $entry        Catalog entry.
	 * @return string
	 */
	private function catalog_folder_slug( $catalog_slug, $entry ) {
		if ( isset( $entry['slug'] ) && is_string( $entry['slug'] ) && '' !== $entry['slug'] ) {
			return sanitize_key( $entry['slug'] );
		}

		return sanitize_key( $catalog_slug );
	}

	/**
	 * Detect install/activation status for a plugin folder slug.
	 *
	 * @param string $folder_slug Plugin directory slug (e.g. woocommerce).
	 * @return array{status:string,plugin_file:string}
	 */
	private function get_plugin_row_status( $folder_slug ) {
		$folder_slug = sanitize_key( $folder_slug );

		if ( '' === $folder_slug ) {
			return array(
				'status'      => 'not_installed',
				'plugin_file' => '',
			);
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$prefix      = $folder_slug . '/';
		$plugin_file = '';

		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( 0 === strpos( $file, $prefix ) ) {
				$plugin_file = $file;
				break;
			}
		}

		if ( '' === $plugin_file ) {
			return array(
				'status'      => 'not_installed',
				'plugin_file' => '',
			);
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return array(
				'status'      => 'active',
				'plugin_file' => $plugin_file,
			);
		}

		return array(
			'status'      => 'inactive',
			'plugin_file' => $plugin_file,
		);
	}

	/**
	 * Require upgrader dependencies and verify filesystem access.
	 *
	 * @return true|WP_Error
	 */
	private function bootstrap_plugin_upgrader() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'ttp_filesystem_unavailable', __( 'The site filesystem is not writable; cannot install plugins from here.', 'themeisle-tester' ) );
		}

		return true;
	}

	/**
	 * Install a catalog entry; returns plugin file basename on success.
	 *
	 * @param array<string,mixed> $entry       Catalog entry.
	 * @param string              $folder_slug Folder slug for org installs.
	 * @return string|WP_Error Plugin file path relative to plugins dir.
	 */
	private function install_catalog_entry( $entry, $folder_slug ) {
		$source = isset( $entry['source'] ) && is_string( $entry['source'] ) ? $entry['source'] : '';

		if ( 'zip' === $source ) {
			$zip_url = isset( $entry['zip_url'] ) && is_string( $entry['zip_url'] ) ? $entry['zip_url'] : '';

			if ( '' === $zip_url ) {
				return new WP_Error( 'ttp_catalog_misconfigured', __( 'ZIP catalog entry is missing a zip_url.', 'themeisle-tester' ) );
			}

			return $this->install_from_zip_url( $zip_url );
		}

		$org_slug = isset( $entry['slug'] ) && is_string( $entry['slug'] ) ? sanitize_key( $entry['slug'] ) : $folder_slug;

		if ( '' === $org_slug ) {
			return new WP_Error( 'ttp_catalog_misconfigured', __( 'WordPress.org catalog entry is missing a slug.', 'themeisle-tester' ) );
		}

		return $this->install_from_wordpress_org( $org_slug );
	}

	/**
	 * Install a plugin from wordpress.org by directory slug.
	 *
	 * @param string $slug Plugin directory slug.
	 * @return string|WP_Error Plugin file path relative to plugins dir.
	 */
	private function install_from_wordpress_org( $slug ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'sections' => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		if ( ! is_object( $api ) || ! isset( $api->download_link ) || ! is_string( $api->download_link ) || '' === $api->download_link ) {
			return new WP_Error(
				'ttp_org_download_missing',
				sprintf(
					/* translators: %s: plugin slug. */
					__( 'Could not resolve a download URL for %s on WordPress.org.', 'themeisle-tester' ),
					$slug
				)
			);
		}

		return $this->install_from_zip_url( $api->download_link );
	}

	/**
	 * Install a plugin from a ZIP URL; returns plugin file on success.
	 *
	 * @param string $zip_url ZIP download URL.
	 * @return string|WP_Error Plugin file path relative to plugins dir.
	 */
	private function install_from_zip_url( $zip_url ) {
		$scheme = wp_parse_url( $zip_url, PHP_URL_SCHEME );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error(
				'ttp_invalid_zip_url',
				__( 'Install URL must use http or https.', 'themeisle-tester' )
			);
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $zip_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( true !== $result ) {
			$messages = $skin->get_upgrade_messages();
			$reason   = ! empty( $messages ) ? (string) end( $messages ) : __( 'Unknown installer error.', 'themeisle-tester' );

			return new WP_Error( 'ttp_install_failed', $reason );
		}

		$plugin_file = $upgrader->plugin_info();

		if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
			return new WP_Error(
				'ttp_plugin_file_unknown',
				__( 'Plugin installed but the plugin file could not be determined.', 'themeisle-tester' )
			);
		}

		return $plugin_file;
	}

	/**
	 * Activate a plugin by its plugin file path.
	 *
	 * @param string $plugin_file Plugin file relative to plugins directory.
	 * @return true|WP_Error
	 */
	private function activate_plugin_file( $plugin_file ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$activation = activate_plugin( $plugin_file );

		if ( is_wp_error( $activation ) ) {
			return $activation;
		}

		return true;
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
		$install = $this->install_from_zip_url( $zip_url );

		if ( is_wp_error( $install ) ) {
			++$failed;
			return sprintf(
				/* translators: 1: ZIP URL, 2: error message. */
				__( '%1$s — install failed: %2$s', 'themeisle-tester' ),
				$zip_url,
				$install->get_error_message()
			);
		}

		$plugin_file = $install;

		if ( ! $activate ) {
			++$succeeded;
			return sprintf(
				/* translators: 1: plugin file, 2: ZIP URL. */
				__( '%1$s installed (%2$s).', 'themeisle-tester' ),
				$plugin_file,
				$zip_url
			);
		}

		$activation = $this->activate_plugin_file( $plugin_file );

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

	/**
	 * Render quick-install shortcuts above the ZIP install fields when needed.
	 *
	 * @phpstan-param NormalizedItem $item
	 *
	 * @param array<string,mixed> $item           Item definition.
	 * @param mixed               $inspect_result Inspect callback result.
	 * @param TTP_Admin_Page      $page           Admin page.
	 * @return void
	 */
	public function render_plugin_install_inspect( $item, $inspect_result, TTP_Admin_Page $page ) {

		if ( ! is_array( $inspect_result ) || ! isset( $inspect_result['shortcuts'] ) || ! is_array( $inspect_result['shortcuts'] ) ) {
			return;
		}

		$has_pending_shortcuts = false;

		foreach ( $inspect_result['shortcuts'] as $shortcut_row ) {
			if ( ! is_array( $shortcut_row ) ) {
				continue;
			}

			$plugin_status = isset( $shortcut_row['status'] ) && is_string( $shortcut_row['status'] ) ? $shortcut_row['status'] : '';

			if ( 'active' !== $plugin_status ) {
				$has_pending_shortcuts = true;
				break;
			}
		}

		if ( ! $has_pending_shortcuts ) {
			return;
		}

		$page->render_plugin_install_shortcuts( $item, $inspect_result['shortcuts'] );
		echo '<hr class="ttp-plugin-shortcuts__divider">';
		echo '<p class="ttp-plugin-shortcuts__zip-label">' . esc_html__( 'Custom ZIP URLs', 'themeisle-tester' ) . '</p>';
	}
}
