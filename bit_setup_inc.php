<?php
/**
 * @package switchboard
 */

global $gBitSystem, $gLibertySystem;

$registerHash = array(
	'package_name' => 'switchboard',
	'package_path' => dirname( __FILE__ ).'/',
	'homeable' => TRUE,
);
$gBitSystem->registerPackage( $registerHash );

if( $gBitSystem->isPackageActive( 'switchboard' ) ) {
	/*
	$menuHash = array(
		'package_name'       => SWITCHBOARD_PKG_NAME,
		'index_url'          => SWITCHBOARD_PKG_URL.'index.php',
		'menu_template'      => 'bitpackage:switchboard/menu_switchboard.tpl',
	);
	$gBitSystem->registerAppMenu( $menuHash );
	*/

	$gLibertySystem->registerService(
		SWITCHBOARD_PKG_NAME, SWITCHBOARD_PKG_NAME, array(
		'content_expunge_function'  => 'switchboard_content_expunge',
		'content_icon_tpl'           => 'bitpackage:switchboard/service_content_icon_inc.tpl',
	) );

//	$gBitSystem->registerNotifyEvent( array( "switchboard_request" => tra("A switchboard request is made.") ) );
//	$gBitSystem->registerNotifyEvent( array( "switchboard_reply" => tra("A switchboard reply is made.") ) );

	require_once( 'SwitchboardSystem.php' );
}
?>
