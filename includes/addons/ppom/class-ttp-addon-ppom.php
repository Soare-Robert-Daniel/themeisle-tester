<?php
/**
 * PPOM for WooCommerce Testing Items.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers PPOM inspect utilities for QA workflows.
 */
class TTP_Addon_PPOM implements TTP_Addon {

	/**
	 * Generator for the free-fields test fixture.
	 *
	 * @var TTP_PPOM_Free_Fields_Generator
	 */
	private $generator;

	/**
	 * WooCommerce product factory used to create the fixture product.
	 *
	 * @var TTP_WooCommerce_Product_Factory
	 */
	private $product_factory;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->generator       = new TTP_PPOM_Free_Fields_Generator();
		$this->product_factory = new TTP_WooCommerce_Product_Factory();
	}

	/**
	 * Register PPOM Testing Items.
	 *
	 * @param TTP_Item_Registry $registry Item registry.
	 * @return void
	 */
	public function register( TTP_Item_Registry $registry ) {
		$ppom_tab      = __( 'PPOM', 'themeisle-tester' );
		$ppom_product  = __( 'PPOM for WooCommerce', 'themeisle-tester' );
		$field_groups  = __( 'Field groups', 'themeisle-tester' );
		$test_fixtures = __( 'Test fixtures', 'themeisle-tester' );

		$registry->register(
			array(
				'id'              => 'ppom_inspect_field_groups',
				'type'            => 'utility',
				'categories'      => array( $ppom_tab ),
				'group'           => $field_groups,
				'product'         => $ppom_product,
				'label'           => __( 'Inspect field groups', 'themeisle-tester' ),
				'description'     => __( 'Lists every PPOM field group row from the database with decoded field definitions and raw the_meta JSON.', 'themeisle-tester' ),
				'width'           => 'full',
				'requires'        => TTP_Integration_Checks::require_ppom(),
				'inspect'         => array( $this, 'inspect_field_groups' ),
				'render_inspect'  => array( $this, 'render_inspect_field_groups' ),
				'inspect_on_load' => false,
				'inspect_refresh' => true,
			)
		);

		$registry->register(
			array(
				'id'             => 'ppom_generate_free_fields_test_group',
				'type'           => 'utility',
				'categories'     => array( $ppom_tab ),
				'group'          => $test_fixtures,
				'product'        => $ppom_product,
				'label'          => __( 'Generate free-fields test product', 'themeisle-tester' ),
				'description'    => __( 'Creates a WooCommerce product attached to a freshly generated PPOM field group containing every free PPOM field type, then surfaces a clickable link to the product page.', 'themeisle-tester' ),
				'width'          => 'wide',
				'requires'       => TTP_Integration_Checks::require_ppom(),
				'inspect'        => array( $this, 'inspect_last_generated_target' ),
				'render_inspect' => array( $this, 'render_inspect_last_generated_target' ),
				'run'            => array( $this, 'run_generate_free_fields_test_group' ),
			)
		);

		$registry->register(
			array(
				'id'          => 'ppom_delete_generated_groups',
				'type'        => 'utility',
				'categories'  => array( $ppom_tab ),
				'group'       => $test_fixtures,
				'product'     => $ppom_product,
				'label'       => __( 'Delete generated PPOM groups', 'themeisle-tester' ),
				'description' => __( 'Permanently deletes every PPOM field group previously created by the free-fields test fixture utility.', 'themeisle-tester' ),
				'width'       => 'normal',
				'requires'    => TTP_Integration_Checks::require_ppom(),
				'run'         => array( $this, 'run_delete_generated_groups' ),
			)
		);
	}

	/**
	 * Inspect all PPOM field groups from the database (read-only).
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Sanitized inspect payload (unused).
	 * @return array<string,mixed>|WP_Error
	 */
	public function inspect_field_groups( $item, $payload ) {
		unset( $payload );

		if ( ! TTP_Integration_Checks::meets_item_requirements( $item ) ) {
			return new WP_Error( 'ttp_ppom_unavailable', TTP_Integration_Checks::unavailable_reason_for_item( $item ) );
		}

		global $wpdb;

		$table_constant = defined( 'PPOM_TABLE_META' ) ? PPOM_TABLE_META : 'nm_personalized';
		$table_name     = $wpdb->prefix . $table_constant;

		$rows   = ppom_meta_repository()->get_all_rows();
		$groups = array();

		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}

			$vars = get_object_vars( $row );
			$id   = isset( $vars['productmeta_id'] ) && is_numeric( $vars['productmeta_id'] ) ? absint( $vars['productmeta_id'] ) : 0;

			if ( $id <= 0 ) {
				continue;
			}

			$groups[] = $this->format_group_row( $row, $id );
		}

		if ( empty( $groups ) ) {
			return array(
				'database_table' => $table_name,
				'group_count'    => 0,
				'_note'          => __( 'No PPOM field groups found in the database.', 'themeisle-tester' ),
				'groups'         => array(),
			);
		}

		return array(
			'database_table' => $table_name,
			'group_count'    => count( $groups ),
			'groups'         => $groups,
		);
	}

	/**
	 * Return the persisted last-run record for the free-fields fixture utility.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Sanitized inspect payload (unused).
	 * @return array<string,mixed>
	 */
	public function inspect_last_generated_target( $item, $payload ) {
		unset( $item, $payload );

		$stored = get_option( TTP_PPOM_Free_Fields_Generator::OPTION_LAST_TARGET, array() );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return array( 'has_target' => false );
		}

		return array(
			'has_target'    => true,
			'product_id'    => isset( $stored['product_id'] ) && is_numeric( $stored['product_id'] ) ? (int) $stored['product_id'] : 0,
			'ppom_group_id' => isset( $stored['ppom_group_id'] ) && is_numeric( $stored['ppom_group_id'] ) ? (int) $stored['ppom_group_id'] : 0,
			'product_url'   => isset( $stored['product_url'] ) && is_string( $stored['product_url'] ) ? $stored['product_url'] : '',
			'product_title' => isset( $stored['product_title'] ) && is_string( $stored['product_title'] ) ? $stored['product_title'] : '',
			'generated_at'  => isset( $stored['generated_at'] ) && is_numeric( $stored['generated_at'] ) ? (int) $stored['generated_at'] : 0,
		);
	}

	/**
	 * Generate a fresh WC product + PPOM free-fields group pair.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Sanitized run payload (unused).
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_generate_free_fields_test_group( $item, $payload ) {
		unset( $payload );

		if ( ! TTP_Integration_Checks::meets_item_requirements( $item ) ) {
			return new WP_Error( 'ttp_ppom_unavailable', TTP_Integration_Checks::unavailable_reason_for_item( $item ) );
		}

		$batch_id = 'ttp-ppom-' . substr( md5( uniqid( '', true ) ), 0, 12 );

		$product_id = $this->product_factory->create_simple( $batch_id, 1, 'publish', 9.99, 99.99 );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		$result = $this->generator->generate( $product_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$product       = wc_get_product( $product_id );
		$product_title = $product ? (string) get_the_title( $product_id ) : '';
		$product_url   = (string) get_permalink( $product_id );

		if ( '' === $product_title ) {
			/* translators: %d: WooCommerce product ID. */
			$product_title = sprintf( __( 'Product #%d', 'themeisle-tester' ), $product_id );
		}

		$generated_at = time();

		update_option(
			TTP_PPOM_Free_Fields_Generator::OPTION_LAST_TARGET,
			array(
				'product_id'    => (int) $product_id,
				'ppom_group_id' => (int) $result['ppom_group_id'],
				'product_url'   => $product_url,
				'product_title' => $product_title,
				'generated_at'  => $generated_at,
			),
			false
		);

		return array(
			'message' => sprintf(
				/* translators: 1: product title, 2: PPOM group ID. */
				__( 'Created PPOM test product "%1$s" with field group #%2$d.', 'themeisle-tester' ),
				$product_title,
				(int) $result['ppom_group_id']
			),
			'details' => array(
				/* translators: %d: WooCommerce product ID. */
				sprintf( __( 'Product ID: %d', 'themeisle-tester' ), (int) $product_id ),
				/* translators: %d: PPOM group ID. */
				sprintf( __( 'PPOM group ID: %d', 'themeisle-tester' ), (int) $result['ppom_group_id'] ),
				/* translators: %s: product permalink. */
				sprintf( __( 'Product URL: %s', 'themeisle-tester' ), $product_url ),
			),
		);
	}

	/**
	 * Delete every tracked PPOM field group created by the free-fields utility.
	 *
	 * @param array<string,mixed> $item    Item definition.
	 * @param array<string,mixed> $payload Sanitized run payload (unused).
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_delete_generated_groups( $item, $payload ) {
		unset( $payload );

		if ( ! TTP_Integration_Checks::meets_item_requirements( $item ) ) {
			return new WP_Error( 'ttp_ppom_unavailable', TTP_Integration_Checks::unavailable_reason_for_item( $item ) );
		}

		$outcome = $this->generator->delete_tracked_groups();

		$deleted_count = count( $outcome['deleted'] );
		$missing_count = count( $outcome['missing'] );

		$details = array();

		if ( $deleted_count > 0 ) {
			$details[] = sprintf(
				/* translators: %s: comma-separated PPOM group IDs. */
				__( 'Deleted PPOM group IDs: %s', 'themeisle-tester' ),
				implode( ', ', array_map( 'strval', $outcome['deleted'] ) )
			);
		}

		if ( $missing_count > 0 ) {
			$details[] = sprintf(
				/* translators: %s: comma-separated PPOM group IDs. */
				__( 'Tracked but no longer present: %s', 'themeisle-tester' ),
				implode( ', ', array_map( 'strval', $outcome['missing'] ) )
			);
		}

		if ( 0 === $deleted_count && 0 === $missing_count ) {
			$details[] = __( 'No tracked PPOM groups to delete.', 'themeisle-tester' );
		}

		return array(
			'message' => sprintf(
				/* translators: 1: number of deleted groups, 2: number of missing tracked entries. */
				__( 'PPOM cleanup complete — deleted %1$d group(s), %2$d stale tracking entries cleared.', 'themeisle-tester' ),
				$deleted_count,
				$missing_count
			),
			'details' => $details,
		);
	}

	/**
	 * Format a group database row for inspect output.
	 *
	 * @param object $row      PPOM meta group row.
	 * @param int    $group_id Group ID.
	 * @return array<string,mixed>
	 */
	private function format_group_row( $row, $group_id ) {
		$vars = get_object_vars( $row );

		$the_meta = $this->row_string( $vars, 'the_meta' );
		$decoded  = '' !== $the_meta ? json_decode( $the_meta, true ) : null;
		$fields   = is_array( $decoded ) ? $this->compact_fields( $decoded, $group_id ) : array();

		$group = array(
			'productmeta_id'         => $group_id,
			'productmeta_name'       => $this->row_string( $vars, 'productmeta_name' ),
			'productmeta_disabled'   => $this->row_string( $vars, 'productmeta_disabled' ),
			'productmeta_validation' => $this->row_string( $vars, 'productmeta_validation' ),
			'productmeta_created'    => $this->row_string( $vars, 'productmeta_created' ),
			'productmeta_categories' => $this->row_string( $vars, 'productmeta_categories' ),
			'productmeta_tags'       => $this->row_string( $vars, 'productmeta_tags' ),
			'dynamic_price_display'  => $this->row_string( $vars, 'dynamic_price_display' ),
			'send_file_attachment'   => $this->row_string( $vars, 'send_file_attachment' ),
			'show_cart_thumb'        => $this->row_string( $vars, 'show_cart_thumb' ),
			'field_count'            => count( $fields ),
			'fields'                 => $fields,
		);

		if ( '' !== $the_meta ) {
			$group['the_meta_json'] = $the_meta;
		}

		return $group;
	}

	/**
	 * Build compact field rows from decoded the_meta JSON.
	 *
	 * @param array<mixed> $decoded  Decoded the_meta array.
	 * @param int          $group_id Owning group ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function compact_fields( array $decoded, $group_id ) {
		$compact = array();

		foreach ( $decoded as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$status = isset( $field['status'] ) && is_string( $field['status'] ) ? $field['status'] : 'on';

			$row = array(
				'data_name' => isset( $field['data_name'] ) && is_string( $field['data_name'] ) ? $field['data_name'] : '',
				'type'      => isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : '',
				'title'     => isset( $field['title'] ) && is_string( $field['title'] ) ? $field['title'] : '',
				'status'    => $status,
			);

			if ( isset( $field['ppom_id'] ) && ( is_int( $field['ppom_id'] ) || is_string( $field['ppom_id'] ) ) ) {
				$row['ppom_id'] = $field['ppom_id'];
			} else {
				$row['ppom_id'] = $group_id;
			}

			$compact[] = $row;
		}

		return $compact;
	}

	/**
	 * Read a string column from a PPOM group row object.
	 *
	 * @param array<string,mixed> $vars Row variables from get_object_vars().
	 * @param string              $key  Column name.
	 * @return string
	 */
	private function row_string( array $vars, $key ) {
		return isset( $vars[ $key ] ) && is_string( $vars[ $key ] ) ? $vars[ $key ] : '';
	}

	/**
	 * Render PPOM field-group inspect output.
	 *
	 * @param array<string,mixed> $item           Item definition.
	 * @param mixed               $inspect_result Inspect callback result.
	 * @param TTP_Admin_Page      $page           Admin page.
	 * @return void
	 */
	public function render_inspect_field_groups( $item, $inspect_result, TTP_Admin_Page $page ) {
		unset( $item );
		$page->render_ppom_field_groups( $inspect_result );
	}

	/**
	 * Render the last-generated PPOM fixture target panel.
	 *
	 * @param array<string,mixed> $item           Item definition.
	 * @param mixed               $inspect_result Inspect callback result.
	 * @param TTP_Admin_Page      $page           Admin page.
	 * @return void
	 */
	public function render_inspect_last_generated_target( $item, $inspect_result, TTP_Admin_Page $page ) {
		unset( $item );
		$page->render_ppom_last_target( $inspect_result );
	}
}
