<?php
/**
 * Testing Item registry.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers, validates, and normalizes Testing Item definitions.
 *
 * @phpstan-type NormalizedItem array{
 *     id: string,
 *     type: string,
 *     categories: array<int,string>,
 *     group: string,
 *     product: string,
 *     label: string,
 *     description: string,
 *     fields: array<int,array<string,mixed>>,
 *     apply: callable|null,
 *     inspect: callable|null,
 *     run: callable|null,
 *     mutate: callable|null,
 *     restore: callable|null,
 *     is_available: callable|null,
 *     unavailable_reason_callback: callable|null,
 *     unavailable_reason: string,
 *     available: bool
 * }
 */
class TTP_Item_Registry {

	/**
	 * Raw item definitions.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $raw_items = array();

	/**
	 * Normalized items.
	 *
	 * @phpstan-var array<string,NormalizedItem>
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $items = array();

	/**
	 * Validation errors.
	 *
	 * @var array<int,string>
	 */
	private $errors = array();

	/**
	 * Whether registration is closed.
	 *
	 * @var bool
	 */
	private $finalized = false;

	/**
	 * Register a raw Testing Item schema.
	 *
	 * @param mixed $item Raw item schema.
	 * @return bool
	 */
	public function register( $item ) {
		if ( $this->finalized ) {
			$this->errors[] = __( 'Testing Items cannot be registered after registration has closed.', 'themeisle-tester' );
			return false;
		}

		if ( ! is_array( $item ) ) {
			$this->errors[] = __( 'Testing Item registration expects an array schema.', 'themeisle-tester' );
			return false;
		}

		$this->raw_items[] = $item;

		return true;
	}

	/**
	 * Normalize all registered items once.
	 *
	 * @return void
	 */
	public function finalize() {
		if ( $this->finalized ) {
			return;
		}

		foreach ( $this->raw_items as $item ) {
			$normalized = $this->normalize_item( $item );

			if ( is_wp_error( $normalized ) ) {
				$this->errors[] = $normalized->get_error_message();
				continue;
			}

			$id = $normalized['id'];

			if ( isset( $this->items[ $id ] ) ) {
				/* translators: %s: Testing Item ID. */
				$this->errors[] = sprintf( __( 'Duplicate Testing Item ID rejected: %s.', 'themeisle-tester' ), $id );
				continue;
			}

			$this->items[ $id ] = $normalized;
		}

		$this->finalized = true;
	}

	/**
	 * Get normalized items with public filters and availability attached.
	 *
	 * @phpstan-return array<string,NormalizedItem>
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_items() {
		$this->finalize();

		$items = array();

		foreach ( $this->items as $id => $item ) {
			/**
			 * Filters a single normalized Themeisle Tester Testing Item definition.
			 *
			 * Product plugins can use this to adjust their own item metadata after registration.
			 *
			 * @phpstan-param NormalizedItem $item
			 *
			 * @param array<string,mixed> $item Normalized item definition.
			 */
			$item = apply_filters( "ttp_item_definition_{$id}", $item );
			$item = $this->with_availability( $item );

			$items[ $id ] = $item;
		}

		/**
		 * Filters all normalized Themeisle Tester Testing Items.
		 *
		 * Product plugins may use this for last-mile metadata adjustments before Dashboard rendering.
		 *
		 * @phpstan-param array<string,NormalizedItem> $items
		 *
		 * @param array<string,array<string,mixed>> $items Normalized item definitions keyed by ID.
		 */
		return apply_filters( 'ttp_registered_items', $items );
	}

	/**
	 * Get one normalized item.
	 *
	 * @phpstan-return NormalizedItem|null
	 *
	 * @param string $id Item ID.
	 * @return array<string,mixed>|null
	 */
	public function get_item( $id ) {
		$items = $this->get_items();

		if ( isset( $items[ $id ] ) ) {
			return $items[ $id ];
		}

		return null;
	}

	/**
	 * Get registry errors.
	 *
	 * @return array<int,string>
	 */
	public function get_errors() {
		$this->finalize();

		return $this->errors;
	}

	/**
	 * Group items by category label.
	 *
	 * @phpstan-return array<string,array<string,NormalizedItem>>
	 *
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function get_items_by_category() {
		$categories = array();

		foreach ( $this->get_items() as $item ) {
			foreach ( $item['categories'] as $category ) {
				if ( ! isset( $categories[ $category ] ) ) {
					$categories[ $category ] = array();
				}

				$categories[ $category ][ $item['id'] ] = $item;
			}
		}

		ksort( $categories );

		return $categories;
	}

	/**
	 * Normalize and validate one item.
	 *
	 * @phpstan-return NormalizedItem|WP_Error
	 *
	 * @param array<string,mixed> $item Raw item.
	 * @return array<string,mixed>|WP_Error
	 */
	private function normalize_item( $item ) {
		$id = isset( $item['id'] ) && is_string( $item['id'] ) ? sanitize_key( $item['id'] ) : '';

		if ( '' === $id ) {
			return new WP_Error( 'ttp_missing_item_id', __( 'A Testing Item is missing a valid id.', 'themeisle-tester' ) );
		}

		$type = isset( $item['type'] ) && is_string( $item['type'] ) ? sanitize_key( $item['type'] ) : '';

		if ( ! in_array( $type, array( 'scenario', 'utility', 'danger_utility' ), true ) ) {
			/* translators: %s: Testing Item ID. */
			return new WP_Error( 'ttp_invalid_item_type', sprintf( __( 'Testing Item %s has an invalid type.', 'themeisle-tester' ), $id ) );
		}

		$raw_categories = isset( $item['categories'] ) && is_array( $item['categories'] ) ? $item['categories'] : array();
		$categories     = array();

		foreach ( $raw_categories as $category ) {
			if ( is_string( $category ) ) {
				$clean = sanitize_text_field( $category );

				if ( '' !== $clean ) {
					$categories[] = $clean;
				}
			}
		}

		if ( empty( $categories ) ) {
			/* translators: %s: Testing Item ID. */
			return new WP_Error( 'ttp_missing_categories', sprintf( __( 'Testing Item %s must define at least one Category.', 'themeisle-tester' ), $id ) );
		}

		$product = isset( $item['product'] ) && is_string( $item['product'] ) ? sanitize_text_field( $item['product'] ) : '';
		$label   = isset( $item['label'] ) && is_string( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';

		if ( '' === $product || '' === $label ) {
			/* translators: %s: Testing Item ID. */
			return new WP_Error( 'ttp_missing_labels', sprintf( __( 'Testing Item %s must define product and label.', 'themeisle-tester' ), $id ) );
		}

		$required_callbacks = $this->get_required_callbacks( $type );

		foreach ( $required_callbacks as $callback_name ) {
			if ( empty( $item[ $callback_name ] ) || ! is_callable( $item[ $callback_name ] ) ) {
				/* translators: 1: Testing Item ID, 2: callback name. */
				return new WP_Error( 'ttp_missing_callback', sprintf( __( 'Testing Item %1$s must define a callable %2$s callback.', 'themeisle-tester' ), $id, $callback_name ) );
			}
		}

		$fields = array();

		if ( isset( $item['fields'] ) && is_array( $item['fields'] ) ) {
			foreach ( $item['fields'] as $field ) {
				if ( is_array( $field ) ) {
					$normalized_field = array();

					foreach ( $field as $field_key => $field_value ) {
						if ( is_string( $field_key ) ) {
							$normalized_field[ $field_key ] = $field_value;
						}
					}

					$fields[] = $normalized_field;
				}
			}
		}

		return array(
			'id'                          => $id,
			'type'                        => $type,
			'categories'                  => $categories,
			'group'                       => isset( $item['group'] ) && is_string( $item['group'] ) ? sanitize_text_field( $item['group'] ) : '',
			'product'                     => $product,
			'label'                       => $label,
			'description'                 => isset( $item['description'] ) && is_string( $item['description'] ) ? sanitize_text_field( $item['description'] ) : '',
			'fields'                      => $fields,
			'apply'                       => isset( $item['apply'] ) && is_callable( $item['apply'] ) ? $item['apply'] : null,
			'inspect'                     => isset( $item['inspect'] ) && is_callable( $item['inspect'] ) ? $item['inspect'] : null,
			'run'                         => isset( $item['run'] ) && is_callable( $item['run'] ) ? $item['run'] : null,
			'mutate'                      => isset( $item['mutate'] ) && is_callable( $item['mutate'] ) ? $item['mutate'] : null,
			'restore'                     => isset( $item['restore'] ) && is_callable( $item['restore'] ) ? $item['restore'] : null,
			'is_available'                => isset( $item['is_available'] ) && is_callable( $item['is_available'] ) ? $item['is_available'] : null,
			'unavailable_reason_callback' => isset( $item['unavailable_reason'] ) && is_callable( $item['unavailable_reason'] ) ? $item['unavailable_reason'] : null,
			'unavailable_reason'          => '',
			'available'                   => true,
		);
	}

	/**
	 * Get callbacks required by item type.
	 *
	 * @param string $type Item type.
	 * @return array<int,string>
	 */
	private function get_required_callbacks( $type ) {
		if ( 'scenario' === $type ) {
			return array( 'apply' );
		}

		if ( 'danger_utility' === $type ) {
			return array( 'inspect', 'mutate', 'restore' );
		}

		return array();
	}

	/**
	 * Resolve availability fields.
	 *
	 * @phpstan-param NormalizedItem $item
	 * @phpstan-return NormalizedItem
	 *
	 * @param array<string,mixed> $item Item.
	 * @return array<string,mixed>
	 */
	private function with_availability( $item ) {
		$available       = true;
		$is_available_cb = $item['is_available'];
		$reason_callback = $item['unavailable_reason_callback'];

		if ( null !== $is_available_cb ) {
			$available = (bool) call_user_func( $is_available_cb, $item );
		}

		/**
		 * Filters whether a Themeisle Tester Testing Item is available.
		 *
		 * Product plugins can disable their items when dependencies are missing.
		 *
		 * @param bool                $available Whether the item is available.
		 * @param array<string,mixed> $item      Normalized item definition.
		 */
		$available = (bool) apply_filters( 'ttp_item_available', $available, $item );

		$reason = '';

		if ( ! $available && null !== $reason_callback ) {
			$raw_reason = call_user_func( $reason_callback, $item );
			$reason     = is_string( $raw_reason ) ? $raw_reason : '';
		}

		/**
		 * Filters the unavailable reason for a Themeisle Tester Testing Item.
		 *
		 * Product plugins should return a human-readable reason shown in the Dashboard.
		 *
		 * @param string              $reason Unavailable reason.
		 * @param array<string,mixed> $item   Normalized item definition.
		 */
		$item['unavailable_reason'] = apply_filters( 'ttp_item_unavailable_reason', $reason, $item );
		$item['available']          = $available;

		return $item;
	}
}
