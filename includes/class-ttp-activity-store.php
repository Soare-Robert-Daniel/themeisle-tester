<?php
/**
 * Dashboard activity storage.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores a small recent activity list for the Dashboard.
 */
class TTP_Activity_Store {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'ttp_activity_log';

	/**
	 * Maximum entries to keep.
	 *
	 * @var int
	 */
	const LIMIT = 10;

	/**
	 * Add one activity entry.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @return void
	 */
	public function add( $entry ) {
		$entries = $this->get_all();

		array_unshift( $entries, $this->normalize_entry( $entry ) );

		$this->persist( array_slice( $entries, 0, self::LIMIT ) );
	}

	/**
	 * Get all recent activity entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_all() {
		$entries = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $entries ) ) {
			return array();
		}

		$clean = array();

		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) ) {
				$clean_entry = array();

				foreach ( $entry as $key => $value ) {
					if ( is_string( $key ) ) {
						$clean_entry[ $key ] = $value;
					}
				}

				$clean[] = $this->normalize_entry( $clean_entry );
			}
		}

		return $clean;
	}

	/**
	 * Normalize one entry.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @return array<string,mixed>
	 */
	private function normalize_entry( $entry ) {
		$details = isset( $entry['details'] ) && is_array( $entry['details'] ) ? $entry['details'] : array();

		return array(
			'time'       => isset( $entry['time'] ) && is_string( $entry['time'] ) ? sanitize_text_field( $entry['time'] ) : current_time( 'mysql' ),
			'item'       => isset( $entry['item'] ) && is_string( $entry['item'] ) ? sanitize_text_field( $entry['item'] ) : '',
			'action'     => isset( $entry['action'] ) && is_string( $entry['action'] ) ? sanitize_text_field( $entry['action'] ) : '',
			'result'     => isset( $entry['result'] ) && is_string( $entry['result'] ) ? sanitize_key( $entry['result'] ) : 'success',
			'message'    => isset( $entry['message'] ) && is_string( $entry['message'] ) ? sanitize_text_field( $entry['message'] ) : '',
			'details'    => $this->string_list( $details ),
			'created_at' => isset( $entry['created_at'] ) && is_int( $entry['created_at'] ) ? $entry['created_at'] : time(),
		);
	}

	/**
	 * Keep only scalar detail values as strings.
	 *
	 * @param array<mixed> $values Values.
	 * @return array<int,string>
	 */
	private function string_list( $values ) {
		$strings = array();

		foreach ( $values as $value ) {
			if ( is_scalar( $value ) ) {
				$strings[] = sanitize_text_field( (string) $value );
			}
		}

		return $strings;
	}

	/**
	 * Persist entries with autoload disabled.
	 *
	 * @param array<int,array<string,mixed>> $entries Entries.
	 * @return void
	 */
	private function persist( $entries ) {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, $entries, '', false );
			return;
		}

		update_option( self::OPTION_NAME, $entries, false );
	}
}
