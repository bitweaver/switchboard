<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/plugins/email/transport.php,v 1.5 2009/09/08 14:10:56 wjames5 Exp $
 * @package switchboard
 * @subpackage plugins-email
 */

/**
 * Initialization
 */
global $gSwitchboardSystem;

define( 'PLUGIN_GUID_TRANSPORT_EMAIL', 'email' );

$pluginParams = array(
	'package' => 'switchboard',
	'transport_type' => PLUGIN_GUID_TRANSPORT_EMAIL,
	'title' => 'Email Transport Handler',
	'description' => 'This transport handler will process and send emails and email digests',
	'requirements' => 'To send email you must at least have php mail enabled. To receive emails and utilizing the list managemant capabilities of this handler you must have an MTA such as postfix installed.',
	'send_function' => 'transport_email_send',
	'receive_function' => 'transport_email_receive',
	'expunge_function' => 'transport_email_expunge',
	'use_queue' => FALSE,
);

$gSwitchboardSystem->registerTransport( PLUGIN_GUID_TRANSPORT_EMAIL, $pluginParams );


function transport_email_receive( $pMsg ){
	global $gBitUser, $gBitSystem;

	// prolly dont need this
	// $connectionString = '{'.$gBitSystem->getConfig('transport_email_server','imap').':'.$gBitSystem->getConfig('transport_email_port','993').'/'.$gBitSystem->getConfig('transport_email_protocol','imap').'/ssl/novalidate-cert}';

	// Parse msg - get header, body, attachments, to, from, reply header 
	if (include_once('PEAR.php')) {		
		if(require_once('Mail/mimeDecode.php')){
			$params['include_bodies'] = true;
			$params['decode_bodies']  = true;
			$params['decode_headers'] = true;

			$decoder = new Mail_mimeDecode($pMsg);
			if( $data = $decoder->decode($params)){
				if( $handler( $data ) ){
					transport_email_expunge( $pMsg );
				}
			}
			else{
				//error
			}
		}
		else{
			//error
		}
	}
	else{
		//error
	}
}

/**
 * Sends an email to the specified recipients.
 *
 * @param array $pParamHash Array of message to be sent
 *
 * @param array $pParamHash['subject'] A string the message subject
 * @param array $pParamHash['message'] A HTML string or array of strings, the message itself aka body
 * @param array $pParamHash['alt_message'] A plain text string, the message itself
 * @param array $pParamHash['headers'] An Array of headers
 * @param array $pParamHash['recipients'] An Array of recipients, where each recipient is an array with key value pair of 'email' => emailaddress
 **/
function transport_email_send( &$pParamHash ){
	// convenience
	$headers = !empty( $pParamHash['headers'] )?$pParamHash['headers']:array();
	$subject = $pParamHash['subject']; 
	$body = !empty( $pParamHash['message'] )? $pParamHash['message'] : NULL;
	$recipients = $pParamHash['recipients']; 

	// assemble the email
	$message = $headers;
	$message['subject'] = $subject;
	if( is_string( $body ) ){
		$message['message'] = $body;
	}elseif( is_array( $body ) ){
		$message = array_merge( $message, $body );
	}
	$message['alt_message'] = !empty( $pParamHash['alt_message'] )?$pParamHash['alt_message']:NULL;
	$mailer = transport_email_build_mailer($message);

	// Set these so the caller can know who the created mail(s) will appear to have come from
	$pParamHash['from'] = $mailer->From;
	$pParamHash['fromName'] = $mailer->FromName;

	// prep recipients
	if( is_string( $recipients ) ) {
		$recipients = array( array( 'email' => $recipients ) );
	}

	/* Send each message one by one. */
	foreach ($recipients as $to) {
		if( !empty($to['email'] ) ) {
			if (isset($to['real_name']) || isset($to['login'])) {
				$mailer->AddAddress( $to['email'], empty($to['real_name']) ? $to['login'] : $to['real_name'] );
			} else {
				$mailer->AddAddress( $to['email'] );
			}
			// send
			if( !$mailer->Send() ) {
				bit_log_error( $mailer->ErrorInfo );
			}
			$mailer->ClearAddresses();
		}
	}
	// return a tracking value
	return $mailer->MessageID;
}

/**
 * Returns a PHPMailer with everything set except the recipients
 *
 * $pMessage['subject'] - The subject
 * $pMessage['message'] - The HTML body of the message
 * $pMessage['alt_message'] - The Non HTML body of the message
 */
function transport_email_build_mailer($pMessage) {
	global $gBitSystem, $gBitLanguage;

	require_once( UTIL_PKG_PATH.'phpmailer/class.phpmailer.php' );

	$mailer = new PHPMailer();
	$mailer->From     = !empty( $pMessage['from'] ) ? $pMessage['from'] : $gBitSystem->getConfig( 'bitmailer_sender_email', $gBitSystem->getConfig( 'site_sender_email', $_SERVER['SERVER_ADMIN'] ) );
	$mailer->FromName = !empty( $pMessage['from_name'] ) ? $pMessage['from_name'] : $gBitSystem->getConfig( 'bitmailer_from', $gBitSystem->getConfig( 'site_title' ) );
	if( !empty( $pMessage['sender'] ) ) {
		$mailer->Sender = $pMessage['sender'];
	}
	
	$mailer->Host     = $gBitSystem->getConfig( 'bitmailer_servers', $gBitSystem->getConfig( 'kernel_server_name', '127.0.0.1' ) );
	$mailer->Mailer   = $gBitSystem->getConfig( 'bitmailer_protocol', 'smtp' ); // Alternative to IsSMTP()
	$mailer->CharSet  = 'UTF-8';

	if($gBitSystem->getConfig( 'bitmailer_ssl') == 'y'){
		$mailer->SMTPSecurity   = "ssl"; // secure transfer enabled
		$mailer->Port     = $gBitSystem->getConfig( 'bitmailer_port', '25' );
	}
	if( $gBitSystem->getConfig( 'bitmailer_smtp_username' ) ) {
		$mailer->SMTPAuth = TRUE;
		$mailer->Username = $gBitSystem->getConfig( 'bitmailer_smtp_username' );
	}
	if( $gBitSystem->getConfig( 'bitmailer_smtp_password' ) ) {
		$mailer->Password = $gBitSystem->getConfig( 'bitmailer_smtp_password' );
	}
	$mailer->WordWrap = $gBitSystem->getConfig( 'bitmailer_word_wrap', 75 );
	if( !$mailer->SetLanguage( $gBitLanguage->getLanguage(), UTIL_PKG_PATH.'phpmailer/language/' ) ) {
		$mailer->SetLanguage( 'en' );
	}

	if( !empty( $pMessage['x_headers'] ) && is_array( $pMessage['x_headers'] ) ) {
		foreach( $pMessage['x_headers'] as $name=>$value ) {
			/* Not sure what this is intended to do
			   but nothing seems to use it yet but boards
			   that I am hacking on now. 29-11-08
			   XOXO - Nick
			if( !$mailer->set( $name, $value ) ) {
				$mailer->$name = $value;
				bit_log_error( $mailer->ErrorInfo );
			}
			*/
			$mailer->AddCustomHeader($name.":".$value);
		}
	}

	$mailer->ClearReplyTos();
	$mailer->AddReplyTo( !empty( $pMessage['replyto'] )?$pMessage['replyto']:($gBitSystem->getConfig( 'bitmailer_replyto_email',  $gBitSystem->getConfig( 'bitmailer_sender_email' ) )) );
	if (empty($pMessage['subject'])) {
		$mailer->Subject = $gBitSystem->getConfig('site_title', '').
			(empty($pMessage['package']) ? '' : " : ".$pMessage['package']).
			(empty($pMessage['type']) ? '' : " : ".$pMessage['type']);
	}
	else {
		$mailer->Subject = $pMessage['subject'];
	}

	if (!empty($pMessage['message'])) {
		$mailer->Body    = $pMessage['message'];
		$mailer->IsHTML( TRUE );
		if (!empty($pMessage['alt_message'])) {
			$mailer->AltBody = $pMessage['alt_message'];
		}
		else {
			$mailer->AltBody = '';
		}
	}
	elseif (!empty($pMessage['alt_message'])) {
		// although plain text, use Body so that clients reading html by default see the msg. header is correctly set as text/plain
		$mailer->Body = $pMessage['alt_message'];
		$mailer->IsHTML( FALSE );
	}

	return $mailer;
}

// returns admin form params
function transport_email_admin(){
}

// deletes an email
function transport_email_expunge( $pMsg ){
}

// --- not sure these are needed - we'll see --- //
function transport_email_raw_headers($body) {
	$matches = preg_split('/^\s*$/ms', $body, 2);
	return $matches[0];
}

function transport_email_get_header($header, $body) {
	$ret = NULL;
	preg_match( '/^'.$header.':\s*(.*?)\s*$/m', $body, $matches);
	if (!empty($matches[1])) {
		$ret = $matches[1];
	}
	return $ret;
}

function transport_email_strip_addresses( $pString ){
	return ereg_replace(
                '[-!#$%&\`*+\\./0-9=?A-Z^_`a-z{|}~]+'.'@'.
                '(localhost|[-!$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.
                '[-!$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+)', '', $pString ); 
}

