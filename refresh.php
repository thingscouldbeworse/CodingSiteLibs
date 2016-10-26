<?php
$location_relative = __DIR__ . '/libs/CoderSiteFuncs.php';
$location_absolute = '/var/www/CodingSiteLibs/libs/CoderSiteFuncs.php';


if( (( @include $location_relative ) === false) && (( @include $location_absolute ) === false) ){
	exit( "neither libs location is valid!" . PHP_EOL );
}

// this file is run through a cron-job at 3 AM each day to refresh each coding site specified in 'commtv_refresh'
// or at least it's supposed to... logs are appended to /home/comadmin/logs/ by the little shell script that's
// actually run by the crontab. if the cronjob is not working correctly check there, and if the logfiles aren't
// showing up as they should check the .sh file because it probably has some nonsense specific to the instance it
// was running on which is my bad.

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
