<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_switchboard/index.php,v 1.3 2009/03/31 05:53:55 lsces Exp $
 * @package switchboard
 * @subpackage functions
 */

/**
 * Initialization
 */
require_once('../bit_setup_inc.php');

require_once(SWITCHBOARD_PKG_PATH.'SwitchboardSystem.php');

require_once(SWITCHBOARD_PKG_PATH.'store_load_prefs.php');

$gBitSystem->display('bitpackage:switchboard/edit_prefs.tpl', 'Switchboard Preferences', array( 'display_mode' => 'display' ));

?>