<?php

	//Interfaces
	require_once "interface/Parser.interface.php";

	//Parsers
	require_once "class/parser/Generic.parser.php";
	require_once "class/parser/Dummy.parser.php";

	$content="<html><head><title>Testing</title><body><h1>Dumb test!</h1></body></html>";

	$dumb = new \aidSQL\parser\DummyParser($content);
	var_dump($dumb->getResult());

?>
