<?php
/**
 * Danger Utility backup storage.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores first-mutation backups for Danger Utilities.
 */
class TTP_Danger_Backup_Store {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'ttp_danger_backups';

	/**
	 * Get all backups.
	 *
	 * @return array<mixed>
	 */
	public function get_all() {
		$backups = get_option( self::OPTION_NAME, array() );

		return is_array( $backups ) ? $backups : array();
	}

	/**
	 * Check whether a backup exists.
	 *
	 * @param string $utility_id Utility ID.
	 * @param string $target     Target identifier.
	 * @return bool
	 */
	public function has( $utility_id, $target ) {
		$backups = $this->get_all();

		return isset( $backups[ $utility_id ] ) && is_array( $backups[ $utility_id ] ) && array_key_exists( $target, $backups[ $utility_id ] );
	}

	/**
	 * Store a backup only if one does not already exist.
	 *
	 * @param string $utility_id Utility ID.
	 * @param string $target     Target identifier.
	 * @param mixed  $backup     Backup payload.
	 * @return void
	 */
	public function backup_once( $utility_id, $target, $backup ) {
		if ( $this->has( $utility_id, $target ) ) {
			return;
		}

		$backups = $this->get_all();

		if ( ! isset( $backups[ $utility_id ] ) || ! is_array( $backups[ $utility_id ] ) ) {
			$backups[ $utility_id ] = array();
		}

		$backups[ $utility_id ][ $target ] = $backup;

		$this->persist( $backups );
	}

	/**
	 * Get one backup.
	 *
	 * @param string $utility_id Utility ID.
	 * @param string $target     Target identifier.
	 * @return mixed|null
	 */
	public function get( $utility_id, $target ) {
		$backups = $this->get_all();

		if ( isset( $backups[ $utility_id ] ) && is_array( $backups[ $utility_id ] ) && array_key_exists( $target, $backups[ $utility_id ] ) ) {
			return $backups[ $utility_id ][ $target ];
		}

		return null;
	}

	/**
	 * Delete one backup.
	 *
	 * @param string $utility_id Utility ID.
	 * @param string $target     Target identifier.
	 * @return void
	 */
	public function delete( $utility_id, $target ) {
		$backups = $this->get_all();

		if ( isset( $backups[ $utility_id ] ) && is_array( $backups[ $utility_id ] ) ) {
			unset( $backups[ $utility_id ][ $target ] );

			if ( empty( $backups[ $utility_id ] ) ) {
				unset( $backups[ $utility_id ] );
			}
		}

		$this->persist( $backups );
	}

	/**
	 * Persist backups with autoload disabled.
	 *
	 * @param array<mixed> $backups Backups.
	 * @return void
	 */
	private function persist( $backups ) {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, $backups, '', false );
			return;
		}

		update_option( self::OPTION_NAME, $backups, false );
	}
}
