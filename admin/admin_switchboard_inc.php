<?php


global $gSwitchboardSystem;

$transportConfigs = array();

$pluginsDir = SWITCHBOARD_PKG_PATH.'plugins/';
foreach( array_keys( $gSwitchboardSystem->mTransports ) as $transport ) {
	if( file_exists( $pluginsDir.$transport.'/admin_transport_inc.php' ) ) {
		include( $pluginsDir.$transport.'/admin_transport_inc.php' );
	}
	if( file_exists( $pluginsDir.$transport.'/admin_transport_inc.tpl' ) ) {
		$transportConfigs[$transport] = $pluginsDir.$transport.'/admin_transport_inc.tpl';
	}
}
$gBitSmarty->assign( 'transportConfigs', $transportConfigs );

if( !empty( $_POST ) ) {
	$gBitSystem->storeConfig( 'switchboard_default_transport', $_REQUEST['switchboard_default_transport'] );
}

?>
