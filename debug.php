<?php
require( 'libs/CoderSitefuncs.php' );



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

	$search_hash = retrieveSearchHash( $dbname ); // get the search associated with the site
	print_r( $search_hash );

	$search_array = hashToArray( $search_hash ); // convert stored hash to workable array
	print_r( $search_array );

	//refreshNew( $dbname, 0 );
}

?>
