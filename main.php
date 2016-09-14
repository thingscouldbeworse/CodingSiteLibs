<?php

require( 'libs/CoderSitefuncs.php' );

$dbname = 'commtv_dummy';

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

$search_results_json = runCurlSearch( $search_hash, 50, 0 );

print_r( $search_results_json->results->hits );

?>
