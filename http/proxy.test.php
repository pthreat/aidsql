<?php

	set_include_path("lib/aidSQL/");

	require "class/core/Url.class.php";
	require "class/core/Logger.class.php";
	require "class/core/File.class.php";
	require "interface/http/Adapter.interface.php";
	require "class/http/adapter/Ecurl.class.php";
	require "class/http/ProxyHandler.class.php";


	$log		=	new \aidSQL\core\Logger();
	$log->setEcho(TRUE);

	$adapter			=	new \aidSQL\http\Adapter\Ecurl();
	$adapter->setLog($log);
	$proxyHandler	=	new \aidSQL\http\ProxyHandler($adapter,$log);
	$proxyHandler->checkProxyList($_SERVER["argv"][1]);

	var_dump($proxyHandler->getValidProxy());

?>
