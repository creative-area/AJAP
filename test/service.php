<?php
set_include_path( get_include_path() . PATH_SEPARATOR . realpath( "../src" ) );

require_once( "Ajap.php" );

$ajap = new AjapEngine(array(
	"path" => realpath( "./data/services/folder1" ) . PATH_SEPARATOR . realpath( "./data/services/folder2" )
));

$ajap->handleRequest();
