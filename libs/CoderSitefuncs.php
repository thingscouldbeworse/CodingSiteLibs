<?php

require( 'SearchObjectFuncs.php' );
require( __DIR__ . '/../vendor/autoload.php' );

// useful API functions are closer to the end of this file, boilerplate at the beginning

// reads list of transcript IDs to add from a file 
// (for manual insertion of transcripts from previously run search)
// normally transcript IDs should be added by a search
// see cURL requests sent in runCurlSearch()
function readFromFile( $fileName ){
	$lines = file( $fileName );

	return $lines;
}

// returns an array of each email associated with users for the site 
function retrieveEmails( $dbname ){

	$config = parse_ini_file( 'config.ini' );
	$servername = $config['servername'];
	$username = $config['username'];
	$password = $config['password'];

	$serverConnection = mysqli_connect( $servername, $username, $password, $dbname );
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " .mysqli_connect_error();
	}

	$sql = "SELECT * FROM `user`";

	if (!mysqli_query($serverConnection,$sql)) {
		die('Error: ' . mysqli_error($serverConnection));
	}

	$result = $serverConnection->query($sql);
	mysqli_close($serverConnection);

	$emails = array();
	while( $row = $result->fetch_assoc() ){
		array_push( $emails, array($row['email'], $row['first_name'], $row['last_name'], $row['role']) );
	}

	return $emails;
}

// returns the number of total transcripts in the db at $dbname
function numTranscripts( $dbname ){

	$config = parse_ini_file( 'config.ini' );
	$servername = $config['servername'];
	$username = $config['username'];
	$password = $config['password'];

	$serverConnection = mysqli_connect( $servername, $username, $password, $dbname );
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " .mysqli_connect_error();
	}

	$sql = "SELECT COUNT(*) FROM `transcript`";

	if (!mysqli_query($serverConnection,$sql)) {
		die('Error: ' . mysqli_error($serverConnection));
	}

	$result = $serverConnection->query($sql);
	mysqli_close($serverConnection);

	$count = $result->fetch_assoc();

	return $count['COUNT(*)'];
}

// properly set flv so the little video icons will work 
// this function will also update transcript contents, be warned
function refreshHasVideo( $dbname ){

	$config = parse_ini_file( 'config.ini' );
	$servername = $config['servername'];
	$username = $config['username'];
	$password = $config['password'];

	$serverConnection = mysqli_connect( $servername, $username, $password, $dbname );
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " .mysqli_connect_error();
	}

	$sql = "SELECT * FROM `transcript`";
	$result = mysqli_query($serverConnection, $sql );

	if (!$result) {
    	echo "Could not successfully run query ($sql) from DB: " . mysql_error();
	    exit;
	}

	if (!mysqli_query($serverConnection,$sql)) {
	  die('Error: ' . mysqli_error($serverConnection));
	}
	
	while ($row = mysqli_fetch_assoc($result)) {
	    
	    $transcript_json_object = fetchSingleTranscript( $row['trans_id'] );

	    $has_video = $transcript_json_object->transcript->has_video;
	    $transcript_id = $transcript_json_object->transcript->trans_id;
	    $today = date("Y-m-d H:i:s");

	    $transformed_text = parseTranscriptText( $transcript_json_object );
		$transformed_text = mysqli_real_escape_string($serverConnection, $transformed_text);

	    $sql = "UPDATE `transcript` 
	    		SET `flv`='$has_video', `updated_at`='$today', `contents`='$transformed_text'
	    		WHERE `trans_id`='$transcript_id'";

	    print( "Updating " . $transcript_json_object->transcript->trans_id . PHP_EOL );
	   	if (!mysqli_query($serverConnection,$sql)) {
		  die('Error: ' .mysqli_error($serverConnection));
		}
	    
	}

	mysqli_close($serverConnection);
}

// returns true if the number of results is less than the limit (no transcript IDs excluded)
function checkSearchResults( $search_results_json, $limit ){
	
	if( sizeof($search_results_json->results->hits->hits) < $limit ){
		return 1;
	}
	else{
		return 0;
	}
}

// returns true if there is a file located at the given path
function checkVideoLocation( $transcript_json_object ){
	
	return file_exists( $transcript_json_object->transcript->video_location );
}

// changes JSON text contents of transcript to one string to be inserted into DB field
function parseTranscriptText( $transcript_json_object ) {

	$transformed_text = "";
	$lines = $transcript_json_object->transcript->contents;
	foreach( $lines as $line ){
		if( isset($line->startTime) ){
			$start_time = gmdate( "H:i:s", $line->startTime );
			$end_time = gmdate( "H:i:s", $line->stopTime );
			$text = $line->text;
			
			$transformed_text .= $start_time ."," .$end_time ."," .$text ."\n";
		}
	}

	return $transformed_text;
}

// adds a transcript contents and video location to the given coding site DB
function insertSingleTranscript( $dbname, $transcript_json_object ) {
	
	$config = parse_ini_file( 'config.ini' );
	$servername = $config['servername'];
	$username = $config['username'];
	$password = $config['password'];

	$serverConnection = mysqli_connect( $servername, $username, $password, $dbname );
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " .mysqli_connect_error();
	}

	$transformed_text = parseTranscriptText( $transcript_json_object );
	$transformed_text = mysqli_real_escape_string($serverConnection, $transformed_text);
	
	$id = $transcript_json_object->transcript->trans_id;
	$channel = $transcript_json_object->transcript->channel;
	$datetime = $transcript_json_object->transcript->datetime;
	$today = date("Y-m-d H:i:s");

	$green  = "\033[32m";
	$Red 	= "\033[31m";
	$off 	= "\033[0m";

	if( isset($transcript_json_object->transcript->video_location) ){
		print( $green . "true " . $off );
		$has_video = "1";
	}
	else{
		print( $Red . "false " . $off );
		$has_video = "0";
	}


	$sql = "INSERT INTO transcript (trans_id, channel, flv, flv_rtmp, datetime, contents, created_at)
	VALUES ('$id', 
		'$channel', 
		'$has_video', 
		'array_7', 
		'$datetime', 
		'$transformed_text',
		'$today')
	ON DUPLICATE KEY UPDATE
	contents='$transformed_text', flv='$has_video', flv_rtmp='array_7', updated_at='$today'";

	if (!mysqli_query($serverConnection,$sql)) {
	  die('Error: ' .mysqli_error($serverConnection));
	}
	
	mysqli_close($serverConnection);
}

// returns the JSON object result of a curl against 7878/api endpoints for search queries
// use fetchSingleTranscript for cURLing for a specific trasncript_id
function runCurlSearch( $searchHash, $limit, $offset ){

	$url = "http://10.163.72.22:7878/api/searches/results"; //limit=100&offset=0
	$PHPSESSID = "jtoergobvciijml6r1ct0n1su7";

	$curl = curl_init();

	curl_setopt( $curl, CURLOPT_URL, $url ."?limit=" .$limit ."&offset=" .$offset );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $curl, CURLOPT_POSTFIELDS, $searchHash );
	curl_setopt( $curl, CURLOPT_POST, 1 );

	$headers = array();
	$headers[] = "Cookie: PHPSESSID=" .$PHPSESSID;
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

	$result = curl_exec($curl);
	if (curl_errno($curl)) {
	    echo 'Error:' .curl_error($curl);
	}
	curl_close ($curl);

	$result_json_object = json_decode( $result );
	return $result_json_object;
}

// returns all details about an individual transcript as a json object
function fetchSingleTranscript( $transcript_id ) {

	$url = "http://10.163.72.22:7878/api/transcripts/";
	$sess_id = "jtoergobvciijml6r1ct0n1su7";
	
	$curl = curl_init();

	curl_setopt($curl, CURLOPT_URL, $url .$transcript_id );
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");


	$headers = array();
	$headers[] = "Cookie: PHPSESSID=" .$sess_id;
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

	$result = curl_exec($curl);
	if (curl_errno($curl)) {
	    echo 'Error:' .curl_error($curl);
	}
	curl_close ($curl);

	$result_json_object = json_decode( $result );
	
	/* 	object has shape of:
		
		transcript
			id
			trans_id
			channel
			broadcast
			channel_broadcast
			datetime
			city
			stat
			created_at
			updated_at
			contents
				number
				text
				startTime
				stopTime 
			has_video
			has_schedule
			video_location
			show
			previous_transcript_id
			next_transcript_id 			*/

	return $result_json_object;
}

// returns a single search array summed from the two given
function addSearchArray( $array1, $array2 ){

	foreach( $array2['hits'] as $hit ){

		array_push( $array1['hits'], $hit );
	}

	return( $array1 );
}

// retrieves the list of transcript ID's based on previously established search hash
// returns transcript IDs as array
function retrieveTranscriptIDList( $dbname ){

	$limit = 50;
	$offset = 0;

	$transcript_ids = [];

	$search_hash = retrieveSearchHash( $dbname );

	$upper_limit = sizeof( $search_hash );
	$index = 0;
	while( $index < $upper_limit ){
		print( "index: " . $index . "\n" );
		if( isset($search_hash[0]) ){
			$searchHash = $search_hash[$index];
		}
		else{
			$searchHash = $search_hash;
		}

		$search_results_json = runCurlSearch( $searchHash, $limit, $offset);
		$search_array = (array)$search_results_json->results->hits;

		print_r( sizeof($search_array['hits']) . "\n");

		$offset = 0;
		while( sizeof($search_array['hits']) < $search_array['total'] ){
			print( "offest: " . $offset . PHP_EOL );
			$offset = $offset + 50;
			$search_results_json = runCurlSearch( $searchHash, $limit, $offset);
			$array2 = (array)$search_results_json->results->hits;

			$search_array =  addSearchArray( $search_array, $array2 );
		}

		foreach( $search_array['hits'] as $hit ){
			array_push( $transcript_ids, $hit->_id );
		}

		$index = $index + 1;
	}

	return $transcript_ids;
}

// retrieve the list of transcript ID's but with a given search hash instead of retrieving a new one
function retrieveIDs_from_hash( $search_hash ){

	$upper_limit = sizeof( $search_hash );
	$index = 0;

	while( $index < $upper_limit ){

		print( "index: " . $index . "\n" );
		if( isset($search_hash[0]) ){
			$searchHash = $search_hash[$index];
		}
		else{
			$searchHash = $search_hash;
		}

		$limit = 50;
		$offset = 0;

		$transcript_ids = [];

		$search_results_json = runCurlSearch( $searchHash, $limit, $offset);
		$search_array = (array)$search_results_json->results->hits;

		while( sizeof($search_array['hits']) < $search_array['total'] ){
			$offset = $offset + 50;
			$search_results_json = runCurlSearch( $searchHash, $limit, $offset);
			$array2 = (array)$search_results_json->results->hits;

			$search_array =  addSearchArray( $search_array, $array2 );
		}

		foreach( $search_array['hits'] as $hit ){
			array_push( $transcript_ids, $hit->_id );
		}
		$index = $index + 1;
	}

	return $transcript_ids;
}

// email a specified email address with message
function mailOut( $email, $subject, $message ){	
	$mail = new PHPMailer;

	$mail->SMTPDebug = 3;                               // Enable verbose debug output

	$mail->isSMTP();                                      // Set mailer to use SMTP
	$mail->Host = 'uksmtp.uky.edu';  // Specify main and backup SMTP servers
	$mail->SMTPAuth = false;                               // Enable SMTP authentication
	$mail->Username = '';                 // SMTP username
	$mail->Password = '';                           // SMTP password
	//$mail->SMTPSecure = 'TLS';                            // Enable TLS encryption, `ssl` also accepted
	$mail->Port = 25;                                    // TCP port to connect to

	$mail->setFrom('commtech@lsv.uky.edu', 'CommTV Mail Alerts');
	$mail->addAddress($email[0], '' .$email[1] . ' ' . $email[2] . '');     // Add a recipient
	$mail->addReplyTo('kirk.hardy@uky.edu', 'Questions');
	//$mail->addReplyTo('commtech@lsv.uky.edu', 'Questions');

	$mail->isHTML(true);                                  // Set email format to HTML

	$mail->Subject = $subject;
	$mail->Body    = $message;
	$mail->AltBody = $message;


	if(!$mail->send()) {
	    print( 'Message could not be sent.' );
	    print( 'Mailer Error: ' . $mail->ErrorInfo );
	} else {
	    print( 'Message has been sent to ' . $email[1] . ' ' . $email[2] );
	}
}

// comprehensive refresh of transcripts in a coding site
// this function will rerun the search used to created the coding site,
// find all transcripts associated with the transcript_id's,
// and add new id's to the site
function refreshCodingSite( $dbname ){

	$blue 	= "\033[34m";
	$green 	= "\033[32m";
	$Cyan 	="\033[36m";
	$Red 	= "\033[31m";
	$Purple = "\033[35m";
	$Brown 	= "\033[33m";
	$lCyan 	= "\033[36m";
	$Yellow = "\033[33m";
	$off 	= "\033[0m";

	$search_hash = retrieveSearchHash( $dbname ); // get the search associated with the site

	$upper_limit = sizeof( $search_hash );
	$index = 0;

	while( $index < $upper_limit ){

		print( "index: " . $index . "\n" );
		if( isset($search_hash[0]) ){
			$searchHash = $search_hash[$index];
		}
		else{
			$searchHash = $search_hash;
		}

		$search_array = hashToArray( $searchHash ); // convert stored hash to workable array
	
		$search_array = updateSearch( $search_array ); // update date_to to today's date

		$searchHash = arrayToHash( $search_array ); // convert date-changed array back into a hash

		addRawSearchHash( $dbname, $searchHash );	// place that search back in the DB

		$num_before = numTranscripts( $dbname ); // find out how many transcripts were in place prior to refresh

		$index = $index + 1;
	}
	
	// grab all id's returned by the search used to generate this coding site
	// (and so stored as a hash in the mySQL DB)
	$transcript_list = retrieveTranscriptIDList( $dbname );
	
	$index = 1;
	foreach( $transcript_list as $transcript_id ){
		print( $index."/".sizeof($transcript_list).$Yellow ." fetching transcript " .$off .$Cyan . $transcript_id . $off . "..." );
		$transcript_json_object = fetchSingleTranscript( $transcript_id ); // get the transcript
		print( $Purple . "  \t \t \040done" . $off . PHP_EOL );

		print( $Cyan ."inserting " . $off . $Yellow ."transcript " .$off .$Cyan .$transcript_id .$off .$Yellow ." has video: ". $off);
		insertSingleTranscript( $dbname, $transcript_json_object );			// add it if need be
		print( $Purple . " done" . $off . PHP_EOL );

		$index = $index + 1;
	}

	$num_after = numTranscripts( $dbname ); 
	if( $num_after > $num_before ){ 		// if we actually added transcripts, we need to alert coders via email

		$num_added = $num_after - $num_before;
		$emails = retrieveEmails( $dbname );
		foreach( $emails as $email ){

			if( $num_added > 1 ){
				$subject = $num_added.' new hits were added to '.$dbname.'.';
			}
			elseif( $num_added == 1 ){
				$subject = $num_added.' new hit was added to the '.explode('_',$dbname)[1].' study site';
			}	
			$message = '<b>'.date('l jS \of F Y h:i:s A').'</b>';
			$message .="<br>Hello. <br>Based on your stored search criteria, COMMTV has identified "; 
						if( $num_added > 1){
							$message .= $num_added;
						}
						elseif( $num_added == 1 ){
							$message .= $num_added;
						}

						$message .= " new results, added them 
						to the '".explode('_',$dbname)[1]."' study, and made them avaliable for coding in your To Do List.";
						
			if( $email[0] != '' ){
				mailOut( $email, $subject, $message );
			}
		}
	}
}

// given an array of transcripts, insert them in a singular SQL query 
// SQL VALUE statements max out at 1000 so this isn't all that useful 
function massAdd( $dbname, $transcripts ){
	
	$config = parse_ini_file( 'config.ini' );
	$servername = $config['servername'];
	$username = $config['username'];
	$password = $config['password'];

	$serverConnection = mysqli_connect( $servername, $username, $password, $dbname );
	if (mysqli_connect_errno()) {
	  echo "Failed to connect to MySQL: " .mysqli_connect_error();
	}

	foreach( $transcripts as $transcript_json_object ){
		$transformed_text = parseTranscriptText( $transcript_json_object );
		$transformed_text = mysqli_real_escape_string($serverConnection, $transformed_text);
		
		$id = $transcript_json_object->transcript->trans_id;
		$channel = $transcript_json_object->transcript->channel;
		$datetime = $transcript_json_object->transcript->datetime;
		$today = date("Y-m-d H:i:s");

		$green  = "\033[32m";
		$Red 	= "\033[31m";
		$off 	= "\033[0m";

		if( isset($transcript_json_object->transcript->video_location) ){
			$has_video = "1";
		}
		else{
			$has_video = "0";
		}


		$sql = "INSERT INTO transcript (trans_id, channel, flv, flv_rtmp, datetime, contents, created_at)
		VALUES ('$id', 
			'$channel', 
			'$has_video', 
			'array_7', 
			'$datetime', 
			'$transformed_text',
			'$today')
		ON DUPLICATE KEY UPDATE
		contents='$transformed_text', flv='$has_video', flv_rtmp='array_7', updated_at='$today'";

		if (!mysqli_query($serverConnection,$sql)) {
		  die('Error: ' .mysqli_error($serverConnection));
		}
	}
	
	mysqli_close($serverConnection);
}

// only refresh for entries since the search hash was last updated
function refreshNew( $dbname ){

	$blue 	= "\033[34m";
	$green 	= "\033[32m";
	$Cyan 	="\033[36m";
	$Red 	= "\033[31m";
	$Purple = "\033[35m";
	$Brown 	= "\033[33m";
	$lCyan 	= "\033[36m";
	$Yellow = "\033[33m";
	$off 	= "\033[0m";

	$search_hash = retrieveSearchHash( $dbname ); // get the search associated with the site

	$upper_limit = sizeof( $search_hash );
	$index = 0;

	$search_hash_changed = [];

	while( $index < $upper_limit ){

		print( "index: " . $index . "\n" );
		if( isset($search_hash[0]) ){
			$searchHash = $search_hash[$index];
		}
		else{
			$searchHash = $search_hash;
		}

		$search_array = hashToArray( $searchHash ); // convert stored hash to workable array
	
		$search_array = updateSearch( $search_array ); // update date_to to today's date
		$search_array = backUpdateSearch( $search_array ); 

		$searchHash = arrayToHash( $search_array ); // convert date-changed array back into a hash

		$num_before = numTranscripts( $dbname ); // find out how many transcripts were in place prior to refresh

		$index = $index + 1;
		array_push( $search_hash_changed, $searchHash );
	}

	$transcript_list = retrieveIDs_from_hash( $search_hash_changed );

	$index = 1;
	$transcripts = [];
	foreach( $transcript_list as $transcript_id ){
		print( $index."/".sizeof($transcript_list).$Yellow ." fetching transcript " .$off .$Cyan . $transcript_id . $off . "..." );
		$transcript_json_object = fetchSingleTranscript( $transcript_id ); // get the transcript
		print( $Purple . "  \t \t \040done" . $off . PHP_EOL );

		array_push( $transcripts, $transcript_json_object );

		$index = $index + 1;
	}
	if( sizeof($transcripts) > 999 ){
		$transcript_chunks = array_chunk($transcripts, 999);
		foreach( $transcript_chunks as $chunk ){
			massAdd( $dbname, $chunk );
		}
	}
	else {
		massAdd( $dbname, $transcripts );
    }

    $num_after = numTranscripts( $dbname ); 
	if( $num_after > $num_before ){ 		// if we actually added transcripts, we need to alert coders via email

		$num_added = $num_after - $num_before;
		$emails = retrieveEmails( $dbname );
		foreach( $emails as $email ){

			if( $num_added > 1 ){
				$subject = $num_added.' new hits were added to '.$dbname.'.';
			}
			elseif( $num_added == 1 ){
				$subject = $num_added.' new hit was added to the '.explode('_',$dbname)[1].' study site';
			}	
			$message = '<b>'.date('l jS \of F Y h:i:s A').'</b>';
			$message .="<br>Hello. <br>Based on your stored search criteria, COMMTV has identified "; 
						if( $num_added > 1){
							$message .= $num_added;
						}
						elseif( $num_added == 1 ){
							$message .= $num_added;
						}

						$message .= " new results, added them 
						to the '".explode('_',$dbname)[1]."' study, and made them avaliable for coding in your To Do List.";
						
			if( $email[0] != '' ){
				mailOut( $email, $subject, $message );
			}
		}
	}
}

?>
