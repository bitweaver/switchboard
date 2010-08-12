<?php
/**
 * @version $Header$
 * @package switchboard
 * @subpackage plugins-email
 */

/**
 * Initialization
 */
$formSwitchboardFeatures = array(
	"bitmailer_sender_email" => array(
		'label' => 'From Email',
		'note' => 'If empty, it will default to the site Sender Email',
		'default' => $gBitSystem->getConfig( 'site_sender_email', $_SERVER['SERVER_ADMIN'] ),
	),
	"bitmailer_from" => array(
		'label' => 'From Name',
		'note' => '',
		'default' => $gBitSystem->getConfig( 'siteTitle' ),
	),
	"bitmailer_replyto_email" => array(
		'label' => 'Reply To Email Address',
		'note' => 'This will be the reply to address',
	),
	"bitmailer_servers" => array(
		'label' => 'Mail Servers',
		'note' => '',
		'default' => $gBitSystem->getConfig( 'kernel_server_name', '127.0.0.1' ),
	),
	"bitmailer_smtp_username" => array(
		'label' => 'SMTP Username',
		'note' => 'Only required for authenticated outbound mail servers.',
		'default' => $gBitSystem->getConfig( 'bitmailer_smtp_username' ),
	),
	"bitmailer_smtp_password" => array(
		'label' => 'SMTP Password',
		'note' => 'Password for the above SMTP Username',
		'default' => $gBitSystem->getConfig( 'bitmailer_smtp_password' ),
	),
	"bitmailer_protocol" => array(
		'label' => 'Protocol',
		'note' => '',
		'default' => 'smtp',
	),
	"bitmailer_port" => array(
		'label' => 'Port',
		'note' => 'Enter the port number of the SMTP server.',
		'default' => '25',
	),
	"bitmailer_word_wrap" => array(
		'label' => 'Word wrap',
		'note' => '',
		'default' => '75',
	),
);
$formSwitchboardChecks = array(
	"bitmailer_ssl" => array(
		'label' => 'SSL',
		'note' => 'Connect to SMTP server using SSL.',
	),
);
$gBitSmarty->assign( 'formSwitchboardFeatures',$formSwitchboardFeatures );
$gBitSmarty->assign( 'formSwitchboardChecks',$formSwitchboardChecks );

if( !empty( $_POST ) ) {

	foreach( array_keys( $formSwitchboardFeatures ) as $key ) {
		if( empty( $_REQUEST[$key] ) || $_REQUEST[$key] != $gBitSystem->getConfig( $key ) ) {
			$gBitSystem->storeConfig( $key, isset( $_REQUEST[$key] ) ? $_REQUEST[$key] : NULL );
		}
	}
	foreach( $formSwitchboardChecks as $item => $data ) {
		simple_set_toggle( $item, SWITCHBOARD_PKG_NAME );
	}
	$gBitSystem->storeConfig( 'switchboard_default_transport', $_REQUEST['switchboard_default_transport'] );
}

?>
