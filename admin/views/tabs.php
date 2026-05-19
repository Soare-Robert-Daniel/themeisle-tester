<?php
/**
 * Tablist + panels.
 *
 * Rendered by {@see TTP_Admin_Page::render_tabs()} via render_view().
 *
 * The `items` shape inside each tab mirrors {@see TTP_Item_Registry}'s
 * NormalizedItem alias — inlined here because PHPStan does not honour
 * `@phpstan-import-type` from file-level docblocks in partials.
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       $page Admin page instance.
 * @var array<int,array{category:string,items:array<string,array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool}>,tab_id:string,panel_id:string,active:int}> $tabs Precomputed tab descriptors.
 */

defined( 'ABSPATH' ) || exit;
?>
<ul class="ttp-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Testing categories', 'themeisle-tester' ); ?>" data-ignore>
	<?php foreach ( $tabs as $index => $tab_data ) : ?>
		<li role="presentation">
			<button
				type="button"
				class="ttp-tab"
				role="tab"
				id="<?php echo esc_attr( $tab_data['tab_id'] ); ?>"
				aria-controls="<?php echo esc_attr( $tab_data['panel_id'] ); ?>"
				aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
				tabindex="<?php echo 0 === $index ? '0' : '-1'; ?>"
			>
				<span><?php echo esc_html( $tab_data['category'] ); ?></span>
				<span id="ttp-tab-indicator-<?php echo esc_attr( sanitize_title( $tab_data['category'] ) ); ?>" class="ttp-tab__indicator-slot">
					<?php
					if ( $tab_data['active'] > 0 ) :
						/* translators: %d: number of active scenarios in this category. */
						$indicator_label = sprintf( _n( '%d active scenario', '%d active scenarios', $tab_data['active'], 'themeisle-tester' ), $tab_data['active'] );
						?>
						<span class="ttp-tab__indicator" aria-label="<?php echo esc_attr( $indicator_label ); ?>"></span>
					<?php endif; ?>
				</span>
			</button>
		</li>
	<?php endforeach; ?>
</ul>

<div class="ttp-panels">
	<?php foreach ( $tabs as $index => $tab_data ) : ?>
		<section
			class="ttp-panel"
			id="<?php echo esc_attr( $tab_data['panel_id'] ); ?>"
			role="tabpanel"
			aria-labelledby="<?php echo esc_attr( $tab_data['tab_id'] ); ?>"
			<?php echo 0 === $index ? '' : 'hidden'; ?>
		>
			<?php $page->render_panel_groups( $tab_data['items'] ); ?>
		</section>
	<?php endforeach; ?>
</div>
