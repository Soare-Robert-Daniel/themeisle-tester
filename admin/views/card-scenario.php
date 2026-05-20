<?php
/**
 * Scenario widget body: readout, fields, action row (rocker + Save/Discard).
 *
 * Rendered by {@see admin/views/card.php} via render_view().
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                            $page Admin page instance.
 * @var array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool,dashboard_hidden:bool,requires:array<string,array<string,string>>,render_inspect:callable|null,render_run:callable|null,inspect_on_load:bool,inspect_refresh:bool,run_ui:array{transport:string}} $item Normalised item descriptor (NormalizedItem shape, type=scenario).
 */

defined( 'ABSPATH' ) || exit;

$state        = $page->get_scenario_state( $item['id'] );
$params       = isset( $state['params'] ) && is_array( $state['params'] ) ? $state['params'] : array();
$is_enabled   = ! empty( $state['enabled'] );
$has_fields   = ! empty( $item['fields'] );
$rocker_id    = 'ttp-rocker-' . sanitize_html_class( $item['id'] );
$rocker_form  = 'ttp-rocker-form-' . sanitize_html_class( $item['id'] );
$save_form_id = 'ttp-scenario-' . sanitize_html_class( $item['id'] );
?>
<div class="ttp-card__readout">
	<?php $page->render_saved_summary( $item, $params ); ?>
</div>

<?php if ( $has_fields ) : ?>
	<form
		id="<?php echo esc_attr( $save_form_id ); ?>"
		method="post"
		class="ttp-card__body ttp-scenario-form"
		data-ttp-scenario-form
		<?php echo $page->datastar_post_attr( 'scenarios/' . $item['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
	>
		<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
		<input type="hidden" name="ttp_action" value="save_scenario">
		<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
		<input type="hidden" name="ttp_enabled" value="<?php echo $is_enabled ? '1' : '0'; ?>">
		<?php $page->render_fields( $item, $params ); ?>
	</form>
<?php endif; ?>

<div class="ttp-card__actions ttp-card__actions--scenario">
	<?php if ( $has_fields ) : ?>
		<button type="submit" form="<?php echo esc_attr( $save_form_id ); ?>" class="button button-primary">
			<?php esc_html_e( 'Save', 'themeisle-tester' ); ?>
		</button>
		<form method="post" class="ttp-inline-form" <?php echo $page->datastar_post_attr( 'scenarios/' . $item['id'] . '/reset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
			<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
			<input type="hidden" name="ttp_action" value="reset_scenario">
			<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Discard', 'themeisle-tester' ); ?></button>
		</form>
	<?php endif; ?>

	<form
		id="<?php echo esc_attr( $rocker_form ); ?>"
		method="post"
		class="ttp-rocker-form"
		data-ttp-rocker-form
		<?php echo $page->datastar_post_attr( 'scenarios/' . $item['id'] . '/toggle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
	>
		<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
		<input type="hidden" name="ttp_action" value="toggle_scenario">
		<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
		<input
			type="checkbox"
			id="<?php echo esc_attr( $rocker_id ); ?>"
			name="ttp_enabled"
			value="1"
			class="ttp-rocker__input"
			<?php checked( $is_enabled ); ?>
			data-ttp-rocker-input
		>
		<label for="<?php echo esc_attr( $rocker_id ); ?>" class="ttp-rocker" data-ttp-rocker>
			<span class="ttp-rocker__side ttp-rocker__side--off"><?php esc_html_e( 'OFF', 'themeisle-tester' ); ?></span>
			<span class="ttp-rocker__track" aria-hidden="true"><span class="ttp-rocker__knob"></span></span>
			<span class="ttp-rocker__side ttp-rocker__side--on"><?php esc_html_e( 'ON', 'themeisle-tester' ); ?></span>
		</label>
	</form>
</div>
