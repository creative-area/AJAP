<?php

require_once "../src/Ajap/Exception.php";
require_once "../src/Ajap/Annotations.php";
require_once "../src/Ajap/utils.php";
require_once "../src/Ajap/JSWriter.php";
require_once "../src/Ajap/ServiceWriter.php";

require_once "./data/services/folder1/Simple.php";

$writer = new AjapServiceWriter( AjapClass::get( "Simple" ) );

$result = $writer->render();

header( "Content-Type: text/plain");

echo
	"// TS: " . $result[ "ts" ] . "\n\n"
	. "// TIME: " . $result[ "time" ] . "\n\n"
	. "// DYNAMIC\n\n" . $result[ "dynamic" ] . "\n"
	. "// STATIC\n\n" . $result[ "static" ];
