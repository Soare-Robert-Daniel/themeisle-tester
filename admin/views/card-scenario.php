<?php
/**
 * Scenario card body: toggle + saved summary + fields + Save/Reset actions.
 *
 * Rendered by {@see admin/views/card.php} via render_view().
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                            $page Admin page instance.
 * @var array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool} $item Normalised item descriptor (NormalizedItem shape, type=scenario).
 */

defined( 'ABSPATH' ) || exit;

$state   = $page->get_scenario_state( $item['id'] );
$params  = isset( $state['params'] ) && is_array( $state['params'] ) ? $state['params'] : array();
$form_id = 'ttp-scenario-' . sanitize_html_class( $item['id'] );
?>
<form id="<?php echo esc_attr( $form_id ); ?>" method="post" class="ttp-card__body ttp-scenario-form" <?php echo $page->datastar_post_attr( 'scenarios/' . $item['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
	<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
	<input type="hidden" name="ttp_action" value="save_scenario">
	<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
	<label class="ttp-toggle">
		<input type="checkbox" name="ttp_enabled" value="1" <?php checked( ! empty( $state['enabled'] ) ); ?>>
		<?php esc_html_e( 'Enable this scenario', 'themeisle-tester' ); ?>
	</label>
	<?php $page->render_saved_summary( $item, $params ); ?>
	<?php $page->render_fields( $item, $params ); ?>
</form>
<div class="ttp-card__actions">
	<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="button button-primary">
		<?php esc_html_e( 'Save', 'themeisle-tester' ); ?>
	</button>
	<form method="post" class="ttp-inline-form" <?php echo $page->datastar_post_attr( 'scenarios/' . $item['id'] . '/reset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
		<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
		<input type="hidden" name="ttp_action" value="reset_scenario">
		<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Reset', 'themeisle-tester' ); ?></button>
	</form>
</div>
