<?php
$_SERVER['SCRIPT_URL'] = __FILE__;
$_SERVER['REQUEST_URI'] = __FILE__;
$_SERVER['REQUEST_METHOD'] = '';
$_SERVER['SERVER_PROTOCOL'] = '';
$_SERVER['HTTP_HOST'] = '';
$_SERVER['HTTP_USER_AGENT'] = 'console';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';

require_once( '../../../bit_setup_inc.php' );

require_once( 'Aim.php' );

$aimClient = new Aim( $gBitSystem->getConfig( 'switchboard_aim_screenname' ), $gBitSystem->getConfig( 'switchboard_aim_password' ), 4 );
$aimClient->myServer = $gBitSystem->getConfig( 'switchboard_aim_server', 'aimexpress.oscar.aol.com' ); // "toc-m08.blue.aol.com" );
//$aimClient->registerHandler("IMIn","ImINHand");
//$aimClient->registerHandler("Rvous","OnRvousIn");
//$aimClient->registerHandler("DimIn","onDimIn");
//$aimClient->registerHandler("ChatInvite","chatInvite");
//$aimClient->registerHandler("ChatIn","chatMessage");
//$aimClient->registerHandler("CatchAll","onFallThrough");
$aimClient->registerHandler( "Error", "switchboard_aim_error" );
$aimClient->signon();
$aimClient->setProfile( '', true );

/* Based on submitted code at http://us.php.net/manual/en/function.stream-socket-server.php

Use stream_socket_server and stream_select() to make a server that accepts more than one connections (can have many clients connected):
In master we hold all opened connections. Just before calling stream select we copy the array to $activeSockets and then pass it ot stream_select(). In case that we may read from at least one socket, $activeSockets will contain socket descriptors. $allSockets is needed not to lose references to the opened connections we have.
*/

$allSockets = array();
$listenAddress = $gBitSystem->getConfig( 'switchboard_aim_server_ip', '127.0.0.1' ).':'.$gBitSystem->getConfig( 'switchboard_aim_server_port', '5190' );
echo "Attempting local listener on tcp://$listenAddress\n";
if( $socket = stream_socket_server("tcp://$listenAddress", $errno, $errstr) ) {
	$allSockets[] = $socket;
	$activeSockets = $allSockets;
	while (1) {
		$aimClient->receive();

		// make a copy of $allSockets on each iteration, stream_select will modify $activeSockets to only have those with actual data
		$activeSockets = $allSockets;
		if( stream_select($activeSockets, $_w = NULL, $_e = NULL, 2) !== FALSE ) {
			foreach( array_keys( $activeSockets ) as $i ) {
				if ($activeSockets[$i] === $socket) {
					$conn = stream_socket_accept($socket);
	//			  fwrite($conn, "Hello! The time is ".date("n/j/Y g:i a")."\n");
					$allSockets[] = $conn;
				} else {
					$sockData = trim( fread( $activeSockets[$i], 1024 ) );
					if( strlen( $sockData ) === 0 ) { // connection closed
						fclose( $activeSockets[$i] );
					} elseif ( $sockData === FALSE ) {
						bit_error_log( __FILE__." no socket data" );
					} else {
						list( $recipient, $message ) = split( ":", $sockData, 1 );
						$aimClient->sendIm( $recipient, $message );
						fwrite( $activeSockets[$i], "AIM sent to: $recipient\n" );
						fclose( $activeSockets[$i] );
					}
					$key = array_search( $activeSockets[$i], $allSockets, TRUE );
					unset( $allSockets[$key] );
				}
			}
		}
	}
} else {
	bit_error_log( __FILE__." could not open socket: $errstr ($errno)<br />\n" );
}

function switchboard_aim_error( $pErrorMsg ) {
	bit_error_log( $pErrorMsg );
}
?>
