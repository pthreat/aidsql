<?php

	namespace aidSQL\parser {

		class RevEngXml{

			private	$_xmlFile		=	NULL;
			private	$_schemas		=	NULL;

			public function __construct(\aidSQL\core\File $file){

				$this->_xmlFile		=	$file;

			}

			private function load(){

				$dom	=	new \DomDocument("1.0");
				$dom->load($this->_xmlFile->getFile());

				return $dom;

			}

			public function getHost(){

				$dom	=	$this->load();
				return $dom->getElementsByTagName("host")->item(0)->nodeValue;
				
			}

			public function getVulnerableLink(){

				$dom	=	$this->load();
				$link	=	$dom->getElementsByTagName("vulnlink")->item(0)->nodeValue;

				return $link;

			}

			/**
			*Searches inside a given section of the XML file ($tag) for a given fieldName if given
			*/

			private function search($tag,$fieldName=NULL,$getNodeNameAndValueAsArray=FALSE){

				if(!is_a($tag,'DomNodeList')){

					$dom				=	$this->load();
					$domNodeList	=	$dom->getElementsByTagName($tag)->item(0)->childNodes;

				}else{

					$domNodeList	=	$tag;

				}

				if($domNodeList->length==0){
					return NULL;
				}

				if(!is_null($fieldName)){

					for($i=0;$i<$domNodeList->length;$i++){

						if($domNodeList->item($i)->nodeName==$fieldName){

							$childNodes	=	$domNodeList->item($i)->childNodes;

							if(sizeof($childNodes)){

								if($getNodeNameAndValueAsArray){

									$nodes	=	Array();

									for($x=0;$x<$childNodes->length;$x++){
										
										$nodes[$childNodes->item($x)->nodeName]	=	$childNodes->item($x)->nodeValue;

									}
								
									return $nodes;

								}else{

									return $domNodeList->item($i)->childNodes;

								}

							}

							return $domNodeList->item($i)->nodeValue;

						}

					}

					return NULL;

				}

				return $domNodeList;

			}

			public function getFieldCount(){

				return $this->search("injection","index");	
				return NULL;

			}

			public function getPlugin(){

				$details	=	$this->search("aidSQL","sqli-details");
				$plugin	=	$this->search($details,"plugin-details",TRUE);

				return $plugin;

			}

			private function getElementAttributesAsArray(\DomElement $element,$attributeName=NULL){

				$attrArray	=	Array();

				$attributes	=	$element->attributes;

				if(!$attributes->length){
					return $attrArray;
				}

				for($c=0;$c<$attributes->length;$c++){

					if(!is_null($attributeName)){

						if($attributes->item($c)->name!==$attributeName){
							continue;
						}

					}

					$attrArray[$attributes->item($c)->name]	=	$attributes->item($c)->value;

				}

				return $attrArray;

			}

			public function getSchemasAsArray(){

				$schemas			=	$this->search("aidSQL","schemas");
				$schemasArray	=	Array();

				for($i=0;$i<$schemas->length;$i++){

					$schemasArray[]	=	$this->getElementAttributesAsArray($schemas->item($i));

				}

				return $schemasArray;	

			}


			public function isValidSchema($schemaName){

				$schemas			=	$this->getSchemasAsArray();
				$validSchema	=	FALSE;

				foreach($schemas as $schema){
					if($schema["name"]==$schemaName){
						$validSchema	=	TRUE;
					}

				}

				return $validSchema;

			}

			public function isValidTableName($schemaName,$tableName){

				$tables	=	$this->getSchemaTablesAsArray($schemaName);

				$valid	=	FALSE;

				foreach($tables as $table){

					if($table["name"]==$tableName){
						$valid	=	TRUE;
						break;
					}

				}

				return $valid;

			}

			private function getChildNodesAsArray(\DomElement $element){

				$domChilds	=	$element->childNodes;
				$childArray	=	Array();
				
				for($i=0;$i<$domChilds->length;$i++){

					$childArray[$domChilds->item($i)->nodeName]	=	$domChilds->item($i)->nodeValue;
			
				}

				return $childArray;

			}

			public function getSchemaTablesAsArray($schemaName,$tableRegex=NULL){

				if(!$this->isValidSchema($schemaName)){

					throw(new Exception("Invalid schema name $schemaName"));

				}

				$schemas	=	$this->search("aidSQL","schemas");
				
				$tables	=	Array();

				for($i=0;$i<$schemas->length;$i++){

					$name	=	$this->getElementAttributesAsArray($schemas->item($i),"name");
					$name	=	$name["name"];

					if($schemaName!==$name){
						continue;
					}


					if(!$schemas->item($i)->childNodes->length){
						throw(new \Exception("Schema $name has no tables!"));
					}

					$domTables	=	$this->search($schemas->item($i)->childNodes,"tables");

					for($c=0;$c<$domTables->length;$c++){

						$attributes	=	$this->getElementAttributesAsArray($domTables->item($c));

						if(!is_null($tableRegex)){

							if(!preg_match('#'.strtolower($tableRegex).'#',strtolower($attributes["name"]))){
								continue;
							}

						}

						$tables[$c]	=	$attributes;

					}

				}

				return $tables;

			}

			public function getTableColumnsAsArray($schemaName,$tableName,$columnRegex=NULL){

				if(!$this->isValidSchema($schemaName)){

					throw(new Exception("Invalid schema name $schemaName"));

				}

				if(!$this->isValidTableName($schemaName,$tableName)){

					throw(new Exception("Invalid table name $tableName not found in schema $schemaName"));

				}

				$schemas	=	$this->search("aidSQL","schemas");

				for($i=0;$i<$schemas->length;$i++){

					$name	=	$this->getElementAttributesAsArray($schemas->item($i),"name");
					$name	=	$name["name"];

					if($schemaName!==$name){
						continue;
					}

					if(!$schemas->item($i)->childNodes->length){
						throw(new \Exception("Schema $name has no tables!"));
					}

					$domTables	=	$this->search($schemas->item($i)->childNodes,"tables");

					for($c=0;$c<$domTables->length;$c++){

						$attributes	=	$this->getElementAttributesAsArray($domTables->item($c));

						if($attributes["name"]==$tableName){

							$columns		=	Array();
							$domColumns	=	$domTables->item($c)->childNodes;

							for($x=0;$x<$domColumns->length;$x++){

								$columnName					=	$domColumns->item($x)->getAttribute("name");
								$columns[$columnName]	=	$this->getChildNodesAsArray($domColumns->item($i));

							}

							return $columns;

						}

					}

				}

			}


		}

	}

?>
