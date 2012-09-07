<?php

	require "lib/aidSQL/class/parser/Url.class.php";

	try{

		$url	=	new \aidSQL\parser\Url($_SERVER["argv"][1]);

		echo "SCHEME:\t\t\t".var_export($url->getScheme(),TRUE)."\n";
		echo "HOST:\t\t\t".var_export($url->getHost(),TRUE)."\n";
		echo "PATH:\t\t\t".var_export($url->getPath(),TRUE)."\n";
		echo "PAGE:\t\t\t".var_export($url->getPage(),TRUE)."\n";
		echo "getQueryAsString:\t\t\t".var_export($url->getQueryAsString(),TRUE)."\n";
		echo "getQueryAsArray:\t\t\t".var_export($url->getQueryAsArray(),TRUE)."\n";
		echo "getUrlAsString:\t\t\t".var_export($url->getUrlAsString(),TRUE)."\n";
		echo "getUrlAsString(FALSE)\t\t\t:".var_export($url->getUrlAsString(FALSE),TRUE)."\n";


	}catch(\Exception $e){

		echo $e->getMessage()."\n";

	}

?>
