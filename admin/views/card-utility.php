<?php
/**
 * Utility card body: optional inspect read-out + Run form/button.
 *
 * Rendered by {@see admin/views/card.php} via render_view().
 *
 * @package Themeisle_Tester
 *
 * @var TTP_Admin_Page                                                                                                                                                                                                                                                                                                                                                                                                                                            $page Admin page instance.
 * @var array{id:string,type:string,categories:array<int,string>,group:string,product:string,label:string,description:string,width:string,fields:array<int,array<string,mixed>>,apply:callable|null,inspect:callable|null,run:callable|null,mutate:callable|null,restore:callable|null,is_available:callable|null,unavailable_reason_callback:callable|null,unavailable_reason:string,available:bool,dashboard_hidden:bool,requires:array<string,array<string,string>>,render_inspect:callable|null,render_run:callable|null,inspect_on_load:bool,inspect_refresh:bool,run_ui:array{transport:string}} $item Normalised item descriptor (NormalizedItem shape, type=utility).
 */

defined( 'ABSPATH' ) || exit;

$presenter = $page->get_card_presenter();

$presenter->render_inspect_section( $item );
$presenter->render_run_section( $item );
