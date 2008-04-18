<?php

/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/SwitchboardSystem.php,v 1.10 2008/04/18 16:05:44 wjames5 Exp $
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
 * @version  $Revision: 1.10 $
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
	 * $pOptions - Options for the listner. Currently supported options are:
				useQueue - If true the message is queued and the handler is called from tendQueue instead
	 *
	 */
    function registerSwitchboardListener( $pPackage, $pDeliveryStyle, $pFunction, $pOptions = NULL ) {
		if ( empty($this->mListener[$pDeliveryStyle]) ) {
			if (function_exists($pFunction)) {
				if (is_array($pOptions)) {
					$this->mListeners[$pDeliveryStyle] = $pOptions;
				}
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
	 * Registers a sender of events.
	 * $pTypes is the array of event types this package will send.
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
	 *				Currently supported are: message and subject
	 */
	function sendEvent($pPackage, $pEventType, $pContentId, $pDataHash, $pRecipients = NULL) {

		global $gBitSystem, $gBitUser;
		// Make sure event is registered so we can do prefs for them. This is for devs really
		if( !empty($this->mSenders[$pPackage]) && in_array($pEventType, $this->mSenders[$pPackage]['types']) ) {
			$event = $pDataHash;
			$event['package'] = $pPackage;
			$event['content_id'] = $pContentId;
			$event['event_type'] = $pEventType;

			// Load users preferences
			$usersPrefs = $this->loadEffectivePrefs($pRecipients, $pPackage, $pEventType, $pContentId);
			$messageId = NULL;
			// Check each delivery style
			foreach( $usersPrefs as $prefered_delivery => $users ) {
				// Make sure the style is registered.
				if( !empty($this->mListeners[$prefered_delivery]['function']) ) {
					// Does this delivery style get handled at cron time?
					if( isset($this->mListeners[$prefered_delivery]['useQueue']) ) {
						// Have we stored this message yet?
						if( $messageId == NULL ) {
							$messageId = $this->queueMessage($event);
						}
						$this->queueDelivery($messageId, $users, $prefered_delivery);
					} else {
						$func = $this->mListeners[$prefered_delivery]['function'];
						if( function_exists($func) ) {
							$func($event, $users);
						} else {
							$gBitSystem->fatalError("Package: ".$options['package']," registered a non-existant function listener: ".$func);
						}
					}
				} else {
					$gBitSystem->fatalError("Delivery Style: ".$prefered_delivery." for user: ". $prefs['login']." not registered!");
				}
			}
		} else {
			$gBitSystem->fatalError("Package: ".$pPackage." attempted to send message of type: ".$pEventType." but didn't register that it wanted to send this type.");
		}
	}

	/**
	 * Stores a message in the database and returns a message id.
	 */
	function queueMessage($event) {
		global $gBitSystem, $gBitUser;

		$messageStore['package'] = $event['package'];
		$messageStore['event_type'] = $event['event_type'];
		$messageStore['content_id'] = $event['content_id'];
		$messageStore['queue_date'] = $gBitSystem->getUTCDate();
		$messageStore['message_id'] = $this->mDb->GenID( 'switchboard_queue_id_seq' );
		$messageStore['message'] = $event['message'];
		$messageStore['sending_user_id'] = $gBitUser->mUserId;

		$this->mDb->associateInsert(BIT_DB_PREFIX."switchboard_queue",
									$messageStore);

		return $messageStore['message_id'];
	}

	/*
	 * Stores the delivery in the database
	 */
	function queueDelivery($pMessageId, $pUsers, $pDelivery) {
		$deliveryStore['message_id'] = $pMessageId;
		$deliveryStore['delivery_style'] = $pDelivery;
		$table = BIT_DB_PREFIX."switchboard_recipients";
		foreach($pUsers as $user_id) {
			$deliveryStore['user_id'] = $user_id;
			$this->mDb->associateInsert($table, $deliveryStore);
		}
	}

	/**
	 * Returns the queued messages for this user
	 */
	function listUserMessages($pUserId) {
		$query = "SELECT d.*, q.* FROM `".BIT_DB_PREFIX."switchboard_recipients` d LEFT JOIN `".BIT_DB_PREFIX."switchboard_queue` q ON (d.`message_id` = q.`message_id`) WHERE d.`user_id` = ?";
		$messages = $this->mDb->getArray($query, $pUserId);
	}

	/**
	 * Returns the user_ids of pending deliveries as an associated array
	 * These come out associated first by message id and then delivery style
	 */
	function listPendingDeliveries() {
		$query = "SELECT q.*  FROM `".BIT_DB_PREFIX."switchboard_recipients` WHERE `complete_date` IS NULL";
		$result = $this->mDb->query($query, $pUserId);
		$ret = array();
		while($res = $result->fetchRow()) {
			$ret[$res['message_id']][$res['delivery_style']][] = $res['user_id'];
		}

		return $ret;
	}

	/**
	 * Returns an array of messages with the given message IDs.
	 */
	function listMessages($pMessageIds) {
		$query = "SELECT q.`message_id` AS hash_key, q.* FROM `".BIT_DB_PREFIX."switchboard_queue` WHERE q.`message_id` IN (". implode( ',',array_fill( 0,count( $pMessageIds ),'?' ) ). ") ";
		$messages = $this->mDb->getAssoc($query, $pMessageIds);

		return $messages;
	}

	/**
	 * Loads the users default and content override preferences.
	 * Returns an associative array with delivery_style as the key.
	 */
	function loadEffectivePrefs( $pRecipients, $pPackage, $pEventType, $pContentId = NULL) {
		$defaults = $this->loadPrefs($pRecipients, $pPackage, $pEventType);
		$overrides = $this->loadContentPrefs($pRecipients, $pContentId);

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
			$ret[$data['delivery_style']][$data['user_id']] = $data;
		}

		return $ret;
	}

	/**
	 * Loads the users preferences for a given content object.
	 */
	function loadContentPrefs( $pRecipients = NULL, $pContentId = NULL ) {
		$selectSql = "SELECT sp.*, uu.`email`, uu.`login`, uu.`real_name`, lc.`title`, lc.`content_type_guid`  ";
		$fromSql = "FROM `".BIT_DB_PREFIX."switchboard_prefs` sp ";
		$joinSql = "LEFT JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (sp.`content_id` = lc.`content_id`) ";
		$joinSql .= "LEFT JOIN `".BIT_DB_PREFIX."users_users` uu ON (sp.`user_id` = uu.`user_id`) ";
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
	 * Loads the preferences for the given recipients, pacakge and event
	 * If recipients is null then all users with registered preferences
	 * are loaded.
	 */
	function loadPrefs( $pRecipients = NULL, $pPackage = NULL, $pEventType = NULL ) {
		$selectSql = "SELECT sp.*, uu.`email`, uu.`login`, uu.`real_name` ";
		$fromSql = "FROM `".BIT_DB_PREFIX."switchboard_prefs` sp ";
		$joinSql = "LEFT JOIN `".BIT_DB_PREFIX."users_users` uu ON (sp.`user_id` = uu.`user_id`) ";
		$whereSql = 'WHERE sp.`content_id` IS NULL ';
		$bindVars = array();
		if (!empty($pPackage)) {
			$whereSql .= "AND sp.`package` = ? ";
			$bindVars[] = $pPackage;
		}
		if (!empty($pEventType)) {
			$whereSql .= "AND sp.`event_type` = ? ";
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

	/**
	 * Deletes a preference for the given user, either a default
	 * or content permission.
	 */
	function deleteUserPref($pUserId, $pPackage, $pEventType, $pContentId = NULL) {
		$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_prefs` WHERE `package` =? AND `event_type` = ? AND `user_id` = ? AND `content_id` ".(empty($pContentId) ? " IS NULL " : " = ?" );
		$bindVars = array( $pPackage, $pEventType, $pUserId );
		if( !empty($pContentId) ) {
			$bindVars[] = $pContentId;
		}
		$this->mDb->query($query, $bindVars);
	}

	/**
	 * Stores a preference for the user.
	 */
	function storeUserPref($pUserId, $pPackage, $pEventType, $pContentId = NULL, $pDeliveryStyle = NULL) {
		if ($this->senderIsRegistered($pPackage, $pEventType)) {
			$this->mDb->StartTrans();

			$this->deleteUserPref($pUserId, $pPackage, $pEventType, $pContentId);

			$query = "INSERT INTO `".BIT_DB_PREFIX."switchboard_prefs` (`package`, `event_type`, `user_id`, `content_id`, `delivery_style`) VALUES (?, ?, ?, ?, ?)";
			$bindVars = array( $pPackage, $pEventType, $pUserId, $pContentId, $pDeliveryStyle );

			$this->mDb->query($query, $bindVars);

			$this->mDb->CompleteTrans();
		}
		else {
			global $gBitSystem;
			$gBitSystem->fatalError("Attempt to register a perference for a package that is not registered.");
		}
	}

	/**
	 * Checks if a package is registered as a sender.
	 * $pSender - The package to check.
	 */
	function senderIsRegistered($pSender, $pType = NULL) {
		if (!empty($pType)) {
			return !empty($this->mSenders[$pSender]) && in_array($pType, $this->mSenders[$pSender]['types']);
		}
		else {
			return !empty($this->mSenders[$pSender]);
		}
	}

	/**
	 * Sends an email to the specified recipients.
	 * This is a convenience method for packages
	 * to be able to use the email sending features
	 * found in this package.
	 *
	 * $pSubject - The Subject of the Email
	 * $pBody - The Body of the Email
	 * $pRecipients - An associative array with keys for email and optionally login and real_name
	 **/
	function sendEmail($pSubject, $pBody, $pRecipients, $pHeaders=array() ){
		global $gBitSystem;
		$message = $pHeaders;
		$message['subject'] = $pSubject;
		$message['message'] = $pBody;
		$mailer = $this->buildMailer($message);

		if( is_string( $pRecipients ) ) {
			$pRecipients = array( array( 'email' => $pRecipients ) );
		}

		foreach ($pRecipients as $to) {
			if( !empty($to['email'] ) ) {
				if (isset($to['real_name']) || isset($to['login'])) {
					$mailer->AddAddress( $to['email'], empty($to['real_name']) ? $to['login'] : $to['real_name'] );
				} else {
					$mailer->AddAddress( $to['email'] );
				}
				if( !$mailer->Send() ) {
					$gBitSystem->fatalError("Unable to send email: " . $mailer->ErrorInfo);
				}
				$mailer->ClearAddresses();
			}
		}
	}

	/**
	 * Returns a PHPMailer with everything set except the recipients
	 *
	 * $pMessage['subject'] - The subject
	 * $pMessage['message'] - The HTML body of the message
	 * $pMessage['alt_message'] - The Non HTML body of the message
	 */
	function buildMailer($pMessage) {
		global $gBitSystem, $gBitLanguage;

		require_once( UTIL_PKG_PATH.'phpmailer/class.phpmailer.php' );

		$mailer = new PHPMailer();
		$mailer->From     = !empty( $pMessage['from'] ) ? $pMessage['from'] : $gBitSystem->getConfig( 'switchboard_sender_email', $gBitSystem->getConfig( 'site_sender_email', $_SERVER['SERVER_ADMIN'] ) );
		$mailer->FromName = !empty( $pMessage['from_name'] ) ? $pMessage['from_name'] : $gBitSystem->getConfig( 'switchboard_from', $gBitSystem->getConfig( 'site_title' ) );
		if( !empty( $pMessage['sender'] ) ) {
			$mailer->Sender = $pMessage['sender'];
		}
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
		elseif (!empty($pmessage['alt_message'])) {
			$mailer->Body = $pMessage['alt_message'];
			$mailer->IsHTML( FALSE );
		}

		return $mailer;
	}

	function tendQueue() {
		// Get the list of pending deliveries
		$msg_to_deliver = $this->listPendingDeliveries();
		// If we have any
		if (count($msg_to_deliver)) {
			// Fetch the data about the messages
			$messages = $this->listMessages(array_keys($msg_to_deliver));
			// And figure out how to deliver them
			foreach($msg_to_deliver as $message_id => $deliveries) {
				foreach($delivereis as $delivery_style => $users) {
					$func = $this->mListeners[$delivery_style]['function'];
					if( function_exists($func) ) {
						$func($event, $users);
					} else {
						bit_log_error("Package: ".$options['package']," registered a non-existant function listener: ".$func);
					}
				}
			}
		}
	}
}

/** Private! Sends a digest message */
function switchboard_send_digest($pSwitchboardEvent, $pRecipients) {
	// For each recipient (we ignore the event triggering the digest)
	foreach ($pRecipients as $recipient) {
		$user = new BitUser($recipient);
		$user->load();

		// Has it been long enough for a digest for this user?
		$last_digest = $user->getPreference('switchboard_last_digest');
		if (empty($last_digest)) {
			$user->storePreference('switchboard_last_digest', $gBitSystem->getUTCTime());
		}
		// @TODO: Need to make admin for digest_period
		else if ($gBitSystem->getUTCTime() >= $last_digest + $user->getPreference('switchboard_digest_period', 24 * 60 * 60)) {
			// Get all the messages pending for this user
			$messages = $gSwitchboardSystem->listUserMessages($recipient);

			// This shouldn't be empty because of $pSwitchboardEvent but...
			if (!empty($messages)) {
				$deleteVars = array();
				$message = '';

				// Build up the digest
				foreach ($messages as $message) {
					$deleteVars[] = $message['message_id'];
					$message = $message['message'];
					$message = "<br/><hr><br/>";
				}

				// Send the message
				// @TODO: Make a better title.
				$mailer = $gSwitchboardSystem->buildMailer(array('subject' => 'Digest From: '.$gBitSystem->getConfig('site_title'),
																 'message' => $message));
				$mailer->AddAddress( $user->mInfo['email'], $user->mInfo['login'] );
				if( !$mailer->Send() ) {
					$gBitSystem->fatalError("Unable to send notification: " . $mailer->ErrorInfo);
				}

				// Delete the deliveries from the queue
				$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_recipients` WHERE `message_id` IN (".(implode(",", array_fill(0,count($deleteVars), "?"))).") AND `user_id` = ?";
				$deleteVars[] = $recipient;
				$this->mDb->query($query, $deleteVars);

				// @TODO: Check and update the complete_date

				// And remember the users digest last time.
				$user->storePreference('switchboard_last_digest', $gBitSystem->getUTCTime());
			}
		}
	}
}

/** Private! Sends an email message */
function switchboard_send_email($pMessage, $pRecipients) {
	global $gSwitchboardSystem, $gBitSystem;

	$mailer = $gSwitchboardSystem->buildMailer($pMessage);
	/* Send each message one by one. */
	foreach ($pRecipients as $to) {
		if( !empty($to['email']) ) {
			$mailer->AddAddress( $to['email'], empty($to['real_name']) ? $to['login'] : $to['real_name'] );
			if( !$mailer->Send() ) {
				bit_log_error( "Switchboard unable to send notification: " . $mailer->ErrorInfo );
			}
			$mailer->ClearAddresses();
		}
	}
}

function switchboard_content_expunge(&$pObject, $pHash) {
	if( $pObject->mContentTypeGuid == BITUSER_CONTENT_TYPE_GUID ) {
		$bindVars = array($pObject->mUserId);
		$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_prefs` WHERE `user_id` = ?";
		$this->mDb->query($query, $bindVars);
		$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_recipients` WHERE `user_id` = ?";
		$this->mDb->query($query, $bindVars);
		$query = "SELECT `message_id` FROM `.".BIT_DB_PREFIX."switchboard_queue` WHERE `user_id` = ?";
		$messageIds = $this->mDb->getArray($query, $bindVars);
		if (count($messageIds)) {
			$in = implode(',', array_fill(0, count($messageIds), '?'));
			$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_recipients` WHERE `message_id` IN (".$in.")";
			$this->mDb->query($query, $messageIds);
			$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_queue` WHERE `message_id` IN (".$in.")";
			$this->mDb->query($query, $messageIds);
		}
	}
	else {
		$bindVars = array($pObject->mContentId);
		$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_prefs` WHERE `content_id` = ?";
		$pObject->mDb->query($query, $bindVars);

		$query = "SELECT `message_id` FROM `".BIT_DB_PREFIX."switchboard_queue` WHERE `content_id` = ?";
		$messageIds = $pObject->mDb->getArray($query, $bindVars);
		if (count($messageIds)) {
			$in = implode(',', array_fill(0, count($messageIds), '?'));
			$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_recipients` WHERE `message_id` IN (".$in.")";
			$this->mDb->query($query, $messageIds);
			$query = "DELETE FROM `".BIT_DB_PREFIX."switchboard_queue` WHERE `message_id` IN (".$in.")";
			$this->mDb->query($query, $messageIds);
		}
	}
}

// Initialize the switchboard system global if we haven't already
if ( empty( $gSwitchboardSystem ) ) {
	$gSwitchboardSystem = new SwitchboardSystem();
	$gSwitchboardSystem->registerSwitchboardListener('switchboard', 'email', 'switchboard_send_email');
	$gSwitchboardSystem->registerSwitchboardListener('switchboard', 'digest', 'switchboard_send_digest', array('useQueue' => true));

	// Store it in the context.
	$gBitSmarty->assign_by_ref('gSwitchboardSystem', $gSwitchboardSystem);
}

?>
