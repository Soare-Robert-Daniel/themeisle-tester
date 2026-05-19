<?php
/**
 * Builds a PPOM field group covering every free PPOM input type and attaches it
 * to a WooCommerce product via a shared TTP test category.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generator for the "free fields" PPOM field group fixture.
 */
class TTP_PPOM_Free_Fields_Generator {

	/**
	 * Option name tracking PPOM group IDs created by this utility.
	 */
	public const OPTION_GROUP_IDS = 'ttp_ppom_generated_group_ids';

	/**
	 * Option name storing the most recent generated pair record.
	 */
	public const OPTION_LAST_TARGET = 'ttp_ppom_last_test_target';

	/**
	 * Prefix applied to every generated PPOM field group name.
	 */
	private const GROUP_NAME_PREFIX = 'TTP — Free fields test';

	/**
	 * Product post meta key PPOM uses to attach a field group directly to one product.
	 *
	 * Mirrors the PPOM_PRODUCT_META_KEY constant defined by woocommerce-product-addon.
	 */
	private const PPOM_PRODUCT_META_KEY = '_product_meta_id';

	/**
	 * Build a PPOM field group covering every free field type and attach it
	 * directly to the given product via the PPOM product-meta key.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array{ppom_group_id:int}|WP_Error
	 */
	public function generate( $product_id ) {
		$product_id = (int) $product_id;

		if ( $product_id <= 0 ) {
			return new WP_Error( 'ttp_ppom_invalid_product', __( 'Invalid product ID for PPOM field group generation.', 'themeisle-tester' ) );
		}

		if ( ! function_exists( 'ppom_meta_repository' ) ) {
			return new WP_Error( 'ttp_ppom_unavailable', __( 'PPOM for WooCommerce is not active on this site.', 'themeisle-tester' ) );
		}

		$group_id = $this->insert_field_group();

		if ( is_wp_error( $group_id ) ) {
			return $group_id;
		}

		$meta_key = defined( 'PPOM_PRODUCT_META_KEY' ) ? PPOM_PRODUCT_META_KEY : self::PPOM_PRODUCT_META_KEY;

		update_post_meta( $product_id, $meta_key, array( $group_id ) );

		$this->track_group_id( $group_id );

		return array(
			'ppom_group_id' => $group_id,
		);
	}

	/**
	 * Delete every PPOM field group ID previously tracked by this generator.
	 *
	 * @return array{deleted:array<int,int>,missing:array<int,int>}
	 */
	public function delete_tracked_groups() {
		$tracked = $this->read_tracked_group_ids();

		$deleted = array();
		$missing = array();

		if ( ! function_exists( 'ppom_meta_repository' ) ) {
			delete_option( self::OPTION_GROUP_IDS );
			delete_option( self::OPTION_LAST_TARGET );

			return array(
				'deleted' => $deleted,
				'missing' => $tracked,
			);
		}

		$repository = ppom_meta_repository();

		foreach ( $tracked as $group_id ) {
			$result = $repository->delete_by_id( $group_id );

			if ( false === $result || 0 === (int) $result ) {
				$missing[] = $group_id;
				continue;
			}

			$deleted[] = $group_id;
		}

		delete_option( self::OPTION_GROUP_IDS );
		delete_option( self::OPTION_LAST_TARGET );

		return array(
			'deleted' => $deleted,
			'missing' => $missing,
		);
	}

	/**
	 * Insert a new PPOM field group with no category/tag rules.
	 *
	 * @return int|WP_Error
	 */
	private function insert_field_group() {
		$fields = $this->build_free_field_definitions();
		$json   = wp_json_encode( $fields );

		if ( ! is_string( $json ) ) {
			return new WP_Error( 'ttp_ppom_encode_failed', __( 'Failed to encode PPOM field definitions to JSON.', 'themeisle-tester' ) );
		}

		$now = current_time( 'mysql' );

		$data = array(
			'productmeta_name'       => self::GROUP_NAME_PREFIX . ' (' . wp_date( 'Y-m-d H:i:s' ) . ')',
			'productmeta_validation' => '',
			'productmeta_disabled'   => '',
			'dynamic_price_display'  => '',
			'send_file_attachment'   => '',
			'show_cart_thumb'        => '',
			'productmeta_categories' => '',
			'productmeta_tags'       => '',
			'the_meta'               => $json,
			'productmeta_created'    => $now,
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$repository = ppom_meta_repository();
		$new_id     = $repository->insert_group( $data, $format );

		if ( $new_id <= 0 ) {
			return new WP_Error( 'ttp_ppom_insert_failed', __( 'PPOM did not return an ID after inserting the field group.', 'themeisle-tester' ) );
		}

		return $new_id;
	}

	/**
	 * Build minimal field definitions for every free PPOM field type.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function build_free_field_definitions() {
		return array(
			$this->text_field( 'ttp_text', __( 'Text', 'themeisle-tester' ), __( 'Sample text', 'themeisle-tester' ) ),
			$this->textarea_field( 'ttp_textarea', __( 'Textarea', 'themeisle-tester' ) ),
			$this->email_field( 'ttp_email', __( 'Email', 'themeisle-tester' ) ),
			$this->number_field( 'ttp_number', __( 'Number', 'themeisle-tester' ) ),
			$this->hidden_field( 'ttp_hidden', __( 'Hidden', 'themeisle-tester' ) ),
			$this->checkbox_field( 'ttp_checkbox', __( 'Checkbox', 'themeisle-tester' ) ),
			$this->radio_field( 'ttp_radio', __( 'Radio', 'themeisle-tester' ) ),
			$this->select_field( 'ttp_select', __( 'Select', 'themeisle-tester' ) ),
			$this->date_field( 'ttp_date', __( 'Date', 'themeisle-tester' ) ),
		);
	}

	/**
	 * Build a text input definition.
	 *
	 * @param string $data_name     Field slug.
	 * @param string $title         Field label.
	 * @param string $default_value Default value.
	 * @return array<string,mixed>
	 */
	private function text_field( $data_name, $title, $default_value = '' ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'text' ),
			array(
				'default_value' => $default_value,
				'placeholder'   => __( 'Enter text', 'themeisle-tester' ),
				'required'      => '',
			)
		);
	}

	/**
	 * Build a textarea input definition.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @return array<string,mixed>
	 */
	private function textarea_field( $data_name, $title ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'textarea' ),
			array(
				'default_value' => '',
				'placeholder'   => __( 'Enter longer text', 'themeisle-tester' ),
				'rows'          => '4',
				'required'      => '',
			)
		);
	}

	/**
	 * Build an email input definition.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @return array<string,mixed>
	 */
	private function email_field( $data_name, $title ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'email' ),
			array(
				'default_value' => '',
				'placeholder'   => 'name@example.com',
				'required'      => '',
			)
		);
	}

	/**
	 * Build a number input definition.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @return array<string,mixed>
	 */
	private function number_field( $data_name, $title ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'number' ),
			array(
				'default_value' => '1',
				'min'           => '0',
				'max'           => '100',
				'step'          => '1',
				'required'      => '',
			)
		);
	}

	/**
	 * Build a hidden input definition.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @return array<string,mixed>
	 */
	private function hidden_field( $data_name, $title ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'hidden' ),
			array(
				'default_value' => 'ttp-hidden-value',
			)
		);
	}

	/**
	 * Build a checkbox input definition with two options.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @return array<string,mixed>
	 */
	private function checkbox_field( $data_name, $title ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'checkbox' ),
			array(
				'options'  => $this->sample_options( true ),
				'required' => '',
			)
		);
	}

	/**
	 * Build a radio input definition with two options.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @return array<string,mixed>
	 */
	private function radio_field( $data_name, $title ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'radio' ),
			array(
				'options'  => $this->sample_options( true ),
				'required' => '',
			)
		);
	}

	/**
	 * Build a select input definition with two options.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @return array<string,mixed>
	 */
	private function select_field( $data_name, $title ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'select' ),
			array(
				'options'           => $this->sample_options( false ),
				'first_option_text' => __( 'Choose an option', 'themeisle-tester' ),
				'required'          => '',
			)
		);
	}

	/**
	 * Build a date input definition.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @return array<string,mixed>
	 */
	private function date_field( $data_name, $title ) {
		return array_merge(
			$this->base_field( $data_name, $title, 'date' ),
			array(
				'default_value'   => '',
				'date_format'     => 'Y-m-d',
				'date_picker_min' => '',
				'date_picker_max' => '',
				'required'        => '',
			)
		);
	}

	/**
	 * Common keys shared by every field definition.
	 *
	 * @param string $data_name Field slug.
	 * @param string $title     Field label.
	 * @param string $type      PPOM field type slug.
	 * @return array<string,mixed>
	 */
	private function base_field( $data_name, $title, $type ) {
		return array(
			'data_name'   => $data_name,
			'type'        => $type,
			'title'       => $title,
			'description' => '',
			'class'       => '',
			'width'       => '12',
			'status'      => 'on',
			'visibility'  => 'all',
			'roles'       => array(),
		);
	}

	/**
	 * Build a small option set for choice fields.
	 *
	 * @param bool $first_default Whether the first option should be pre-selected.
	 * @return array<int,array<string,string>>
	 */
	private function sample_options( $first_default ) {
		return array(
			array(
				'option'  => __( 'Option A', 'themeisle-tester' ),
				'price'   => '',
				'default' => $first_default ? 'on' : '',
			),
			array(
				'option'  => __( 'Option B', 'themeisle-tester' ),
				'price'   => '',
				'default' => '',
			),
		);
	}

	/**
	 * Append the new PPOM group ID to the persistent tracking option.
	 *
	 * @param int $group_id Newly created PPOM group ID.
	 * @return void
	 */
	private function track_group_id( $group_id ) {
		$ids   = $this->read_tracked_group_ids();
		$ids[] = (int) $group_id;

		update_option( self::OPTION_GROUP_IDS, array_values( array_unique( $ids ) ), false );
	}

	/**
	 * Read the persisted list of generated PPOM group IDs.
	 *
	 * @return array<int,int>
	 */
	private function read_tracked_group_ids() {
		$stored = get_option( self::OPTION_GROUP_IDS, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$ids = array();

		foreach ( $stored as $value ) {
			if ( ! is_numeric( $value ) ) {
				continue;
			}

			$id = (int) $value;

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}
}
