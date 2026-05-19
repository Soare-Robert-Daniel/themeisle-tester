<?php
/**
 * Utility card body: optional inspect read-out + Run form/button.
 *
 * Rendered by {@see admin/views/card.php} via render_view().
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                            $page Admin page instance.
 * @var array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool,dashboard_hidden:bool,requires:array<string,array<string,string>>} $item Normalised item descriptor (NormalizedItem shape, type=utility).
 */

defined( 'ABSPATH' ) || exit;

if ( 'install_plugin_from_zip' === $item['id'] && is_callable( $item['inspect'] ) ) {
	$inspect_result = call_user_func( $item['inspect'], $item, array() );

	$has_pending_shortcuts = false;

	if ( is_array( $inspect_result ) && isset( $inspect_result['shortcuts'] ) && is_array( $inspect_result['shortcuts'] ) ) {
		foreach ( $inspect_result['shortcuts'] as $shortcut_row ) {
			if ( ! is_array( $shortcut_row ) ) {
				continue;
			}

			$plugin_status = isset( $shortcut_row['status'] ) && is_string( $shortcut_row['status'] ) ? $shortcut_row['status'] : '';

			if ( 'active' !== $plugin_status ) {
				$has_pending_shortcuts = true;
				break;
			}
		}
	}

	if ( $has_pending_shortcuts ) {
		echo '<div class="ttp-card__body">';
		$page->render_plugin_install_shortcuts( $item, $inspect_result['shortcuts'] );
		echo '<hr class="ttp-plugin-shortcuts__divider">';
		echo '<p class="ttp-plugin-shortcuts__zip-label">' . esc_html__( 'Custom ZIP URLs', 'themeisle-tester' ) . '</p>';
		echo '</div>';
	}
}

if ( is_callable( $item['inspect'] ) && 'install_plugin_from_zip' !== $item['id'] ) {
	$inspect_result = call_user_func( $item['inspect'], $item, array() );

	echo '<div class="ttp-card__body">';
	if ( 'ppom_inspect_field_groups' === $item['id'] ) {
		$page->render_ppom_field_groups( $inspect_result );
	} elseif ( 'ppom_generate_free_fields_test_group' === $item['id'] ) {
		$page->render_ppom_last_target( $inspect_result );
	} elseif ( 'sdk_inspect_logger' === $item['id'] ) {
		$page->render_logger_inspect( $inspect_result );
	} else {
		$page->render_result_table( $inspect_result );
	}
	echo '</div>';
}

if ( ! is_callable( $item['run'] ) || 'sdk_inspect_logger' === $item['id'] ) {
	return;
}

$has_fields = ! empty( $item['fields'] );

if ( $has_fields ) :
	$form_id      = 'ttp-utility-' . sanitize_html_class( $item['id'] );
	$form_item    = $item;
	$hidden_slugs = array( 'plugin_slug' );

	if ( 'install_plugin_from_zip' === $item['id'] ) {
		$visible_fields = array();

		foreach ( $item['fields'] as $field ) {
			if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				continue;
			}

			if ( in_array( $field['id'], $hidden_slugs, true ) ) {
				continue;
			}

			$visible_fields[] = $field;
		}

		$form_item['fields'] = $visible_fields;
	}

	$is_wc_generate = 'woocommerce_generate_random_products' === $item['id'];
	$form_attrs     = '';

	if ( 'install_plugin_from_zip' === $item['id'] ) {
		$form_attrs = ' data-ttp-zip-install-form';
	} elseif ( $is_wc_generate ) {
		$form_attrs = ' data-ttp-wc-generate-form';
	}

	$datastar_attr = $is_wc_generate ? '' : $page->datastar_post_attr( 'utilities/' . $item['id'] . '/run' );
	?>
	<form id="<?php echo esc_attr( $form_id ); ?>" method="post" class="ttp-card__body"<?php echo $form_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static data attribute. ?> <?php echo $datastar_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper when present. ?>>
		<?php wp_nonce_field( 'ttp_admin_action', 'ttp_nonce' ); ?>
		<input type="hidden" name="ttp_action" value="run_utility">
		<input type="hidden" name="ttp_item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
		<?php $page->render_fields( $form_item, array() ); ?>
	</form>
	<div class="ttp-card__actions">
		<?php if ( 'install_plugin_from_zip' === $item['id'] ) : ?>
			<span class="ttp-card__run-status" data-ttp-zip-install-status role="status" aria-live="polite" hidden><?php esc_html_e( 'Installing plugins…', 'themeisle-tester' ); ?></span>
		<?php elseif ( $is_wc_generate ) : ?>
			<div class="ttp-wc-generate-progress" data-ttp-wc-generate-progress hidden>
				<div
					class="ttp-wc-generate-progress__track"
					role="progressbar"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-valuenow="0"
					data-ttp-wc-generate-progress-track
				>
					<div class="ttp-wc-generate-progress__bar" data-ttp-wc-generate-progress-bar style="width: 0%"></div>
				</div>
				<p class="ttp-card__run-status ttp-wc-generate-progress__status" data-ttp-wc-generate-status role="status" aria-live="polite"></p>
			</div>
		<?php endif; ?>
		<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="button button-primary"<?php echo 'install_plugin_from_zip' === $item['id'] ? ' data-ttp-zip-install-submit' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute. ?><?php echo $is_wc_generate ? ' data-ttp-wc-generate-submit' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute. ?>>
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
