<?php
/**
 * Danger Utility card body: inspect with specialised license/install table renderers.
 *
 * Rendered by {@see admin/views/card.php} via render_view().
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                            $page Admin page instance.
 * @var array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool,dashboard_hidden:bool,requires:array<string,array<string,string>>} $item Normalised item descriptor (NormalizedItem shape, type=danger_utility).
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_callable( $item['inspect'] ) ) {
	return;
}

$inspect_result = call_user_func( $item['inspect'], $item, array() );

echo '<div class="ttp-card__body">';

if ( is_wp_error( $inspect_result ) ) {
	echo '<p class="ttp-note ttp-note--error">' . esc_html( $inspect_result->get_error_message() ) . '</p>';
	echo '</div>';
	return;
}

if ( 'license_data_editor' === $item['id'] && isset( $inspect_result['rows'] ) && is_array( $inspect_result['rows'] ) ) {
	$page->render_license_rows( $item, $inspect_result['rows'] );
	echo '</div>';
	return;
}

if ( 'install_timestamp_editor' === $item['id'] && isset( $inspect_result['rows'] ) && is_array( $inspect_result['rows'] ) ) {
	$has_reference = isset( $inspect_result['reference_date'] ) && is_string( $inspect_result['reference_date'] );

	echo '<div class="ttp-card__meta-bar">';
	if ( $has_reference ) {
		echo '<p class="ttp-card__reference"><strong>' . esc_html__( 'Reference date:', 'themeisle-tester' ) . '</strong> ' . esc_html( $inspect_result['reference_date'] ) . '</p>';
	}
	$page->render_force_license_refresh_action();
	echo '</div>';

	$page->render_install_rows( $item, $inspect_result['rows'] );
	echo '</div>';
	return;
}

$page->render_result_table( $inspect_result );
echo '</div>';
