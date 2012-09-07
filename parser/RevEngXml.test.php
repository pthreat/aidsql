<?php

	require	"lib/aidSQL/class/core/File.class.php";
	require	"lib/aidSQL/class/parser/RevEngXml.class.php";

	try{

		$file	=	new \aidSQL\core\File($_SERVER["argv"][1]);
		$xml	=	new \aidSQL\parser\RevEngXml($file);
		var_dump($xml->getPlugin());

		foreach($xml->getSchemasAsArray() as $schema){
			foreach($xml->getSchemaTablesAsArray($schema["name"]) as $table){
				echo "TABLE $table[name]\n";
				$columns	=	$xml->getTableColumnsAsArray($schema["name"],$table["name"]);
				var_dump($columns);
			}
		}

	}catch(\Exception $e){

		echo $e->getMessage()."\n";

	}

	

?>
