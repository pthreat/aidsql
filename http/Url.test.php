<?php

	require "lib/aidSQL/class/http/Url.class.php";

	try{

		$url	=	new \aidSQL\core\Url($_SERVER["argv"][1]);

		var_dump($url->getScheme());
		var_dump($url->getHost());
		var_dump($url->getPage());
		var_dump($url->getQueryAsString());
		var_dump($url->getQueryAsArray());
		$url->setEqualityOperator(":");
		$url->setSeparator("|");
		var_dump($url->addRequestVariable("z",20));
		var_dump($url->getUrlAsString());
		var_dump($url->getUrlAsString(FALSE));


	}catch(\Exception $e){

		echo $e->getMessage()."\n";

	}

?>
