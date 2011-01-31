<?php
	namespace aidSQL;

	if(empty($_SERVER["argv"][1])){
		die("Usage: ".basename($_SERVER["argv"][0])." <url>\n");
	}

	set_include_path("lib/aidSQL/".PATH_SEPARATOR.get_include_path());


	require "interface/Log.interface.php";
	require "interface/http/Adapter.interface.php";
	require "class/http/Url.class.php";
	require "class/http/adapter/Ecurl.class.php";
	require "class/core/Dom.class.php";
	require "class/log/StdLog.class.php";

	$log	=	new log\StdLog();
	$log->setEcho(TRUE);

	try{

		$url	=	new core\Url($_SERVER["argv"][1]);
		$http	=	new http\adapter\Ecurl($url);
		$http->setLog($log);

		$dom	=	new core\Dom($http->fetch());
		$forms	=	$dom->fetchForms();

		foreach($forms as $formName=>$form){
			$form	=	$form[key($form)];
			$http->setMethod($form["attributes"]["method"]);
			var_dump($form);
				

		}

	}catch (\Exception $e){

		$log->log($e->getMessage(),1,"red");

	}

?>
