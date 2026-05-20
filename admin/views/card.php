<?php
/**
 * Widget chrome — name plate (LED + title + info), per-type body, footer (type + product + edit).
 *
 * Rendered by {@see TTP_Admin_Page::render_panel_groups()} via render_view().
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                            $page Admin page instance.
 * @var array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool,dashboard_hidden:bool,requires:array<string,array<string,string>>,render_inspect:callable|null,render_run:callable|null,inspect_on_load:bool,inspect_refresh:bool,run_ui:array{transport:string}} $item Normalised item descriptor (NormalizedItem shape).
 */

defined( 'ABSPATH' ) || exit;

$is_active = false;

if ( 'scenario' === $item['type'] && ! empty( $item['available'] ) ) {
	$state     = $page->get_scenario_state( $item['id'] );
	$is_active = ! empty( $state['enabled'] );
}

$classes = array( 'ttp-card', 'ttp-card--' . $item['type'], 'ttp-card--width-' . $item['width'] );

if ( $is_active ) {
	$classes[] = 'ttp-card--scenario-active';
}

if ( 'danger_utility' === $item['type'] ) {
	$classes[] = 'ttp-card--danger';
}

if ( empty( $item['available'] ) ) {
	$classes[] = 'ttp-card--unavailable';
}

$type_marks = array(
	'scenario'       => 'S',
	'utility'        => 'U',
	'danger_utility' => 'D',
);
$type_mark  = isset( $type_marks[ $item['type'] ] ) ? $type_marks[ $item['type'] ] : '·';
$type_label = $page->type_label( $item['type'] );
?>
<article id="ttp-card-<?php echo esc_attr( $item['id'] ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-ttp-item-id="<?php echo esc_attr( $item['id'] ); ?>" data-ttp-item-type="<?php echo esc_attr( $item['type'] ); ?>" <?php echo $page->datastar_busy_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>>
	<header class="ttp-card__nameplate">
		<h3 class="ttp-card__title">
			<span class="ttp-card__title-text"><?php echo esc_html( $item['label'] ); ?></span>
		</h3>
		<?php if ( '' !== $item['description'] ) : ?>
			<?php $tooltip_id = 'ttp-tip-' . sanitize_html_class( $item['id'] ); ?>
			<span class="ttp-card__info" tabindex="0" role="button" aria-describedby="<?php echo esc_attr( $tooltip_id ); ?>" aria-label="<?php echo esc_attr( $item['description'] ); ?>">
				<span class="ttp-card__info-mark" aria-hidden="true">i</span>
				<span class="ttp-card__info-tooltip" role="tooltip" id="<?php echo esc_attr( $tooltip_id ); ?>"><?php echo esc_html( $item['description'] ); ?></span>
			</span>
		<?php endif; ?>
	</header>

	<?php if ( empty( $item['available'] ) ) : ?>
		<p class="ttp-note ttp-note--error"><?php echo esc_html( '' !== $item['unavailable_reason'] ? $item['unavailable_reason'] : __( 'Testing Item is unavailable.', 'themeisle-tester' ) ); ?></p>
	<?php elseif ( 'scenario' === $item['type'] ) : ?>
		<?php $page->render_view( 'card-scenario', array( 'item' => $item ) ); ?>
	<?php elseif ( 'utility' === $item['type'] ) : ?>
		<?php $page->render_view( 'card-utility', array( 'item' => $item ) ); ?>
	<?php elseif ( 'danger_utility' === $item['type'] ) : ?>
		<?php $page->render_view( 'card-danger', array( 'item' => $item ) ); ?>
	<?php endif; ?>

	<footer class="ttp-card__footer">
		<span class="ttp-typemark ttp-typemark--<?php echo esc_attr( $item['type'] ); ?>" aria-label="<?php echo esc_attr( $type_label ); ?>"><?php echo esc_html( $type_mark ); ?></span>
		<span class="ttp-card__product"><?php echo esc_html( $item['product'] ); ?></span>
	</footer>

</article>
