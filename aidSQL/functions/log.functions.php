<?php

	//This file will contain HTML and XML logging functions

	function makeXML(\aidSQL\plugin\sqli\InjectionPlugin &$plugin,Array &$schemas){

		$dom			=	new \DomDocument('1.0','utf-8');
		$main			=	$dom->createElement("aidSQL");

		$url			=	$plugin->getHttpAdapter()->getUrl();
		$affected	=	$plugin->getAffectedVariable();

		$domain		=	$dom->createElement("host",$url->getHost());
		$date			=	$dom->createElement("date",date("Y-m-d H:i:s"));

		$main->appendChild($domain);
		$main->appendChild($date);

		$injection	=	$dom->createElement("sqli-details");

		$injection->appendChild($dom->createElement("vulnlink",$url->getUrlAsString(FALSE)));

		$requestVariables	=	$url->getQueryAsArray();

		$params				=	$dom->createElement("parameters");
		$injection->appendChild($dom->createElement("injection",sprintf("%s",$affected["injection"])));

		foreach($requestVariables as $var=>$value){

			$params->appendChild($dom->createElement("param",$var));
			$vulnerable	=	($var == $affected["variable"])	?	1	:	0;
			$params->appendChild($dom->createElement("vulnerable",$vulnerable));

		}

		$injection->appendChild($params);

		$pluginDom		=	$dom->createElement("plugin-details");
		
		$pluginDom->appendChild($dom->createElement("plugin",$plugin->getPluginName()));
		$pluginDom->appendChild($dom->createElement("author",$plugin->getPluginAuthor()));
		$pluginDom->appendChild($dom->createElement("method",$affected["method"]));

		$injection->appendChild($pluginDom);

		$main->appendChild($injection);

		$domSchemas	=	$dom->createElement("schemas");

		foreach($schemas as $schema){

			$db		=	$dom->createElement("database");

			$db->appendChild($dom->createElement("name",$schema->getDbName()));
			$db->appendChild($dom->createElement("version",$schema->getDBVersion()));
			$db->appendChild($dom->createElement("datadir",$schema->getDbDataDir()));

			$tables			=	$dom->createElement("tables");
			$schemaTables	=	$schema->getTables();

			foreach($schemaTables as $tName=>$columns){

				$table	=	$dom->createElement("table");
				$table->appendChild($dom->createElement("name",$tName));

				if(sizeof($columns)){

					$domCols	=	$dom->createElement("columns");

					foreach($columns as $column){
						$domCols->appendChild($dom->createElement("name",$column));
					}

					$table->appendChild($domCols);

				}

				$tables->appendChild($table);

			}

			$db->appendChild($tables);
			$domSchemas->appendChild($db);

		}

		$main->appendChild($domSchemas);
		$dom->appendChild($main);

  		return $dom->saveXML(); 

	}


?>
