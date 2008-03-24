<?php

/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/SwitchboardSystem.php,v 1.3 2008/03/24 11:59:40 nickpalmer Exp $
 *
 * +----------------------------------------------------------------------+
 * | Copyright ( c ) 2008, bitweaver.org
 * +----------------------------------------------------------------------+
 * | All Rights Reserved. See copyright.txt for details and a complete
 * | list of authors.
 * | Licensed under the GNU LESSER GENERAL PUBLIC LICENSE.
 * | See license.txt for details
 * |
 * | For comments, please use phpdocu.sourceforge.net standards!!!
 * | -> see http://phpdocu.sourceforge.net/
 * +----------------------------------------------------------------------+
 * | Authors: nick <nick@sluggardy.net>
 * +----------------------------------------------------------------------+
 *
 * SwitchboardSystem class
 *
 * This class represents an abstract switchboard system which packages
 * can use to register things for switchboard and
 *
 * @author   nick <nick@sluggardy.net>
 * @version  $Revision: 1.3 $
 * @package  switchboard
 */

global $gSwitchboardSystem;

require_once( LIBERTY_PKG_PATH . 'LibertyBase.php' );

class SwitchboardSystem extends LibertyBase {

	/**
	 * The packages registered to listen for events
	 */
	var $mListeners;

	/**
	 * The packages registered to send events
	 */
	var $mSenders;


	/**
	 * Constructs a SwitchboardSystem. This shouldn't really be called.
	 * Use the $gSwitchboardSystem instance instead which is created
	 * for you if you include this file.
	 */
	function SwitchboardSystem() {
		// Not much to do here
		$this->mListeners = array();
		$this->mSenders = array();
		LibertyBase::LibertyBase();
	}

    /**
	 * Register the callback function for a given package.
	 *
	 * The function should have the following API:
	 * handleSwitchboardNotification($pSwitchboardEvent, $pRecipients) Where
	 * $pSwitchboardEvent is the array with data on the event and $pRecipients
	 * is the array of users to deliver the event to.
	 *
	 * $pDeliveryStyles - The delivery style being registered
	 * $pFunction - The function that will get the callback
	 *
	 */
    function registerSwitchboardListener( $pPackage, $pDeliveryStyle, $pFunction ) {
		if ( empty($this->mListener[$pDeliveryStyle]) ) {
			if (function_exists($pFunction)) {
				$this->mListeners[$pDeliveryStyle]['function'] = $pFunction;
				$this->mListeners[$pDeliveryStyle]['package'] = $pPackage;
			}
			else {
				$gBitSystem->fatalError("Package: ".$pPackage," registered a non-existant function listener: ".$func);
			}
		}
		else {
			$gBitSystem->fatalError("Switchboard Error: ".$pPackage." attempt to register an already registered delivery style: ".$pDelivertyStyle.". Already registered by: ".$this->mListener[$pDeliveryStyle]['package']);
		}
	}

    /**
	 * Register the callback function for a given package.
	 *
	 * The function should have the following API:
	 * handleSwitchboardNotification($pSwitchboardEvent) Where
	 * $pSwitchboardEvent is the array with data on the event
	 *
	 * $pTypes is the event types this package will send.
	 *
	 */
    function registerSwitchboardSender( $pPackage, $pTypes ) {
		$this->mSenders[$pPackage]['types'] = $pTypes;
	}

	/**
	 * Send an event to all event listeners
	 *
	 * $pPackage - The Package sending the message.
	 * $pEventType - The type of the event.
	 * $pRecipients - An array of userIds of recipients. If null all with registered preference will be sent a message
	 * $pContentId - The content_id of the object that triggered this message.
	 * $pDataHash - The message that is being sent.
	 *				Currently supported are: body and subject
	 */
	function sendEvent($pPackage, $pEventType, $pContentId, $pDataHash, $pRecipients = NULL) {

		global $gBitSystem, $gBitUser;

		// Only registered users get messages routed.
		if( !$gBitUser->isRegistered() ) {
			return;
		}

		// Make sure event is registered so we can do prefs for them. This is for devs really
		if( !empty($this->mSenders[$pPackage]) && !empty($this->mSenders[$pPackage]['types'][$pEventType]) ) {
			$event = $pDataHash;
			$event['package'] = $pPackage;
			$event['content_id'] = $pContentId;
			$event['event_type'] = $pEventType;

			// Load users preferences
			$usersPrefs = $this->loadEffectivePrefs($pRecipients, $pPackage, $pEventType, $pContentId);

			// Send the message for each recipient
			foreach( $usersPrefs as $prefered_delivery => $users ) {
				if( !empty($this->mListeners[$prefered_delivery]['function']) ) {
					$func = $this->mListeners[$prefered_delivery]['function'];
					if( function_exists($func) ) {
						$func($event);
					} else {
						$gBitSystem->fatalError("Package: ".$options['package']," registered a non-existant function listener: ".$func);
					}
				} else {
					$gBitSystem->fatalError("Delivery Style: ".$prefered_delivery." for user: ". $prefs['login']." not registered!");
				}
			}
		} else {
			$gBitSystem->fatalError("Package: ".$pPackage." attempted to send message of type: ".$pEventType." but didn't register that it wanted to send this type.");
		}
	}

	function loadEffectivePrefs( $pRecipients, $pPackage, $pEventType, $pContentId = NULL) {
		$defaults = $this->loadPrefs($pRecipients, $pPackage, $pEventType);
		$overrides = $this->loadPrefs($pRecpients, $pContentId);

		// Figure out each users effect prefs
		$prefs = array();
		foreach ($defaults as $data) {
			$prefs[$data['user_id']] = $data;
		}
		foreach ($overrides as $data) {
			$prefs[$data['user_id']] = $data;
		}

		// Now reorder by delivery style
		$ret = array();
		foreach ($prefs as $user_id => $data) {
			$ret[$data['delivery_style']] = $data;
		}

		return $ret;
	}

	function loadContentPrefs( $pRecipients = NULL, $pContentId = NULL ) {
		$selectSql = "SELECT sp.*, uu.`email`, uu.`login`, uu.`real_name`, lc.`title`, lc.`content_type_guid`  ";
		$fromSql = "FROM `".BIT_DB_PREFIX."switchboard_prefs` sp ";
		$joinSql = "LEFT JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (sp.`content_id` = lc.`content_id`) ";
		$joinSql .= "LEFT JOIN `".BIT_DB_PREFIX."users_users` lc ON (sp.`user_id` = uu.`user_id`) ";
		$whereSql = "WHERE sp.`content_id` ";
		$bindVars = array();

		if( !empty($pContentId) ) {
			$whereSql .= '= ? ';
			$bindVars[] = $pContentId;
		} else {
			$whereSql .= 'IS NOT NULL ';
		}

		if( !empty($pRecipients) ) {
			// Make it into an array for simplicity
			if ( !is_array($pRecipients) ) {
				$pRecipients = array($pRecipients);
			}
			$whereSql .= "AND sp.`user_id` IN (". implode( ',',array_fill( 0,count( $pRecipients ),'?' ) ). ") ";
			$bindVars = array_merge($bindVars, $pRecipients);
		}

		$prefs = $this->mDb->getArray($selectSql.$fromSql.$joinSql.$whereSql, $bindVars);

		return $prefs;
	}

	/**
	 * Loads the recipients with interest registered for a given message
	 */
	function loadPrefs( $pRecipients = NULL, $pPackage = NULL, $pEventType = NULL ) {
		$selectSql = "SELECT sp.*, uu.`email`, uu.`login`, uu.`real_name` ";
		$fromSql = "FROM `".BIT_DB_PREFIX."switchboard_prefs` sp ";
		$joinSql = "LEFT JOIN `".BIT_DB_PREFIX."users_users` lc ON (sp.`user_id` = uu.`user_id`) ";
		$whereSql = 'WHERE sp.`content_id` IS NULL ';
		$bindVars = array();
		if (!empty($pPackage)) {
			$whereSql .= "AND `package` = ? ";
			$bindVars[] = $pPackage;
		}
		if (!empty($pEventType)) {
			$whereSql = "AND `event_type` = ? ";
			$bindVars[] = $pEventType;
		}

		if( !empty($pRecipients) ) {
			// Make it into an array for simplicity
			if ( !is_array($pRecipients) ) {
				$pRecipients = array($pRecipients);
			}
			$whereSql .= "AND sp.`user_id` IN (". implode( ',',array_fill( 0,count( $pRecipients ),'?' ) ). ") ";
			$bindVars = array_merge($bindVars, $pRecipients);
		}

		$prefs = $this->mDb->getArray($selectSql.$fromSql.$joinSql.$whereSql, $bindVars);

		return $prefs;
	}

	function deleteUserPref($pUserId, $pPackage, $pEventType, $pContentId = NULL) {
		$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_prefs` WHERE `package` =? AND `event_type` = ? AND `user_id` = ? AND `content_id` ".(empty($pContentId) ? " IS NULL " : " = ?" );
		$bindVars = array( $pPackage, $pEventType, $pUserId );
		if( !empty($pContentId) ) {
			$bindVars[] = $pContentId;
		}
		$this->mDb->query($query, $bindVars);
	}

	function storeUserPref($pUserId, $pPackage, $pEventType, $pContentId = NULL, $pDeliveryStyle = NULL) {
		$this->mDb->StartTrans();

		$this->deleteUserPref($pUserId, $pPackage, $pEventType, $pContentId);

		$query = "INSERT INTO `".BIT_DB_PREFIX."switchboard_prefs` (`package`, `event_type`, `user_id`, `content_id`, `delivery_style`) VALUES (?, ?, ?, ?, ?)";
		$bindVars = array( $pPackage, $pEventType, $pUserId, $pContentId, $pDeliveryStyle );

		$this->mDb->query($query, $bindVars);

		$this->mDb->CompleteTrans();
	}

	function senderIsRegistered($pSender) {
		return !empty($this->mSenders[$pSender]);
	}

}


function switchboard_send_digest($pSwitchboardEvent, $pRecipients) {
	vd("CALLED SEND EMAIL");
	// TODO: Add the event to long term storage for processing later.
}

function switchboard_build_digest() {
	// TODO: Build a digest message for each user with something in the queue
	// TODO: Write a cron job that sets up and then calls this function.
	// Send the message
	switchboard_send_email($message, $recipients);
}

function switchboard_send_email($pMessage, $pRecipients) {
	require_once( UTIL_PKG_PATH.'phpmailer/class.phpmailer.php' );

	$mailer = new PHPMailer();
	$mailer->From     = $gBitSystem->getConfig( 'bitmailer_sender_email', $gBitSystem->getConfig( 'site_sender_email', $_SERVER['SERVER_ADMIN'] ) );
	$mailer->FromName = $gBitSystem->getConfig( 'bitmailer_from', $gBitSystem->getConfig( 'site_title' ) );
	$mailer->Host     = $gBitSystem->getConfig( 'bitmailer_servers', $gBitSystem->getConfig( 'kernel_server_name', '127.0.0.1' ) );
	$mailer->Mailer   = $gBitSystem->getConfig( 'bitmailer_protocol', 'smtp' ); // Alternative to IsSMTP()
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
	$mailer->ClearReplyTos();
	$mailer->AddReplyTo( $gBitSystem->getConfig( 'bitmailer_from' ) );
	$mailer->Body    = $pMessage['message'];
	$mailer->Subject = empty($pMessage['subject']) ? $gBitSystem->getConfig('site_title', '')." : ".$pMessage['package']." : ".$pMessage['type'] : $pMessage['subject'];
	$mailer->IsHTML( TRUE );
	$mailer->AltBody = '';

	foreach ($pRecipients as $to) {
		$mailer->AddAddress( $to['email'], empty($to["real_name"]) ? $to["login"] : $to['real_name']);
	}

	if( !$mailer->Send() ) {
		$gBitSystem->fatalError("Unable to send notification: " . $mailer->ErrorInfo);
	}
}

function switchboard_content_expunge(&$pObject, $pHash) {
	if( $pObject->mContentTypeGuid == BITUSER_CONTENT_TYPE_GUID ) {
		$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_prefs` WHERE `user_id` = ?";
		$this->mDb->query($query, array($pObject->mUserId));
	}
	else {
		$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_prefs` WHERE `content_id` = ?";
		$this->mDb->query($query, array($pObject->mContentId));
	}
}

// Initialize the switchboard system global if we haven't already
if ( empty( $gSwitchboardSystem ) ) {
	$gSwitchboardSystem = new SwitchboardSystem();
	$gSwitchboardSystem->registerSwitchboardListener('switchboard', 'email', 'switchboard_send_email');
	$gSwitchboardSystem->registerSwitchboardListener('switchboard', 'digest', 'switchboard_send_digest');

	// Store it in the context.
	$gBitSmarty->assign_by_ref('gSwitchboardSystem', $gSwitchboardSystem);
}

?>
