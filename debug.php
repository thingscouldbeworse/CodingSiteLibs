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
	#print_r( $search_hash );

	$search_array = hashToArray( $search_hash[0] ); // convert stored hash to workable array
	#$print_r( $search_array );

	$search_array = updateSearch( $search_array );
	print_r( $search_array );

	$searchHash = arrayToHash( $search_array ); // convert date-changed array back into a hash

	$num_before = numTranscripts( $dbname ); // find out how many transcripts were in place prior to refresh
	print( "num before: " . $num_before . PHP_EOL );

	$debug = 1;
	$upper_limit = sizeof( $searchHash );
	debugPrint( $upper_limit, $debug, 'upper limit' );	
	$index = 0;

	while( $index < $upper_limit ){

		debugPrint( $search_hash, $debug, 'searchHash' );

		$limit = 50;
		$offset = 0;

		$transcript_ids = [];

		$search_results_json = runCurlSearch( $searchHash, $limit, $offset );
		//debugPrint( $search_results_json, $debug, 'search results' );
		$search_array = (array)$search_results_json->results->hits;

		while( sizeof($search_array['hits']) < $search_array['total'] ){
			$offset = $offset + 50;
			$search_results_json = runCurlSearch( $searchHash, $limit, $offset );
			$array2 = (array)$search_results_json->results->hits;

			$search_array =  addSearchArray( $search_array, $array2 );
		}

		foreach( $search_array['hits'] as $hit ){
			array_push( $transcript_ids, $hit->_id );
		}
		$index = $index + 1;
	}





	print_r( $transcript_ids );

	//refreshNew( $dbname, 0 );
}

?>
