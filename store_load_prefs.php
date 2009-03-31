<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/store_load_prefs.php,v 1.5 2009/03/31 05:53:55 lsces Exp $
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
$prefs = $gSwitchboardSystem->loadUserPrefs( $gBitUser->mUserId );

// Now make it associate the way we want.
$defaults = array();
if( !empty($prefs) ) {
	foreach( $prefs as $data ) {
		$defaults[$data['package']][$data['event_type']] = $data;
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
