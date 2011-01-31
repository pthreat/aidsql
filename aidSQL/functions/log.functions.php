<?php

	//This file will contain HTML and XML logging functions

	function makeXML(\aidSQL\plugin\sqli\InjectionPlugin &$plugin,Array &$schemas){

		$dom			=	new \DomDocument('1.0','utf-8');
		$main			=	$dom->createElement("aidSQL");

		$url			=	$plugin->getHttpAdapter()->getUrl();

		$domain		=	$dom->createElement("host",$url->getHost());
		$date			=	$dom->createElement("date",date("Y-m-d H:i:s"));

		$main->appendChild($domain);
		$main->appendChild($date);

		$sqliDetails			=	$dom->createElement("sqli-details");
		$sqliDetails->appendChild($dom->createElement("vulnlink",$url->getUrlAsString(FALSE)));

		$domInjection			=	$dom->createElement("injection");
		$pluginDom				=	$dom->createElement("plugin-details");
		$pluginDom->appendChild($dom->createElement("plugin",$plugin->getPluginName()));
		$pluginDom->appendChild($dom->createElement("author",$plugin->getPluginAuthor()));

		$sqliDetails->appendChild($pluginDom);

		$injectionParameters	=	$plugin->getInjectionParameters();

		foreach($injectionParameters as $iKey=>$iVal){

			if(is_array($iVal)){

				$element	=	$dom->createElement($iKey);

				$i	=	0;

				foreach($iVal as $key=>$val){

					$key			=	is_int($key)	?	"value_".$i++	:	$key;
					$domValue	=	$dom->createElement($key,$val);
					$element->appendChild($domValue);

				}

				$domInjection->appendChild($element);

			}else{

				$domInjection->appendChild($dom->createElement($iKey,$iVal));

			}

		}

		$sqliDetails->appendChild($domInjection);
		$main->appendChild($sqliDetails);

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

		$url			=	$plugin->getHttpAdapter()->getUrl();
		$affected	=	$plugin->getAffectedVariable();

		$txtLog		=	NULL;
		$txtLog	.=	"HOST ".$url->getHost()."\n";
		$txtLog	.=	"------------------------------------\n";
		$txtLog	.=	"PLUGIN NAME\t\t:\t".$plugin->getPluginName()."\n";
		$txtLog	.=	"PLUGIN AUTHOR\t\t:\t".$plugin->getPluginAuthor()."\n";
		$txtLog	.=	"PLUGIN METHOD\t\t:\t".$affected["method"]."\n";
		
		$link					=	$url->getUrlAsString(FALSE);
		$requestVariables	=	$url->getQueryAsArray();

		$reqVars				=	array();
		foreach($requestVariables as $var=>$value){

			$reqVars[]	=	$var;

		}

		$txtLog	.=	"AFFECTED VARIABLE\t:\t".$affected["variable"]."\n";
		$txtLog	.=	"REQUEST VARIABLES\t:\t".implode(',',$reqVars)."\n";
		$txtLog	.=	"INJECTION\t\t:\t".sprintf("%s",$affected["injection"])."\n";
		$txtLog	.=	"VULNERABLE LINK\t\t:\t".$url->getUrlAsString()."\n";

		foreach($schemas as $schema){

			$txtLog	.=	"\n------------------------------------------------\n";
			$txtLog	.=	"SCHEMA ".$schema->getDbName()."\n";
			$txtLog	.=	"------------------------------------------------\n";
			$txtLog	.=	"VERSION : ".$schema->getDbVersion()."\n";
			$txtLog	.=	"DATADIR : ".$schema->getDbDataDir()."\n";

			$schemaTables	=	$schema->getTables();

			foreach($schemaTables as $tName=>$columns){

				$txtLog	.=	"\nTABLE $tName\n";
				$txtLog	.=	"---------------------\n";

				foreach($columns["description"] as $descName=>$descValue){

					$txtLog	.=	"$descName\t\t:\t$descValue\n";

				}

				$txtLog	.=	"\nCOLUMNS\n";
				$txtLog	.=	"---------------------\n";

				if(!sizeof($columns["fields"])){
					continue;
				}

				foreach($columns["fields"] as $name=>$value){

					$txtLog	.=	"NAME\t\t:\t$name\n";

					foreach($value as $nodeName=>$nodeValue){
						
						if(is_array($nodeValue)){

							$txtLog	.=	"\t\t$nodeName => ".implode(',',$nodeValue)."\n";

						}else{

							$txtLog	.=	"\t\t$nodeName\t\t$nodeValue\n";

						}

					}

				}

			}

		}

		return $txtLog;

	}


?>
