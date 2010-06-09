<?php
/**
 * @version $Header$
 * @package switchboard
 * @subpackage functions
 */

/**
 * Initialization
 */
require_once('../kernel/setup_inc.php');

require_once(SWITCHBOARD_PKG_PATH.'SwitchboardSystem.php');

require_once(SWITCHBOARD_PKG_PATH.'store_load_prefs.php');

$gBitSystem->display('bitpackage:switchboard/edit_prefs.tpl', 'Switchboard Preferences', array( 'display_mode' => 'display' ));

?>