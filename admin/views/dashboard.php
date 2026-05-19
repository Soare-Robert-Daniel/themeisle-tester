<?php
/**
 * Dashboard page shell.
 *
 * Rendered by {@see TTP_Admin_Page::render()} via render_view().
 *
 * The `$categories` shape mirrors {@see TTP_Item_Registry}'s NormalizedItem alias —
 * inlined here because PHPStan does not honour `@phpstan-import-type` from
 * file-level docblocks in partials.
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                                                            $page       Admin page instance.
 * @var array<string,array<string,array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool,dashboard_hidden:bool,requires:array<string,array<string,string>>}>> $categories Items grouped by category.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ttp-dashboard">
	<header class="ttp-dashboard__intro">
		<div>
			<h1><?php esc_html_e( 'Themeisle Tester', 'themeisle-tester' ); ?></h1>
			<p><?php esc_html_e( 'Create, inspect, and reset controlled Themeisle testing conditions.', 'themeisle-tester' ); ?></p>
		</div>
	</header>

	<?php $page->render_action_result(); ?>

	<?php if ( empty( $categories ) ) : ?>
		<p class="ttp-empty"><?php esc_html_e( 'No Testing Items have been registered yet.', 'themeisle-tester' ); ?></p>
	<?php else : ?>
		<?php $page->render_tabs( $categories ); ?>
		<?php $page->render_activity(); ?>
	<?php endif; ?>
</div>
