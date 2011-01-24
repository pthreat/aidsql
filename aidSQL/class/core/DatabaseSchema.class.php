<?php

	namespace aidSQL\core {

		class DatabaseSchema {

			private	$_dbVersion		=	NULL;
			private	$_dbName			=	NULL;
			private	$_dbPassword	=	NULL;
			private	$_dbUser			=	NULL;
			private	$_dbDataDir		=	NULL;
			private	$_tables			=	array();

			public function addTable($table,Array $fields){

				$this->_tables[$table]	=	$fields;

			}

			public function getTables(){

				if(sizeof($this->_tables)){
					return array_keys($this->_tables);
				}

				return FALSE;

			}

			public function getSchema(){
				return $this->_tables;
			}

			public function setTotalRegisters($total){
				$this->_tables[$table]["total"]	=	$total;
			}

			public function setDbUser($dbUser){
				$this->_dbUser	=	$dbUser;
			}

			public function setDbName($dbName){
				$this->_dbName	=	$dbName;
			}

			public function setDbPassword($dbPassword){
				$this->_dbPassword	=	$dbPassword;
			}

			public function setDbVersion($dbVersion){
				$this->_dbVersion	=	$dbVersion;	
			}

			public function getDbVersion(){
				return $this->_dbVersion;
			}

			public function setDbDataDir($dataDir){

				$this->_dbDataDir	=	$dataDir;

			}

			public function getXML(){

            $dom		=	new DOMDocument('1.0','utf-8');
            $db		=	$dom->createElement("database");

				$db->appendChild($dom->createElement("name",$this->_dbName));
				$db->appendChild($dom->createElement("version",$this->_dbVersion));
				$db->appendChild($dom->createElement("datadir",$this->_dbDataDir));

				$tables	=	$dom->createElement("tables");

				foreach($this->_tables as $tName=>$columns){

					$table	=	$dom->createElement("table");
					$table->appendChild($dom->createElement("name",$tName));

					$domCols	=	$dom->createElement("columns");

					foreach($columns as $column){
						$domCols->appendChild($dom->createElement("name",$column));
					}
					
					$table->appendChild($domCols);

					$tables->appendChild($table);

				}

				$db->appendChild($tables);
				$dom->appendChild($db);

           	return $dom->getXML(); 

			}

		}

	}

?>
