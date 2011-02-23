<?php

	if(empty($_SERVER["argv"][1])){
		die("Usage: ".basename($_SERVER["argv"][0])." <ip>\n");
	}

	set_include_path("lib/aidSQL/");
	require_once "interface/http/Adapter.interface.php";
	require_once "class/core/Logger.class.php";
	require_once "class/core/Url.class.php";
	require_once "class/http/adapter/Ecurl.class.php";
	require_once "class/http/webservice/bing/Bing.class.php";

	$log	=	new \aidSQL\core\Logger();
	$log->setEcho(TRUE);

	try{

		$http	=	new \aidSQL\http\Adapter\Ecurl();
		$bing	=	new \aidSQL\http\webservice\Bing($http,$log);
		$bing->setLog($log);
		var_dump($bing->getHosts($_SERVER["argv"][1]));

	}catch (Exception $e){

		$log->log($e->getMessage(),1,"red");

	}

?>
