<?php
/**
 * PHPStan stubs for PPOM APIs used by Themeisle Tester.
 *
 * @package Themeisle_Tester
 */

/**
 * PPOM meta repository stub.
 */
class PPOM_Meta_Repository {

	/**
	 * @return array<int,mixed>
	 */
	public function get_all_rows() {
		return array();
	}

	/**
	 * @param array<string,mixed> $data   Column => value.
	 * @param array<int,string>   $format wpdb format placeholders.
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public function insert_group( array $data, array $format ) {
		return 0;
	}

	/**
	 * @param int $id Row ID.
	 * @return int|false Rows deleted or false.
	 */
	public function delete_by_id( $id ) {
		return 0;
	}
}

/**
 * @return PPOM_Meta_Repository
 */
function ppom_meta_repository() {
	return new PPOM_Meta_Repository();
}
