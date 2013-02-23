<?php

require_once "../src/Ajap/Exception.php";
require_once "../src/Ajap/Annotations.php";
require_once "../src/Ajap/utils.php";
require_once "../src/Ajap/CodeTemplate.php";
//require_once "../src/Ajap/JSWriter.php";
require_once "../src/Ajap/ServiceWriter.php";

require_once "./data/services/folder1/Simple.php";

header( "Content-Type: text/plain");

$options = array(
	"path" => __DIR__ . "/data/services/folder1",
);

$serviceWriter = new AjapServiceWriter( AjapClass::get( "Simple" ), $options );

echo "\n//--------------------------- RAW \n\n";

$raw = $serviceWriter->parse();
//var_export( $raw );

echo "\n//--------------------------- SERVICE \n\n";

$jsWriter =& ajap_getWriter( "JS", __DIR__ . DIRECTORY_SEPARATOR . "cache" );

echo ajap_beautifyJS( $jsWriter->services( array(
	"Simple" => $raw[ "service" ],
) ), true );

echo "\n//--------------------------- DYNAMIC SOURCE \n\n";

$dynWriter =& ajap_getWriter( "Dynamic", __DIR__ . DIRECTORY_SEPARATOR . "cache" );

echo $dynWriter->main( $raw );

