<?php

/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/SwitchboardSystem.php,v 1.1.1.2 2008/12/24 16:47:19 wjames5 Exp $
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
 * @version  $Revision: 1.1.1.2 $
 * @package  switchboard
 */

/**
 * Initialization
 */
global $gSwitchboardSystem;
require_once( KERNEL_PKG_PATH . 'BitMailer.php' );

/**
 * SwitchboardSystem 
 * 
 * @package switchboard
 */
class SwitchboardSystem extends BitMailer {

	/**
	 * Active transport plugins
	 */
	var $mTransports;

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
		$this->mTransports = array();
		$this->mSenders = array();
		LibertyBase::LibertyBase();
	}

    /**
	 * Registers a sender of events.
	 * $pTypes is the array of event types this package will send.
	 *
	 */
    function registerSender( $pPackage, $pTypes ) {
		$this->mSenders[$pPackage]['types'] = $pTypes;
	}

	/** 
	 * Registers a transport plugin
	 * 
	 * Populates the list of available transport types
	 * Example access looks like $this->mTransports['email']['send_function']
	 **/
	function registerTransport( $pGuid, $pParamHash ){
		if ( empty($this->mTransports[$pGuid]) ) {
			$this->mTransports[$pGuid] = array_merge( $pParmaHash );
		}
		else {
			$gBitSystem->fatalError("Switchboard Error: ".$pParamHash['package']." attempt to register an already registered transport handler: ".$pGuid.". Already registered by: ".$this->mTransports[$pGuid]['package']);
		}
	}

	/**
	 * Load active transport plugins
	 **/
	function loadPlugins(){
		// @TODO get configs from system
		$configs = array( "email" );

		foreach( $configs as $config ) {
			// @TODO get path from system - the todo is to store the path
            // if( $pluginFile = $gBitSystem->getConfig( "switchbaord_plugin_path_$config" ) ) {
			if( $pluginFile = 'switchboard/plugins/transport.email.php'){
                if( is_file( BIT_ROOT_PATH.$pluginFile )) {
                    include_once( BIT_ROOT_PATH.$pluginFile );
                }
			}
		}
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
			$msgHash = $pDataHash;
			$msgHash['package'] = $pPackage;
			$msgHash['content_id'] = $pContentId;
			$msgHash['event_type'] = $pEventType;

			// Load users preferences
			$usersPrefs = $this->loadEffectivePrefs($pRecipients, $pPackage, $pEventType, $pContentId);
			// Check each delivery style
  			foreach( $usersPrefs as $prefered_delivery => $users ) {
				$msgHash['users'] = $users;
				$msgHash['transport_type'] = $prefered_delivery;
				$msgHash['use_queue'] = !empty( $this->mTransports[$transport_type]['use_queue'] )?TRUE:FALSE;
				// send the message using the prefered delivery style
				$this->sendMsg( $msgHash );
			}
		} else {
			$gBitSystem->fatalError("Package: ".$pPackage." attempted to send message of type: ".$pEventType." but didn't register that it wanted to send this type.");
		}
	}

	/**
	 * Send a message using a particular transport type 
	 * 
	 * @param array $pParamHash Array of message to be sent
	 *
	 * @param array $pParamHash['transport_type'] required, the method of delivery, e.g. email, sms, im, etc
	 * @param array $pParamHash['recipients'] an array of arrays containing the transport address for each recipient - optional if users is set
	 * @param array $pParamHash['users'] an array of arrays of users - required for queue or if recipients is empty
	 * @param array $pParamHash['use_queue'] boolean to use the message queue - only valid for spooling to registered users for messages related to a content object
	 * @param array $pParamHash['content_id'] - optional, required only for message queue
	 * 
	 * params passed along to send handler or message queue
	 * @param array $pParamHash['event_type'] optional, required only for message queue
	 * @param array $pParamHash['package'] optional, required only for message queue
	 **/
	function sendMsg( $pParamHash ){
		global $gBitSystem;

		if( !empty( $pParamHash['transport_type'] ) ){
			if( empty( $pParamHash['recipients'] ) && !empty( $pParamHash['users'] ) ){
				$pParamHash['recipients'] = $pParamHash['users'];
			}
			// convenience
			$transport_type = $pParamHash['transport_type'];
			$recipients = $pParamHash['recipients'];
			$users = $pParamHash['users'];

			// queue message reference
			$messageId = NULL;

			// make sure the transport type is registered.
			if( !empty($this->mTransports[$transport_type]['send_function']) ) {
 				// Does this transport type get handled at cron time?
				/**
				 * NOTE: use_queue is only valid for messages going to registered users! 
				 * this will fail if you try to queue non-registered users
				 * this should only be set in sendEvent
				 **/
				if( $pParamHash['use_queue'] && !empty( $pParamHash['content_id'] ) && !empty( $users ) ) {
					// Have we stored this message yet?
					if( $messageId == NULL ) {
						$messageId = $this->queueMessage($pParamHash);
					}
					$this->queueDelivery($messageId, $users, $transport_type);
				} 
				// send immediately
				else {
					$func = $this->mTransports[$transport_type]['send_function'];
					if( function_exists($func) ) {
						$func($pParamHash);
					} else {
						// @TODO fataling here is kinda nasty since this function might be called a few times for multiple transport types from sendEvent
						$gBitSystem->fatalError("Package: ".$this->mTransports[$transport_type]['package']." registered a non-existant send handler: ".$func);
					}
				}
			} 
			// handler error
			else {
				// display the list of recipients who are not getting the message
				$recipient_list = '';
				foreach ($recipients as $recipient) {
					// if we have users then we'll display their login name, otherwise display the address we were trying to send to
					$recipient_list .= ( !empty( $users ) ? $recipient['login'] : $recipient[$transport_type] ) . " ";
				}
				// @TODO fataling here is kinda nasty since this function might be called a few times for different transport types from sendEvent
				$gBitSystem->fatalError("Delivery Style: ".$transport_type." for users: ". $user_list." not registered!");
			}
		}
	}	

	// convenience function
	function sendEmail( $pParamHash ){
		$pParamHash['transport_type'] = 'email';
		$this->sendMsg( $pParmaHash );
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
					$func = $this->mTransports[$delivery_style]['send_function'];
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

?>
