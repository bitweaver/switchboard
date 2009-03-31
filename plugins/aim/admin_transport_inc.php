<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/plugins/aim/admin_transport_inc.php,v 1.2 2009/03/31 05:53:55 lsces Exp $
 * @package switchboard
 * @subpackage plugins
 */

/**
 * Initialization
 */
$formTransportAim = array(
	"switchboard_aim_screenname" => array(
		'label' => 'AIM ScreenName',
		'note' => 'This is the ScreenName from which messages will be sent.',
		'default' => '',
	),
	"switchboard_aim_password" => array(
		'label' => 'AIM Password',
		'note' => 'This is the password for the ScreenName.',
		'default' => '',
	),
	"switchboard_aim_server" => array(
		'label' => 'AIM Server',
		'note' => 'This is the address of AOL\'s AIM server.',
		'default' => 'aimexpress.oscar.aol.com',
	),
	"switchboard_aim_local_ip" => array(
		'label' => 'Local Client IP',
		'note' => 'This is address used to passed messages to the local AIM client, which will then send the IM.',
		'default' => '127.0.0.1',
	),
	"switchboard_aim_local_port" => array(
		'label' => 'Local Client Port',
		'note' => 'This is the port number used to pass messages to the local AIM client.',
		'default' => 5190,
	),
);
$gBitSmarty->assign( 'formTransportAim',$formTransportAim );

if( !empty( $_POST ) ) {

	foreach( array_keys( $formTransportAim ) as $key ) {
		if( empty( $_REQUEST[$key] ) || $_REQUEST[$key] != $formTransportAim[$key]['default'] ) {
			$gBitSystem->storeConfig( $key, isset( $_REQUEST[$key] ) ? $_REQUEST[$key] : NULL );
		}
	}
	$gBitSystem->storeConfig( 'switchboard_default_transport', $_REQUEST['switchboard_default_transport'] );
}

?>
