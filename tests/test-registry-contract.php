<?php
/**
 * Registry contract tests.
 *
 * @package Themeisle_Tester
 */

/**
 * Registry contract test case.
 */
class Test_Registry_Contract extends WP_UnitTestCase {

	/**
	 * Build a minimal valid utility schema.
	 *
	 * @param string $id Item ID.
	 * @return array<string,mixed>
	 */
	private function utility_schema( $id ) {
		return array(
			'id'         => $id,
			'type'       => 'utility',
			'categories' => array( 'Contract' ),
			'product'    => 'Contract Product',
			'label'      => 'Contract Utility',
			'run'        => static function () {
				return array( 'message' => 'ok' );
			},
		);
	}

	/**
	 * Valid utility registers and is available.
	 */
	public function test_valid_utility_registers_and_is_available() {
		$registry = new TTP_Item_Registry();
		$this->assertTrue( $registry->register( $this->utility_schema( 'contract_valid_utility' ) ) );

		$item = $registry->get_item( 'contract_valid_utility' );

		$this->assertIsArray( $item );
		$this->assertTrue( $item['available'] );
	}

	/**
	 * Invalid item produces a useful registry error.
	 */
	public function test_invalid_item_is_rejected_with_useful_reason() {
		$registry = new TTP_Item_Registry();
		$registry->register(
			array(
				'id'         => 'contract_missing_label',
				'type'       => 'utility',
				'categories' => array( 'Contract' ),
				'product'    => 'Contract Product',
				'run'        => static function () {
					return array();
				},
			)
		);

		$errors = $registry->get_errors();

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'contract_missing_label', implode( ' ', $errors ) );
	}

	/**
	 * Duplicate IDs are rejected.
	 */
	public function test_duplicate_ids_are_rejected() {
		$registry = new TTP_Item_Registry();
		$registry->register( $this->utility_schema( 'contract_duplicate' ) );
		$registry->register( $this->utility_schema( 'contract_duplicate' ) );

		$errors = $registry->get_errors();

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'contract_duplicate', implode( ' ', $errors ) );
	}

	/**
	 * Requires gate availability and unavailable reason.
	 */
	public function test_requires_blocks_availability_with_declared_reason() {
		$registry = new TTP_Item_Registry();
		$reason   = 'Missing ContractDependencyClass.';

		$registry->register(
			array(
				'id'         => 'contract_requires_gate',
				'type'       => 'utility',
				'categories' => array( 'Contract' ),
				'product'    => 'Contract Product',
				'label'      => 'Requires Utility',
				'requires'   => array(
					'classes' => array(
						'ContractDependencyClass' => $reason,
					),
				),
				'run'        => static function () {
					return array( 'message' => 'ok' );
				},
			)
		);

		$item = $registry->get_item( 'contract_requires_gate' );

		$this->assertIsArray( $item );
		$this->assertFalse( $item['available'] );
		$this->assertSame( $reason, $item['unavailable_reason'] );
	}

	/**
	 * Is_available callback is ANDed with requires.
	 */
	public function test_is_available_callback_is_anded_with_requires() {
		$registry = new TTP_Item_Registry();

		$registry->register(
			array(
				'id'           => 'contract_requires_and_callback',
				'type'         => 'utility',
				'categories'   => array( 'Contract' ),
				'product'      => 'Contract Product',
				'label'        => 'Requires And Callback',
				'requires'     => array(
					'classes' => array(
						'ContractDependencyClass' => 'Missing class.',
					),
				),
				'is_available' => static function () {
					return true;
				},
				'run'          => static function () {
					return array( 'message' => 'ok' );
				},
			)
		);

		$item = $registry->get_item( 'contract_requires_and_callback' );

		$this->assertIsArray( $item );
		$this->assertFalse( $item['available'] );
	}
}
