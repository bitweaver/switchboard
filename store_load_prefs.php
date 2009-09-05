<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/store_load_prefs.php,v 1.6 2009/09/05 18:54:31 wjames5 Exp $
 * @package switchboard
 * @subpackage functions
 */

/**
 * Initialization
 */
require_once('../bit_setup_inc.php');

if( ! $gBitUser->isRegistered() ) {
	$gBitSystem->setHttpStatus(403);
	$gBitSystem->fatalError('You must be registered to set switchboard preferences.');
}

// Save (if not anonymous user
if( !empty($_REQUEST['saveSwitchboardPrefs']) && $gBitUser->isRegistered() ) {
	if( isset($_REQUEST['SBDefault']) ) {
		foreach ($_REQUEST['SBDefault'] as $package => $types) {
			foreach ($types as $type => $preference) {
				if( !$gSwitchboardSystem->storeUserPref($gBitUser->mUserId, $package, $type, NULL, $preference) ) {
					$gBitSystem->fatalError("Attempt to register a perference for a package that is not registered.");
				}
			}
		}
	}
	if( isset($_REQUEST['SBContent']) ) {
		foreach ($_REQUEST['SBContent'] as $content_id => $packages) {
			foreach ($packages as $package => $types) {
				foreach ($types as $type => $preference) {
					if( !$gSwitchboardSystem->storeUserPref($gBitUser->mUserId, $package, $type, $content_id, $preference) ) {
						$gBitSystem->fatalError("Attempt to register a perference for a package that is not registered.");
					}
				}
			}
		}
	}
}

// Get the default preferences
/* @Todo create new method for loading all package=>type prefs of user 
 * loadUserPrefs is for getting a list of users by package and type 
 * and its getAssoc is not workable for the need here
 * but we'll abuse it until its an issue -wjames5
 */
$defaults = array();
foreach( $gSwitchboardSystem->mSenders as $package=>$data ){ 
	foreach( $data['types'] as $type=>$opts ){
		// make it associate the way we want.
		$prefs = $gSwitchboardSystem->loadUserPrefs( $gBitUser->mUserId, $package, $type );
		if( !empty( $prefs[$gBitUser->mUserId] ) ){
			$defaults[$package][$type] = $prefs[$gBitUser->mUserId];
		}
	}
}
$gBitSmarty->assign('switchboardPrefs', $defaults);

// Now get the content preferences
$contentTitles = array();
$contentPrefs = array();
if( empty($_REQUEST['content_id']) ) {
	$prefs = $gSwitchboardSystem->loadContentPrefs($gBitUser->mUserId);
	if( !empty($prefs) ) {
		foreach( $prefs as $data ) {
			$contentTitles[$data['content_id']] = $data['title'];
		}
		foreach( $prefs as $data ) {
			$contentPrefs[$data['content_id']][$data['package']][$data['event_type']] = $data;
		}
	}
} else {
	$prefs = $gSwitchboardSystem->loadContentPrefs($gBitUser->mUserId, $_REQUEST['content_id']);
	if( empty($prefs) ) {
		// No prefs yet. Need to get the title.
		require_once(LIBERTY_PKG_PATH.'lookup_content_inc.php');

		// Make sure they are allowed to look at the content
		$gContent->verifyViewPermission();

		$contentTitles[$_REQUEST['content_id']] = $gContent->mInfo['title'];
		$contentPrefs[$_REQUEST['content_id']] = array();
	} else {
		foreach( $prefs as $data ) {
			$contentPrefs[$data['content_id']][$data['package']][$data['event_type']] = $data;
		}
	}
	$gBitSmarty->assign('switchboardContentId', $_REQUEST['content_id']);
}

$gBitSmarty->assign('switchboardContentPrefs', $contentPrefs);
$gBitSmarty->assign('switchboardContentTitles', $contentTitles);
