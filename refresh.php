<?php
require( 'libs/CoderSitefuncs.php' );

// this file is run through a cron-job at 3 AM each day to refresh each coding site specified in 'commtv_refresh'

$config = parse_ini_file( 'config.ini' );
$servername = $config['servername'];
$username = $config['username'];
$password = $config['password'];

$dbname = 'commtv_refresh';
$today = date("Y-m-d H:i:s");

$serverConnection = mysqli_connect( $servername, $username, $password, $dbname );
if (mysqli_connect_errno()) {
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

$sql = "SELECT * FROM `sites`";

if (!mysqli_query($serverConnection,$sql)) {
	die('Error: ' . mysqli_error($serverConnection));
}

$result = $serverConnection->query($sql);
mysqli_close($serverConnection);

while( $row = $result->fetch_assoc() ){
	
	$dbname = $row['site name'];
	print( $dbname . PHP_EOL );
	refreshNew( $dbname, 0 );
}

?>
