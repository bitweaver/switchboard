<?php
/**
 * @version $Header$
 * @package switchboard
 * @subpackage plugins-aim
 */

/**
 * Initialization
 */
global $gSwitchboardSystem;

define( 'PLUGIN_GUID_TRANSPORT_AIM', 'aim' );

$pluginParams = array(
	'package' => 'switchboard',
	'transport_type' => PLUGIN_GUID_TRANSPORT_AIM,
	'title' => 'AOL Instant Messenger (AIM)',
	'description' => 'This transport handler will do send an instant message via the AIM network.',
	'requirements' => '',
	'send_function' => 'transport_aim_send',
	'receive_function' => 'transport_aim_receive',
	'expunge_function' => 'transport_aim_expunge',
	'use_queue' => FALSE,
);

$gSwitchboardSystem->registerTransport( PLUGIN_GUID_TRANSPORT_AIM, $pluginParams );

function transport_aim_receive( $pMsg ){
}

/**
 * Queue a message to be sent.
 *
 * @param array $pParamHash Array of message to be sent
 *
 * @param array $pParamHash['subject'] A string the message subject
 * @param array $pParamHash['message'] A string the message itself aka body
 * @param array $pParamHash['headers'] An Array of headers
 * @param array $pParamHash['recipients'] An Array of recipients, where each recipient is an array with key value pair of 'email' => emailaddress
 **/
function transport_aim_send( &$pParamHash ) {
	$gBitSystem->getConfig( 'switchboard_aim_server_ip', '127.0.0.1' );
	$gBitSystem->getConfig( 'switchboard_aim_server_port', '5190' );

	$listenAddress = $gBitSystem->getConfig( 'switchboard_aim_server_ip', '127.0.0.1' ).':'.$gBitSystem->getConfig( 'switchboard_aim_server_port', '5190' );
	$im = substr( $pParamHash['subject'].' => '.$pParamHash['message'], 0, 1024 );
	foreach( $pParamHash['headers'] as $screenName ) {
		$fp = stream_socket_client( "tcp://listenAddress", $errno, $errstr, 30 );
		if( !$fp ) {
			echo "$errstr ($errno)<br />\n";
		} else {
			fwrite($fp, $screenName.':'.$im );
			while (!feof($fp)) {
				var_dump(fgets($fp, 1024));
			}
			fclose($fp);
		}
	}

	return TRUE;
}

function transport_aim_expunge( $pMsg ){
}

