<?php
/**
 * Danger Utility backup contract tests.
 *
 * @package Themeisle_Tester
 */

/**
 * Danger backup contract test case.
 */
class Test_Danger_Backup_Contract extends WP_UnitTestCase {

	/**
	 * Reset backups before each test.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( TTP_Danger_Backup_Store::OPTION_NAME );
	}

	/**
	 * Backup_once writes the first value.
	 */
	public function test_backup_once_writes_first_value() {
		$store = new TTP_Danger_Backup_Store();

		$store->backup_once( 'contract_danger', 'target_a', 'original' );

		$this->assertSame( 'original', $store->get( 'contract_danger', 'target_a' ) );
	}

	/**
	 * Backup_once does not overwrite an existing backup.
	 */
	public function test_backup_once_does_not_overwrite_existing_backup() {
		$store = new TTP_Danger_Backup_Store();

		$store->backup_once( 'contract_danger', 'target_a', 'original' );
		$store->backup_once( 'contract_danger', 'target_a', 'replacement' );

		$this->assertSame( 'original', $store->get( 'contract_danger', 'target_a' ) );
	}

	/**
	 * Mutate and restore use the first-change backup only.
	 */
	public function test_mutate_and_restore_use_first_change_backup() {
		$registry         = new TTP_Item_Registry();
		$scenario_store   = new TTP_Scenario_Store();
		$backup_store     = new TTP_Danger_Backup_Store();
		$schema_sanitizer = new TTP_Schema_Sanitizer();
		$activity_store   = new TTP_Activity_Store();
		$mutate_count     = 0;

		$registry->register(
			array(
				'id'         => 'contract_stub_danger',
				'type'       => 'danger_utility',
				'categories' => array( 'Contract' ),
				'product'    => 'Contract Product',
				'label'      => 'Stub Danger Utility',
				'inspect'    => static function () {
					return array( 'backup' => 'original' );
				},
				'mutate'     => static function () use ( &$mutate_count ) {
					++$mutate_count;
					return array( 'message' => 'mutated' );
				},
				'restore'    => static function ( $item, $target, $backup ) {
					unset( $item, $target );
					return array(
						'message' => 'restored',
						'backup'  => $backup,
					);
				},
			)
		);

		$actions = new TTP_Dashboard_Actions(
			$registry,
			$scenario_store,
			$backup_store,
			$schema_sanitizer,
			$activity_store
		);

		$item = $registry->get_item( 'contract_stub_danger' );

		$this->assertIsArray( $item );

		$first = $actions->mutate_danger( $item, 'target_a', array() );
		$this->assertIsArray( $first );
		$this->assertSame( 'original', $backup_store->get( 'contract_stub_danger', 'target_a' ) );

		$second = $actions->mutate_danger( $item, 'target_a', array() );
		$this->assertIsArray( $second );
		$this->assertSame( 'original', $backup_store->get( 'contract_stub_danger', 'target_a' ) );
		$this->assertSame( 2, $mutate_count );

		$restore = $actions->restore_danger( $item, 'target_a' );
		$this->assertIsArray( $restore );
		$this->assertSame( 'original', $restore['backup'] );
		$this->assertNull( $backup_store->get( 'contract_stub_danger', 'target_a' ) );
	}
}
