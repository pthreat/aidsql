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

				return $this->_tables;

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

			public function getDbUser($dbUser){
				return $this->_dbUser;
			}

			public function setDbName($dbName){
				$this->_dbName	=	$dbName;
			}

			public function getDbName(){
				return $this->_dbName;
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

			public function getDbDataDir(){
				return $this->_dbDataDir;
			}

		}

	}

?>
