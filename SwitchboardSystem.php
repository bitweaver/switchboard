<?php

/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/SwitchboardSystem.php,v 1.1 2008/03/23 18:57:40 nickpalmer Exp $
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
 * @version  $Revision: 1.1 $
 * @package  switchboard
 */

global $gSwitchboardSystem;

/*
   A handy flag to turn on some validation of your transition table
   when you are developing switchboard for a package.
*/
define( 'SWITCHBOARD_DEVELOPMENT', TRUE );

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
	 * handleSwitchboardNotification($pSwitchboardEvent) Where
	 * $pSwitchboardEvent is the array with data on the event
	 *
	 * $pDeliveryStyles - The delivery style being registered
	 * $pFunction - The function that will get the callback
	 *
	 */
    function registerSwitchboardListener( $pDeliveryStyle, $pFunction ) {
		$this->mListeners[$pDeliveryStyle]['function'] = $pFunction;
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
	 * $pPacakge - The Package sending the message.
	 * $pEventType - The type of the event.
	 * $pRecipients - An array of userIds of recipients. If null all with registered preference will be sent a message
	 * $pContentId - The content_id of the object this is about.
	 * $pDataHash - The message that is being sent.
	 */
	function sendEvent($pPackage, $pEventType, $pMessageBody, $pContentId, $pRecipients = NULL) {

		global $gBitSystem, $gBitUser;

		// Only registered users get messages routed.
		if( !$gBitUser->isRegistered() ) {
			return;
		}

		// Make sure event is registered so we can do prefs for them. This is for devs really
		if( !empty($this->mSenders[$pPackage]) && !empty($this->mSenders[$pPackage]['types'][$pEventType]) ) {

			$event['package'] = $pPackage;
			$event['content_id'] = $pContentId;
			$event['event_type'] = $pEventType;
			$event['message'] = $pMessageBody;

			// Load users preferences
			$usersPrefs = $this->loadUsersPreferences($pRecipients, $pPackage, $pEventType, $pContentId, TRUE);

			// Send the message for each recipient
			foreach( $usersPrefs as $recipient => $prefs ) {
				foreach( $mListeners as $package ) {
					// Is this package active, and does the recipient use it?
					if( $gBitSystem->isPackageActive( $package ) && !empty($prefs[$package])){
						$func = $package['function'];
						if ( function_exists($func) ) {
							$event['delivery_style'] = $prefs[$package]['delivery_style'];
							$func($event);
						} else {
							$gBitSystem->fatalError("Package: ".$package," registered a non-existant function listener: ".$func);
						}
					}

				}
			}
		}
		else {
			$gBitSystem->fatalError("Package: ".$pPackage." attempted to send message of type: ".$pEventType." but didn't register that it wanted to send this type.");
		}
	}

	function loadEffectivePrefs( $pRecipients, $pPackage, $pEventType, $pContentId = NULL) {
		$defaults = $this->loadPrefs($pRecipients, $pPackage, $pEventType);
		$overrides = $this->loadPrefs($pRecpients, $pContentId);

		$ret = array();
		foreach ($defaults as $data) {
			$ret[$data['user_id']] = $data['delivery_style'];
		}
		foreach ($overrides as $data) {
			$ret[$data['user_id']] = $data['deliverty_style'];
		}

		return $ret;
	}

	function loadContentPrefs( $pRecipients = NULL, $pContentId = NULL ) {
		$selectSql = "SELECT sp.*, lc.`title`, lc.`content_type_guid`  ";
		$fromSql = "FROM `".BIT_DB_PREFIX."switchboard_prefs` sp ";
		$joinSql = "LEFT JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (sp.`content_id` = lc.`content_id`) ";
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
		$selectSql = "SELECT sp.* ";
		$fromSql = "FROM `".BIT_DB_PREFIX."switchboard_prefs` sp ";
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

		$prefs = $this->mDb->getArray($selectSql.$fromSql.$whereSql, $bindVars);

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

function switchboard_send_email() {
	vd("CALLED SEND EMAIL");
	die;
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
	$gSwitchboardSystem->registerSwitchboardListener('email', 'switchboard_send_email');
	$gSwitchboardSystem->registerSwitchboardListener('digest', 'switchboard_send_digest');

	$gSwitchboardSystem->registerSwitchboardSender('switchboard', array('type1','type2'));
	$gSwitchboardSystem->registerSwitchboardSender('group', array('grouptype1','grouptype2'));
	$gSwitchboardSystem->registerSwitchboardSender('conference', array('type1','type2'));
	// Store it in the context.
	$gBitSmarty->assign_by_ref('gSwitchboardSystem', $gSwitchboardSystem);
}

?>
