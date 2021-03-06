<?php
/**
 * @version $Header$
 *
 * +----------------------------------------------------------------------+
 * | Copyright ( c ) 2008, bitweaver.org
 * +----------------------------------------------------------------------+
 * | All Rights Reserved. See below for details and a complete
 * | list of authors.
 * | Licensed under the GNU LESSER GENERAL PUBLIC LICENSE.
 * | See http://www.gnu.org/copyleft/lesser.html for details
 * |
 * | For comments, please use phpdocu.sourceforge.net standards!!!
 * | -> see http://phpdocu.sourceforge.net/
 * +----------------------------------------------------------------------+
 * | Authors: nick <nick@sluggardy.net>, will <will@tekimaki.com>
 * +----------------------------------------------------------------------+
 *
 * SwitchboardSystem class
 *
 * This class represents an abstract switchboard system which packages
 * can use to register things for switchboard and
 *
 * @author   nick <nick@sluggardy.net>, will <will@tekimaki.com>
 * @version  $Revision$
 * @package  switchboard
 */

/**
 * Initialization
 */
global $gSwitchboardSystem;
require_once( KERNEL_PKG_PATH . 'BitBase.php' );

/**
 * SwitchboardSystem 
 * 
 * @package switchboard
 */
class SwitchboardSystem extends BitBase {

	/**
	 * Active transport plugins
	 */
	var $mTransports = array();

	/**
	 * The packages registered to send events
	 */
	var $mSenders = array();


    /**
	 * Registers a sender of events.
	 * @param string $pPackage 	- required package registering the sender
	 * @param string $pType 	- required sender type the package will send.
	 * @param array $pParamHash - optional array of options for the sender
	 *
	 * @param boolean $pParamHash['include_owner'] - optional flag to load sender preferences for owners of a given content when a content_id is given for an event, see loadEffectivePreferences 
	 */
    function registerSender( $pPackage, $pType, $pParamHash = array() ) {
		$this->mSenders[$pPackage]['types'][$pType] = $pParamHash;
	}

	/** 
	 * Registers a transport plugin
	 * 
	 * Populates the list of available transport types
	 * Example access looks like $this->mTransports['email']['send_function']
	 **/
	function registerTransport( $pGuid, $pParamHash ){
		if ( empty($this->mTransports[$pGuid]) ) {
			$this->mTransports[$pGuid] = array_merge( $pParamHash );
		}
		else {
			$gBitSystem->fatalError("Switchboard Error: ".$pParamHash['package']." attempt to register an already registered transport handler: ".$pGuid.". Already registered by: ".$this->mTransports[$pGuid]['package']);
		}
	}

	/**
	 * Load active transport plugins
	 **/
	function loadPlugins(){
		global $gBitSystem;
		$pluginLoc = $gBitSystem->getConfig( "switchboard_plugin_path", SWITCHBOARD_PKG_PATH.'plugins' );
		if( $plugins = scandir( $pluginLoc ) ) {
			foreach( $plugins as $pluginDirName ) {
				$pluginFile = $pluginLoc.'/'.$pluginDirName.'/transport.php';
				if( file_exists( $pluginFile ) ) {
					include_once( $pluginFile );
				}
			}
		}
	}

	function getDefaultTransport() {
		global $gBitSystem;
		return $gBitSystem->getConfig( 'switchboard_default_transport' );
	}

	/**
	 * Send an event to all event listeners
	 *
	 * $pPackage - The Package sending the message.
	 * $pEventType - The type of the event.
	 * $pRecipients - An array of userIds of recipients. If null all with registered preference and content owners will be sent a message
	 * $pContentId - The content_id of the object that triggered this message.
	 * $pDataHash - The message that is being sent.
	 *				Currently supported are: message and subject
	 */
	function sendEvent($pPackage, $pEventType, $pContentId, $pDataHash, $pRecipients = NULL) {
		global $gBitSystem, $gBitUser;
		$ret = FALSE;
		// Make sure event is registered so we can do prefs for them. This is for devs really
		if( !empty($this->mSenders[$pPackage]) && in_array($pEventType, array_keys( $this->mSenders[$pPackage]['types'] )) ) {
			$msgHash = $pDataHash;
			$msgHash['package'] = $pPackage;
			$msgHash['content_id'] = $pContentId;
			$msgHash['event_type'] = $pEventType;

			// Load users preferences
			$usersPrefs = $this->loadEffectivePrefs($pRecipients, $pPackage, $pEventType, $pContentId);
			// Check each delivery style
  			foreach( $usersPrefs as $transportType => $users ) {
				$msgHash['users'] = $users;
				$msgHash['transport_type'] = $transportType;
				$msgHash['use_queue'] = !empty( $this->mTransports[$transportType]['use_queue'] ) ? TRUE : FALSE;
				// send the message using the prefered delivery style
				$this->sendMsg( $msgHash );
			}
			$ret = TRUE;
		} else {
			bit_error_log( "Package: ".$pPackage." attempted to send message of type: ".$pEventType." but didn't register that it wanted to send this type." );
		}
		return $ret;
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
	function sendMsg( &$pParamHash ){
		global $gBitSystem;

		if( !empty( $pParamHash['transport_type'] ) ){
			if( empty( $pParamHash['recipients'] ) && !empty( $pParamHash['users'] ) ){
				$pParamHash['recipients'] = $pParamHash['users'];
			}
			// convenience
			$transport_type = $pParamHash['transport_type'];
			$recipients = !empty($pParamHash['recipients'])?$pParamHash['recipients']:NULL;
			$users = !empty( $pParamHash['users'] )?$pParamHash['users']:NULL;

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
				if( !empty( $pParamHash['use_queue'] ) && !empty( $pParamHash['content_id'] ) && !empty( $users ) ) {
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
						bit_error_log("Package: ".$this->mTransports[$transport_type]['package']." registered a non-existant send handler: ".$func);
					}
				}
			} 
			// handler error
			else {
				// display the list of recipients who are not getting the message
				$recipient_list = '';
				if( !empty( $recipients ) ){
					foreach ($recipients as $recipient) {
						// if we have users then we'll display their login name, otherwise display the address we were trying to send to
						$recipient_list .= ( !empty( $users ) ? $recipient['login'] : $recipient[$transport_type] ) . " ";
					}
				}
				bit_error_log("Delivery Style: ".$transport_type." for users: ". $recipient_list." not registered!");
			}
		} else {
			bit_error_log("Attempted to send message but didn't supply a type.");
		}
	}	

	// convenience function
	function sendEmail( &$pParamHash ){
		$pParamHash['transport_type'] = 'email';
		$this->sendMsg( $pParamHash );
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

		$this->mDb->associateInsert(BIT_DB_PREFIX."switchboard_queue", $messageStore);

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
		// Figure out each users effect prefs
		$ret = array();

		// user preference by content ownership - developer option - see registerSender - makes use of DefaultTransport 
		$ownerPrefs = !empty( $this->mSenders[$pPackage]['types'][$pEventType]['include_owner'] )?$this->loadOwnerPrefs( $pContentId ):array();
		// user preferences by package->eventtype
		$userWatchers = $this->loadUserPrefs($pRecipients, $pPackage, $pEventType);
		// user preferences by content id
		$contentWatchers = $this->loadContentPrefs($pRecipients, $pContentId );

		// order is important here to determine who wins. ownerPrefs should be first
		$prefs = array_merge( $ownerPrefs, $userWatchers, $contentWatchers );
		// Now reorder by delivery style
		foreach ($prefs as $user_id => $data) {
			// @TODO I have no idea if this is a new bug or an old bug - seems odd the user should have a null delivery_style pref - but it really fucks things up - wjames5
			if( !empty( $data['delivery_style'] ) ){
				$ret[$data['delivery_style']][$data['user_id']] = $data;
			}
		}
		return $ret;
	}

	/**
	 * Loads the users preferences for a given content object.
	 */
	function loadContentPrefs( $pRecipients = NULL, $pContentId = NULL ) {
		$bindVars = array();
		$whereSql = '';

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

		$query =   "SELECT uu.`user_id` AS `hash_key`, sp.*, uu.`email`, uu.`login`, uu.`real_name`, lc.`title`, lc.`content_type_guid` 
					FROM `".BIT_DB_PREFIX."switchboard_prefs` sp 
						LEFT JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (sp.`content_id` = lc.`content_id`) 
						LEFT JOIN `".BIT_DB_PREFIX."users_users` uu ON (sp.`user_id` = uu.`user_id`) 
					WHERE sp.`content_id` ".$whereSql;
		$prefs = $this->mDb->getAssoc( $query, $bindVars);
		return $prefs;
	}

	/**
	 * Loads the users for the content owner
	 */
	function loadOwnerPrefs( $pContentId ) {
		$bindVars[] = $pContentId;
		$query =   "SELECT uu.`user_id` AS `hash_key`, COALESCE( sp.`delivery_style`, '".$this->getDefaultTransport()."' ) AS `delivery_style`, sp.`package`, sp.`event_type`, uu.`user_id`, uu.`email`, uu.`login`, uu.`real_name`, lc.`content_id`, lc.`title`, lc.`content_type_guid` 
					FROM `".BIT_DB_PREFIX."liberty_content` lc
						INNER JOIN `".BIT_DB_PREFIX."users_users` uu ON (lc.`user_id` = uu.`user_id`) 
						LEFT JOIN `".BIT_DB_PREFIX."switchboard_prefs` sp ON (sp.`content_id` = lc.`content_id`) 
					WHERE lc.`content_id` = ? ";
		$prefs = $this->mDb->getAssoc( $query, $bindVars );
		return $prefs;
	}

	/**
	 * Loads the preferences for the given recipients, package and event
	 * If recipients is null then all users with registered preferences
	 * are loaded.
	 */
	function loadUserPrefs( $pRecipients = NULL, $pPackage = NULL, $pEventType = NULL ) {
		$whereSql = '';
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

		$query =   "SELECT uu.`user_id` AS `hash_key`, sp.*, uu.`email`, uu.`login`, uu.`real_name` 
					FROM `".BIT_DB_PREFIX."switchboard_prefs` sp 
						LEFT JOIN `".BIT_DB_PREFIX."users_users` uu ON (sp.`user_id` = uu.`user_id`) 
		 			WHERE sp.`content_id` IS NULL ".$whereSql;
		$prefs = $this->mDb->getAssoc( $query, $bindVars );
		return $prefs;
	}

	/**
	 * Deletes a preference for the given user, either a default or content permission.
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
		$ret = FALSE;
		if ($this->senderIsRegistered($pPackage, $pEventType)) {
			// important for how we deal with default deliverystyle
			$includeOwner = !empty( $this->mSenders[$pPackage]['types'][$pEventType]['include_owner'] );
			$this->mDb->StartTrans();
			$this->deleteUserPref($pUserId, $pPackage, $pEventType, $pContentId);
			if( 
				// not include owner && sytle not none
				( !$includeOwner && $pDeliveryStyle != 'none' ) ||
				// include owner && style not default 
				( $includeOwner && $pDeliveryStyle != $this->getDefaultTransport() )
				) {	
				$query = "INSERT INTO `".BIT_DB_PREFIX."switchboard_prefs` (`package`, `event_type`, `user_id`, `content_id`, `delivery_style`) VALUES (?, ?, ?, ?, ?)";
				$this->mDb->query( $query, array( $pPackage, $pEventType, $pUserId, $pContentId, $pDeliveryStyle ) );
			}
			$this->mDb->CompleteTrans();
			$ret = TRUE;
		}
		return $ret;
	}

	/**
	 * Checks if a package is registered as a sender.
	 * $pSender - The package to check.
	 */
	function senderIsRegistered($pSender, $pType = NULL) {
		if (!empty($pType)) {
			return !empty($this->mSenders[$pSender]) && in_array($pType, array_keys($this->mSenders[$pSender]['types']));
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
						bit_error_log(  tra( "Package registered a non-existant function listener:" )." ".$this->mTransports[$delivery_style]['send_function']." => $func" );
					}
				}
			}
		}
	}
}

?>
