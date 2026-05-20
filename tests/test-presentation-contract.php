<?php
/**
 * Presentation contract tests.
 *
 * @package Themeisle_Tester
 */

/**
 * Presentation contract test case.
 */
class Test_Presentation_Contract extends WP_UnitTestCase {

	/**
	 * Inspect is not called on render when inspect_on_load is false.
	 */
	public function test_lazy_inspect_does_not_call_inspect_on_render() {
		$inspect_calls = 0;
		$registry      = new TTP_Item_Registry();

		$registry->register(
			array(
				'id'              => 'contract_lazy_inspect',
				'type'            => 'utility',
				'categories'      => array( 'Contract' ),
				'product'         => 'Contract Product',
				'label'           => 'Lazy Inspect Utility',
				'inspect'         => static function () use ( &$inspect_calls ) {
					++$inspect_calls;
					return array( 'rows' => array() );
				},
				'inspect_on_load' => false,
			)
		);

		$item = $registry->get_item( 'contract_lazy_inspect' );

		$this->assertIsArray( $item );
		$this->assertFalse( $item['inspect_on_load'] );

		$admin_page = new TTP_Admin_Page(
			$registry,
			new TTP_Scenario_Store(),
			new TTP_Danger_Backup_Store(),
			new TTP_Dashboard_Actions(
				$registry,
				new TTP_Scenario_Store(),
				new TTP_Danger_Backup_Store(),
				new TTP_Schema_Sanitizer(),
				new TTP_Activity_Store()
			),
			new TTP_Activity_Store()
		);

		ob_start();
		$admin_page->get_card_presenter()->render_inspect_section( $item );
		$html = (string) ob_get_clean();

		$this->assertSame( 0, $inspect_calls );
		$this->assertStringContainsString( 'ttp-card-inspect-contract_lazy_inspect', $html );
		$this->assertStringContainsString( 'Load', $html );
	}

	/**
	 * Inspect_item invokes the inspect callback once.
	 */
	public function test_inspect_item_calls_inspect_callback() {
		$inspect_calls = 0;
		$registry      = new TTP_Item_Registry();

		$registry->register(
			array(
				'id'              => 'contract_inspect_action',
				'type'            => 'utility',
				'categories'      => array( 'Contract' ),
				'product'         => 'Contract Product',
				'label'           => 'Inspect Action Utility',
				'inspect'         => static function () use ( &$inspect_calls ) {
					++$inspect_calls;
					return array( 'message' => 'ok' );
				},
				'inspect_on_load' => false,
			)
		);

		$item    = $registry->get_item( 'contract_inspect_action' );
		$actions = new TTP_Dashboard_Actions(
			$registry,
			new TTP_Scenario_Store(),
			new TTP_Danger_Backup_Store(),
			new TTP_Schema_Sanitizer(),
			new TTP_Activity_Store()
		);

		$this->assertIsArray( $item );

		$result = $actions->inspect_item( $item, array() );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $inspect_calls );
	}
}
