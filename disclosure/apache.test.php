<?php
	namespace aidSQL;

	if(empty($_SERVER["argv"][1])){
		die("Usage: ".basename($_SERVER["argv"][0])." <ip>\n");
	}

	set_include_path("./lib/aidSQL/".PATH_SEPARATOR.get_include_path());

	if(empty($_SERVER["argv"][1])){
		die("Usage ".basename($_SERVER["argv"][0]." <host>"));
	}

	require "interface/Log.interface.php";
	require "interface/plugin/Info.interface.php";
	require "interface/http/Adapter.interface.php";
	require	"class/core/File.class.php";
	require "class/http/adapter/Ecurl.class.php";
	require "class/parser/Nmap.class.php";
	require "class/log/StdLog.class.php";
	require "class/plugin/info/Apache.class.php";

	$log	=	new log\StdLog();
	$log->setEcho(TRUE);

	try{

		$http	=	new http\adapter\Ecurl($_SERVER["argv"][1]);
		$apache	=	new plugin\info\Apache($http,$log);
		var_dump($apache->getInfo());

	}catch (\Exception $e){

		$log->log($e->getMessage(),1,"red");

	}

?>
