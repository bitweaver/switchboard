<?php

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
	"bitmailer_word_wrap" => array(
		'label' => 'Word wrap',
		'note' => '',
		'default' => '75',
	),
);
$gBitSmarty->assign( 'formSwitchboardFeatures',$formSwitchboardFeatures );

if( !empty( $_POST ) ) {

	foreach( array_keys( $formSwitchboardFeatures ) as $key ) {
		if( empty( $_REQUEST[$key] ) || $_REQUEST[$key] != $formSwitchboardFeatures[$key]['default'] ) {
			$gBitSystem->storeConfig( $key, isset( $_REQUEST[$key] ) ? $_REQUEST[$key] : NULL );
		}
	}
}

?>
