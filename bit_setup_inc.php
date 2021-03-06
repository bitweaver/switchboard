<?php
/**
 * @package switchboard
 */

global $gBitSystem, $gLibertySystem, $gBitThemes;

$registerHash = array(
	'package_name' => 'switchboard',
	'package_path' => dirname( __FILE__ ).'/',
	'homeable' => TRUE,
);
$gBitSystem->registerPackage( $registerHash );

if( $gBitSystem->isPackageActive( 'switchboard' ) ) {
	/*
	$menuHash = array(
		'package_name'       => SWITCHBOARD_PKG_NAME,
		'index_url'          => SWITCHBOARD_PKG_URL.'index.php',
		'menu_template'      => 'bitpackage:switchboard/menu_switchboard.tpl',
	);
	$gBitSystem->registerAppMenu( $menuHash );
	*/

	$gLibertySystem->registerService( SWITCHBOARD_PKG_NAME, SWITCHBOARD_PKG_NAME,
		array(
			'users_expunge_function'  => 'switchboard_user_expunge',
			'content_expunge_function'  => 'switchboard_content_expunge',
			'content_icon_tpl'           => 'bitpackage:switchboard/service_content_icon_inc.tpl',
		)
	);

//	$gBitSystem->registerNotifyEvent( array( "switchboard_request" => tra("A switchboard request is made.") ) );
//	$gBitSystem->registerNotifyEvent( array( "switchboard_reply" => tra("A switchboard reply is made.") ) );


	// Initialize the switchboard system global if we haven't already
	require_once( 'SwitchboardSystem.php' );

	$gSwitchboardSystem = new SwitchboardSystem();
	$gSwitchboardSystem->loadPlugins();

	// add sender for liberty comment - this is here because of chick and egg with liberty switchboar and themes->loadCSS - this should be in liberty
	if( $gBitSystem->isPackageActive( 'switchboard' )) {
		global $gSwitchboardSystem;
		$gSwitchboardSystem->registerSender( 'My Content', 'new comment', array( 'include_owner' => TRUE ) );
	}

	// Store it in the context.
	$gBitSmarty->assign_by_ref( 'gSwitchboardSystem', $gSwitchboardSystem );
	$gBitThemes->loadCss( SWITCHBOARD_PKG_PATH.'switchboard.css' );

	// make sure all tags from a deleted user are nuked
	function switchboard_user_expunge( &$pObject ) {
		if( is_a( $pObject, 'BitUser' ) && !empty( $pObject->mUserId ) ) {
			$pObject->mDb->StartTrans();
			$pObject->mDb->query( "DELETE FROM `".BIT_DB_PREFIX."switchboard_prefs` WHERE `user_id`=?", array( $pObject->mUserId ) );
			$pObject->mDb->CompleteTrans();
		}
	}

}


