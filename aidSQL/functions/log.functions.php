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
				$table->setAttribute("name",$tName);

				foreach($columns["description"] as $descName=>$descValue){

					$table->setAttribute($descName,$descValue);

				}

				if(sizeof($columns["fields"])){

					foreach($columns["fields"] as $name=>$value){

						$domCol	=	$dom->createElement("column");
						$domCol->setAttribute("name",$name);

						foreach($value as $nodeName=>$nodeValue){

							if(is_array($nodeValue)){

								$tmpNode	=	$dom->createElement($nodeName);

								foreach($nodeValue as $value){

									$tmpNode->appendChild($dom->createElement($value,1));

								}	
								
							}else{

								$tmpNode	=	$dom->createElement($nodeName,$nodeValue);

							}

							$domCol->appendChild($tmpNode);

						}

						$table->appendChild($domCol);

					}


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


	function makeLog(\aidSQL\plugin\sqli\InjectionPlugin &$plugin,Array &$schemas,\aidSQL\core\Logger &$log){

		$url					=	$plugin->getHttpAdapter()->getUrl();

		$pluginName			=	$plugin->getPluginName();
		$pluginAuthor		=	$plugin->getPluginAuthor();
		$affected			=	$plugin->getAffectedVariable();
		$pluginMethod		=	$affected["method"];
		$domain				=	$url->getHost();
		$link					=	$url->getUrlAsString(FALSE);
		$requestVariables	=	implode(',',$url->getQueryAsArray());
		$injection			=	sprintf("%s",$affected["injection"]);

		foreach($schemas as $schema){

			$log->log("------------------------------------------------",0,"white",TRUE);
			$log->log("SCHEMA ".$schema->getDbName(),0,"white",TRUE);
			$log->log("------------------------------------------------",0,"white",TRUE);
			$log->log("VERSION : ".$schema->getDbVersion(),0,"white",TRUE);
			$log->log("DATADIR : ".$schema->getDbDataDir(),0,"white",TRUE);

			$schemaTables	=	$schema->getTables();

			foreach($schemaTables as $tName=>$columns){

				$log->log("TABLE $tName",0,"white",TRUE);		
				$log->log("---------------------",0,"white",TRUE);		

				foreach($columns["description"] as $descName=>$descValue){

					$log->log("$descName\t\t:\t$descValue",0,"white",TRUE);

				}

				$log->log("COLUMNS",0,"white",TRUE);
				$log->log("---------------------",0,"white",TRUE);		

				if(!sizeof($columns["fields"])){
					continue;
				}

				foreach($columns["fields"] as $name=>$value){

					$log->log("NAME\t\t:\t$name",0,"white",TRUE);

					foreach($value as $nodeName=>$nodeValue){
						
						if(is_array($nodeValue)){

							$log->log("$nodeName\t\t:\t".implode(',',$nodeValue),0,"white",TRUE);

						}else{

							$log->log("$nodeName\t\t:\t$name",0,"white",TRUE);

						}

					}

				}

			}

		}

	}


?>
