<?php
require( 'libs/CoderSitefuncs.php' );

// this file is run through a cron-job at 3 AM each day to refresh each coding site specified in 'commtv_refresh'

refreshNew( 'commtv_testing', 1 );


?>
