<?php

	namespace aidSQL\plugin\sqli {

		abstract class InjectionPlugin implements InjectionPluginInterface {

			protected	$_logger						=	NULL;
			protected 	$_httpAdapter				=	NULL;
			protected	$_verbose					=	FALSE;
			private	 	$_parser						=	NULL;				//This is the parser youll set in your child class 
																					//to analyze query results.

			protected	$_queryBuilder				=	NULL;				//Query building object
			protected	$_config						=	NULL;				//Configuration parameters
			private		$_injectionMethodString	=	"injection";	//String that indicates the prefix of injection methods
			private		$_injectionMethods		=	array();			//Contains all injection methods
			private		$_queryCount				=	array(
																		"someInjectionMethod"=>0	//Query count per injection method
																);	
			private		$_totalQueries				=	0;					//Total amount of queries
			protected	$_lastQuery					=	NULL;				//Contains the last executed query
			private		$_queryResult				=	NULL;				//Contains the lastQuery Result
																

			//Injection related stuff

			protected	$_injection					=	array();			//Contains all detected injection parameters
			protected 	$_injectionAttempts		=	40;
			private		$_lastInjectionMethod	=	NULL;				//Contains the last called injection method
			private		$_affectedVariable		=	array();			//Affected URL Variable, plus, the working injection
			protected	$_payload					=	NULL;				//This is just purely informational for now and contains
																					//The given escape sequence that was used on the field
																					//to inject for instance ',%') etc

			//Shell related stuff
			protected	$_shellCode					=	NULL;
			protected	$_shellFileName			=	NULL;
			private		$_schemas					=	array();	//An array containing DatabaseSchemas

			public final function __construct(\aidSQL\http\Adapter $adapter,Array $config,\aidSQL\core\Logger &$log=NULL){

				if(isset($config["all"]["injection-attempts"])){
					$this->setInjectionAttempts($config["all"]["injection-attempts"]);
				}

				$this->getInjectionMethods();

				if(!sizeof($this->_injectionMethods)){
					throw (new \Exception("No injection methods were found in this plugin!"));
				}

				$url						=	$adapter->getUrl();
				$requestVariables		=	$url->getQueryAsArray();

				$this->setConfig($config);

				if(sizeof($requestVariables)){ //url's that have query variables on them ?a=1&b=5, etc

					//Order Variables in the request like you want

					if(array_key_exists("numeric-only",$config)){

						$requestVariables	=	$this->separateRequestVariablesByType($requestVariables,"numeric");
						$url->addRequestVariables($requestVariables);

					}elseif(array_key_exists("strings-only",$config)){

						$requestVariables	=	$this->separateRequestVariablesByType($requestVariables,"string");
						$url->addRequestVariables($requestVariables);

					}elseif(array_key_exists("numeric-first",$config)){

						$requestVariables	=	$this->separateRequestVariablesByType($requestVariables,"numeric-first");
						$url->addRequestVariables($requestVariables);
						
					}elseif(array_key_exists("strings-first",$config)){

						$requestVariables	=	$this->separateRequestVariablesByType($requestVariables,"strings-first");
						$url->addRequestVariables($requestVariables);

					}


				}	//else we are probably dealing with a mod_rewritten url

				$adapter->setUrl($url);

				$this->setHttpAdapter($adapter);

				if(!is_null($log)){
					$this->setLog($log);
				}

			}

			public function setInjectionParameters(Array $parameters){

				$this->_injection	=	($parameters);

			}

			public function getInjectionParameters(){

				return $this->_injection;

			}

			public function setQueryBuilder(\aidSQL\db\QueryBuilderInterface &$queryBuilder){

				$this->_queryBuilder	=	$queryBuilder;

			}

			public function getPluginAuthor(){

         	$constant   =  "static::PLUGIN_AUTHOR";

				if(defined($constant)){

					return constant($constant);

				}

				return "UNKNOWN";

			}

			public function getPluginName(){

         	$constant   =  "static::PLUGIN_NAME";

				if(defined($constant)){

					return constant($constant);

				}

				return "UNKNOWN";

			}

			protected function addSchema(\aidSQL\core\DatabaseSchema $dbSchema){

				$this->_schemas[]	=	$dbSchema;

			}

			public function isVulnerable(){

				foreach($this->_injectionMethods as $injectionMethod){

					$this->_lastInjectionMethod	=	$injectionMethod;

					$results	=	$this->$injectionMethod();
	
					if($results){

						return TRUE;

					}

				}

			}

			public function getLastInjectionMethod(){
				return $this->_lastInjectionMethod;
			}

			public function getTotalQueryCount(){
				return $this->_queryCount;
			}

			public function setShellCode($shellCode=NULL){

				if(!empty($shellCode)){

					$this->_shellCode	=	\String::hexEncode($shellCode);
					$this->log("Set shell code to $shellCode",0,"light_cyan");
					return TRUE;
				}

				$this->log("Warning! Provided empty shell code",2,"yellow");

				return FALSE;

			}

			public function setShellName($fileName=NULL){

				if(empty($fileName)){
					$fileName	=	md5(rand(0,time())).".php";	
					$this->log("Warning, assumed random shell name $fileName, use --shell-name to set a shell name of your own!",2,"yellow");
				}

				$this->log("Set Shell name : $fileName",0,"light_cyan");

				$this->_shellFileName	=	$fileName;

			}

			public function getShellName(){

				return $this->_shellFileName;

			}

			public function setInjectionAttempts($int=0){

				if(!(int)$int||$int<=0){

					throw(new \Exception("Injection attempts has to be greater than 0"));

				}

				$this->_injectionAttempts	=	$int;

			}

			public function getInjectionAttempts(){

				return $this->_injectionAttempts;

			}

			public function setConfig(Array $config){
				$this->_config=$config;
			}

			public function setLog(\aidSQL\core\Logger &$log){
				$this->_logger = $log;
			}

			protected function log($msg = NULL,$color="white",$type="0",$toFile=FALSE){

				if(!(isset($this->_config["all"]["verbose"])&&(bool)$this->_config["all"]["verbose"])){
					echo ".";
					return;
				}

				$logToFile			=	(isset($this->_config["log-all"]))	?	TRUE	:	$toFile;

				if(!is_null($this->_logger)){

					$this->_logger->setPrepend("[".get_class($this)."]");
					$this->_logger->log($msg,$color,$type,$logToFile);
					return TRUE;

				}

				return FALSE;

			}

			public function getInjectionMethods($nocache=FALSE){

				if(sizeof($this->_injectionMethods)){
					return $this->_injectionMethods;
				}

				$methods				=	get_class_methods($this);
				$injectionMethods	=	array();

				foreach($methods as $method){

					if(substr($method,0,strlen($this->_injectionMethodString))==$this->_injectionMethodString){

						$injectionMethods[]	=	$method;

					}

				}

				$this->_injectionMethods	=	$injectionMethods;
						
			}

			protected function isIgnoredRequestVariable($requestVariable,$requestVariableValue=NULL){

				if(!array_key_exists("ignore-variables",$this->_config["all"])){
					return FALSE;
				}

				$ignore	=	explode(',',$this->_config["all"]["ignore-variables"]);

				if(in_array($requestVariable,$ignore)){

					$this->log("Ignoring $requestVariable",0,"yellow");
					return TRUE;

				}


				return FALSE;

			}

			protected function query($requestVariable,$injectionMethod=NULL,$modRewrite=FALSE){

				if(empty($requestVariable)){
					throw (new \Exception("Query error: Cannot execute query with no affected url variable set!"));
				}

				if(!($this->_queryBuilder instanceof \aidSQL\db\QueryBuilderInterface)){
					throw (new \Exception("Query error: You must set a query builder in order to perform a query"));
				}

				if($this->_parser instanceof ParserInterface){
					throw (new \Exception("Query error: Cannot execute query with no parser make sure your parser complies with the ParserInterface!"));
				}

				if(!isset($this->_queryCount[$injectionMethod])){

					$count	=	$this->_queryCount[$injectionMethod]=0;

				}

				$count				=	++$this->_queryCount[$injectionMethod];
				$url					=	$this->_httpAdapter->getUrl();
				$sql					=	$this->_queryBuilder->getSQL();

				$this->_lastQuery	=	clone($this->_queryBuilder);		//Save last query
				$this->_queryBuilder->reset();								//Take away previous SQL

				if($this->_config["all"]["verbose"]==2){

					$this->log("[$count][$requestVariable]\t| METHOD: $injectionMethod",0,"light_cyan");
					$this->log("[QUERY]\t| $sql",0,"yellow");

				}

				if($modRewrite){
					$url->changePath($requestVariable,$sql);

				}else{

					$oldValue	=	$url->getVariable($requestVariable);
					$url->addRequestVariable($requestVariable,$sql);

				}

				$this->_httpAdapter->setUrl($url);

				try{

					$content					=	$this->_httpAdapter->fetch();
					$code						=	$this->_httpAdapter->getHttpCode();

					if($code==414&&$modRewrite){
						die("FFFFFFFFFFFFFUUUUUUUUUUUUUUUUUUUUUUUUUU\n");
					}

					$this->_queryResult	=	$content;
					$this->_totalQueries++;

					if($this->_config["all"]["verbose"]==2){
						$this->log($content,0,"light_cyan");
					}

					$result	=	$this->_parser->analyze($content);

					//Restore original URL

					if($modRewrite){

						$url->restorePath($requestVariable);

					}else{

						$url->addRequestVariable($requestVariable,$oldValue);

					}

					$this->_httpAdapter->setUrl($url);

					return $result;

				}catch(\Exception $e){

					$this->log($e->getMessage());

				}

				return FALSE;

			}

			public function setHttpAdapter(\aidSQL\http\Adapter $adapter){
				$this->_httpAdapter = $adapter;
			}

			public function getHttpAdapter(){
				return $this->_httpAdapter;
			}

			public function getAllSchemas(){

				$this->getSchemas();
				return $this->_schemas;

			}

			public function setVerbose($boolean=TRUE){
				$this->_verbose = $boolean;
			}

			public function getVerbose(){
				return $this->_verbose;
			}

			public function setParser(\aidSQL\parser\ParserInterface &$parser){
				$this->_parser = $parser;
			}

			public function getParser(){
				return $this->_parser;
			}

			private function separateRequestVariablesByType(Array $requestVariables,$type){

				$numVariables	=	array();
				$strVariables	=	array();

				foreach($requestVariables as $name=>$value){

					if(is_numeric($value)){
						$numVariables[$name]	=	$value;
					}else{
						$strVariables[$name]	=	$value;
					}

				}

				switch(strtolower($type)){

					case "numeric":
						return $numVariables;
						break;

					case "string":
						return $strVariables;
						break;

					case "numeric-first":
						return array_merge($numVariables,$strVariables);
						break;

					case "strings-first":
						return array_merge($strVariables,$numVariables);
						break;

					default:
						return array_merge($numVariables,$strVariables);
						break;

				}

			}

		}

	}

?>
