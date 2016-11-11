<?php

require( 'libs/rewriteSearchFuncs.php' );
/*
$hashes = retrieveSearchHashes( 'commtv_zika' );

$total_hits = retrieveTranscriptList( $hashes );

$total_hits = consolidateIDList( $total_hits );
print_r( $total_hits );

$transcripts = retrieveAllTranscripts( $total_hits );

massAdd('commtv_zika', $transcripts ); 
*/

refreshNew2( 'commtv_zika' );


?>
