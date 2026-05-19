<?php
/**
 * Utility card body: optional inspect read-out + Run form/button.
 *
 * Rendered by {@see admin/views/card.php} via render_view().
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                            $page Admin page instance.
 * @var array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool} $item Normalised item descriptor (NormalizedItem shape, type=utility).
 */

defined( 'ABSPATH' ) || exit;

if ( is_callable( $item['inspect'] ) ) {
	$inspect_result = call_user_func( $item['inspect'], $item, array() );

	echo '<div class="ttp-card__body">';
	$page->render_result_table( $inspect_result );
	echo '</div>';
}

if ( ! is_callable( $item['run'] ) ) {
	return;
}

$has_fields = ! empty( $item['fields'] );

if ( $has_fields ) :
	$form_id = 'ttp-utility-' . sanitize_html_class( $item['id'] );
	?>
	<form id="<?php echo esc_attr( $form_id ); ?>" method="post" class="ttp-card__body" <?php echo $page->datastar_post_attr( 'utilities/' . $item['id'] . '/run' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
		<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
		<input type="hidden" name="ttp_action" value="run_utility">
		<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
		<?php $page->render_fields( $item, array() ); ?>
	</form>
	<div class="ttp-card__actions">
		<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="button button-primary">
			<?php esc_html_e( 'Run', 'themeisle-tester' ); ?>
		</button>
	</div>
	<?php
	return;
endif;
?>
<div class="ttp-card__actions">
	<form method="post" class="ttp-inline-form" <?php echo $page->datastar_post_attr( 'utilities/' . $item['id'] . '/run' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
		<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
		<input type="hidden" name="ttp_action" value="run_utility">
		<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
		<button type="submit" class="button button-primary">
			<?php esc_html_e( 'Run', 'themeisle-tester' ); ?>
		</button>
	</form>
</div>
