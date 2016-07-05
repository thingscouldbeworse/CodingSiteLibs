<?php

// change date in search_array to today's date
function updateSearch( $search_array ){

	$today = date("Y-m-d") . 'T01%3A00%3A00.000Z';
	$search_array['date_to'] = $today;

	return $search_array;
}

// change date_from to only search for new transcripts
function backUpdateSearch( $search_array ){

	$search_array['date_from'] = $search_array['updated_at'];

	return $search_array;
}

// recode search hash with real brackets
function recodeHash( $search_hash ){

	$search_hash = str_replace( '%5B', '[', $search_hash );
	$search_hash = str_replace( "%5D", ']', $search_hash );

	return $search_hash;
}

// adds a string (formatted search hash) to the DB table for this coding site
// allows for future retrieval to run searches again and append new results
function addRawSearchHash( $dbname, $search_hash ){
	
	$config = parse_ini_file( 'config.ini' );
	$servername = $config['servername'];
	$username = $config['username'];
	$password = $config['password'];

	$today = date("Y-m-d H:i:s");

	$serverConnection = mysqli_connect( $servername, $username, $password, $dbname );
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " . mysqli_connect_error();
	}

	$search_array = hashToArray( $search_hash );
	$updated_at = $today;

	$sql = "INSERT INTO `settings`(`id`, `name`, `value`, `created_at`) 
	VALUES ('4','search hash', '$search_hash', '$today')
	ON DUPLICATE KEY UPDATE
	value='$search_hash', updated_at='$updated_at'";

	if (!mysqli_query($serverConnection,$sql)) {
	  die('Error: ' . mysqli_error($serverConnection));
	}
	
	mysqli_close($serverConnection);
}

// returns the previously stored search hash
function retrieveSearchHash( $dbname ){

	$config = parse_ini_file( 'config.ini' );
	$servername = $config['servername'];
	$username = $config['username'];
	$password = $config['password'];
	
	$today = date("Y-m-d") . "T" . date("H:i:s") . "000Z";


	$serverConnection = mysqli_connect( $servername, $username, $password, $dbname );
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " . mysqli_connect_error();
	}

	$sql = "SELECT `id`, `name`, `value`, `created_at`, `updated_at` FROM `settings` WHERE `name`= 'search hash'";
	$result = $serverConnection->query( $sql );

	if (!mysqli_query($serverConnection,$sql)) {
	  die('Error: ' . mysqli_error($serverConnection));
	}
	
	mysqli_close($serverConnection);
	
	if( $result->num_rows > 1 ){
		$hash = [];
		while( $row = mysqli_fetch_assoc($result) ){
			array_push( $hash, $row['value'] );
		}
	}
	else{
		$row = $result->fetch_assoc();

		$hash = $row['value'];
		$updated_at = $row['updated_at'];

		$updated_pos = strpos( $hash, '[updated_at]=' );
		//$hash = substr_replace( $hash, $updated_at, $updated_pos+13, 0 );
	}
	return $hash;
}

// returns an array with the channel added to search parameters
function addChannel( $search_array, $channel ){

	$search_array['channels'][] = $channel;

	$today = date("Y-m-d") . "T" . date("H:i:s") . "000Z";
	$search_array['updated_at'] = $today;

	return $search_array;
}

// returns an array built off the given search hash
function hashToArray( $search_hash ){

	$search_hash = recodeHash( $search_hash );

	$exploded = explode("&search", $search_hash);
	$search_array = array();

	foreach( $exploded as $line ){
		if( strpos( $line, '[name]' ) ){
			$search_array['name'] = explode( "=", $line )[1];
		}
		else if(strpos( $line, 'date_from]' )){
			$search_array['date_from'] = explode( "=", $line )[1];
		}
		else if(strpos( $line, 'date_to]' )){
			$search_array['date_to'] = explode( "=", $line )[1];
		}
		else if(strpos( $line, 'time_of_day_from]' )){
			$search_array['time_of_day_from'] = explode( "=", $line )[1];
		}
		else if(strpos( $line, 'time_of_day_to]' )){
			$search_array['time_of_day_to'] = explode( "=", $line )[1];
		}
		else if(strpos( $line, 'created_at]' )){
			$search_array['created_at'] = explode( "=", $line )[1];
		}
		else if(strpos( $line, 'updated_at]' )){
			$search_array['updated_at'] = explode( "=", $line )[1];
		}
		else if(strpos( $line, 'channels][]' )){
			$search_array['channels'][] = explode( "=", $line )[1];
		}
		else if(strpos( $line, 'queries]' )){
			if( strpos($line, 'query]') ){
				$search_array['queries']['query'] = explode( "=", $line )[1];
			}
			else if( strpos($line, 'label]') ){
				$search_array['queries']['label'] = explode( "=", $line )[1];
			}
			else if( strpos($line, 'showResults]') ){
				$search_array['queries']['showResults'] = explode( "=", $line )[1];
			}
		}
	}

	return $search_array;
}

// returns a search hash built off the given array
function arrayToHash( $search_array ){

	$search_hash = '';
	$search_hash .= 'search[name]=' . $search_array['name'];
	$search_hash .= '&search[queries][0][query]=' . $search_array['queries']['query']; 
	$search_hash .= '&search[queries][0][label]=' . $search_array['queries']['label'];
	$search_hash .= '&search[queries][0][showResults]=' . $search_array['queries']['showResults'];
	if( isset($search_array['date_from']) ){
		$search_hash .= '&search[date_from]=' . $search_array['date_from'];
		$search_hash .= '&search[date_to]=' . $search_array['date_to'];
	}
	if( isset($search_array['time_of_day_from']) ){
			$search_hash .= '&search[time_of_day_from]=' . $search_array['time_of_day_from'];
			$search_hash .= '&search[time_of_day_to]=' . $search_array['time_of_day_to'];
	}
	$search_hash .= '&search[created_at]=' . $search_array['created_at'];
	$search_hash .= '&search[updated_at]=' . $search_array['updated_at'];

	foreach( $search_array['channels'] as $channel ){
		$search_hash .= '&search[channels][]=' . $channel;
	}


	return $search_hash;
}

/*
	return a php array of the search with as many properties as arguments
	search hash needs these fields
		
		search[name]
		search[queries][0][query]
		search[queries][0][label]
		search[queries][0][showResults]
		search[date_from]
		search[date_to]
		search[time_of_day_from]
		search[time_of_day_to]
		search[created_at]
		search[updated_at]
		search[channels][]
		*/
function createSearch( 	$name,
						$query, 
						$label, 
						$showResults= 'true',  
						$date_from = '',
						$date_to = '',
						$time_of_day_from = '',
						$time_of_day_to = ''
						){ // channels need to be added in after, 'created' and 'updated' are added by $today

	$today = date("Y-m-d") . "T" . date("H:i:s") . "000Z";

	$search_array = array(
		"name" => $name,
		"queries" => array(
			"query" => $query,
			"label" => $label,
			"showResults" => $showResults
		),
		"channels" => array()
	);
	if( $date_from != '' ){
		$search_array["date_from"] = $date_from;
		$search_array["date_to"] = $date_to;
	}
	if( $time_of_day_from != '' ){
		$search_array["time_of_day_from"] = $time_of_day_from;
		$search_array["time_of_day_to"] = $time_of_day_to;
	}
	$search_array['created_at'] = $today;
	$search_array['updated_at'] = $today;

	return $search_array;
}


?>
