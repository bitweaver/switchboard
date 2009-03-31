<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/plugins/none/transport.php,v 1.3 2009/03/31 08:19:52 lsces Exp $
 * @package switchboard
 * @subpackage plugins-none
 */

/**
 * Initialization
 */
global $gSwitchboardSystem;

define( 'PLUGIN_GUID_TRANSPORT_NONE', 'none' );

$pluginParams = array(
	'package' => 'switchboard',
	'transport_type' => PLUGIN_GUID_TRANSPORT_EMAIL,
	'title' => 'None',
	'description' => 'This transport handler will do nothing and ignore any messages.',
	'requirements' => 'none.',
	'send_function' => 'transport_none_send',
	'receive_function' => 'transport_none_receive',
	'expunge_function' => 'transport_none_expunge',
	'use_queue' => FALSE,
);

$gSwitchboardSystem->registerTransport( PLUGIN_GUID_TRANSPORT_NONE, $pluginParams );


function transport_none_receive( $pMsg ){
}

/**
 * Does nothing
 *
 * @param array $pParamHash Array of message to be sent
 *
 * @param array $pParamHash['subject'] A string the message subject
 * @param array $pParamHash['message'] A string the message itself aka body
 * @param array $pParamHash['headers'] An Array of headers
 * @param array $pParamHash['recipients'] An Array of recipients, where each recipient is an array with key value pair of 'email' => emailaddress
 **/
function transport_none_send( &$pParamHash ){
	return TRUE;
}

function transport_none_expunge( $pMsg ){
}

