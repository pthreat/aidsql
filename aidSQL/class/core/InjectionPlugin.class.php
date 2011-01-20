<?php

	namespace aidSQL\plugin\sqli {

		abstract class InjectionPlugin implements InjectionPluginInterface {

			//General Stuff

			protected	$_logger						=	NULL;
			protected 	$_httpAdapter				=	NULL;
			protected	$_verbose					=	FALSE;

			//This is a plugin that knows howto check if the injection has succeded
			//The whole point is to the children classes to set the parser of their
			//preference.

			protected 	$_parser						=	NULL;
			protected	$_config						=	NULL;
			protected	$_injectionMethodString	=	"injection";
			protected	$_injectionMethods		=	array();
			protected	$_queryCount				=	array("someInjectionMethod"=>0);
			protected	$_lastQuery					=	NULL;
			protected	$_queryResult				=	NULL;

			//Injection related stuff

			protected 	$_injectionAttempts		=	40;

			//Shell related stuff
			protected	$_shellCode					=	NULL;
			protected	$_shellFileName			=	NULL;

			//A database schema object
			protected	$_dbSchema					=	NULL;		

			public final function __construct(\aidSQL\http\Adapter $adapter,Array $config,\aidSQL\core\Logger &$log=NULL){

				$this->getInjectionMethods();

				if(!sizeof($this->_injectionMethods)){
					throw (new \Exception("No injection methods were found in this plugin!"));
				}

				$url						=	$adapter->getUrl();
				$requestVariables		=	$url->getQueryAsArray();

				if(!sizeof($requestVariables)){

					throw(new \Exception("Unable to perform injection without any request variables set in the http adapter!"));

				}

				$this->setConfig($config);

				$keys						=	array_keys($config);
				
				if(in_array("numeric-only",$keys)){

					$requestVariables	=	$this->separateRequestVariablesByType($requestVariables,"numeric");
					$url->addRequestVariables($requestVariables);

				}elseif(in_array("strings-only",$keys)){

					$requestVariables	=	$this->separateRequestVariablesByType($requestVariables,"string");
					$url->addRequestVariables($requestVariables);

				}

				$adapter->setUrl($url);

				$this->setHttpAdapter($adapter);

				if(!is_null($log)){
					$this->setLog($log);
				}


			}

			public function isVulnerable(){

				foreach($this->_injectionMethods as $injectionMethod){
					$this->$injectionMethod();
				}

			}

			public function setAffectedURLVariable($var){
				$this->_affectedUrlVariable	=	$var;
			}

			public function getAffectedURLVariable(){
				return $this->_affectedUrlVariable;
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

				if(!$int){

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

			public function log($msg = NULL,$color="white",$type="0",$toFile=FALSE){

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

			protected function query(\aidSQL\core\QueryBuilder $builder,$requestVariable,$injectionMethod=NULL){

				if(empty($requestVariable)){
					throw (new \Exception("Query error: Cannot execute query with no affected url variable set!"));
				}

				if($this->_parser instanceof ParserInterface){
					throw (new \Exception("Query error: Cannot execute query with no parser make sure your parser complies with the ParserInterface!"));
				}

				$this->_lastQuery	=	$builder;

				if(!isset($this->_queryCount[$injectionMethod])){

					$count	=	$this->_queryCount[$injectionMethod]=1;

				}else{

					$count	=	$this->_queryCount[$injectionMethod]++;

				}

				$sql	=	$builder->getSQL();
				$url	=	$this->_httpAdapter->getUrl();

				$this->log("[$count][$requestVariable]\t| METHOD: $injectionMethod",0,"light_cyan");

				$this->log("[QUERY]\t| $sql",0,"yellow");

				$url->addRequestVariable($requestVariable,$sql);

				$this->_httpAdapter->setUrl($url);

				try{

					$content					=	$this->_httpAdapter->fetch();
					$this->_queryResult	=	$content;

					if($this->_verbose){
						$this->log($content);
					}

					$result	=	$this->_parser->analyze($content);

					if($result){
						return $result;
					}

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

			public function setTable($table=NULL){

				if(empty($table)){
					throw (new Exception("The table name cant be empty!"));
				}

				$this->_table = $table;

			}

			public function getSchema(){

				return $this->_dbSchema;

			}

			/**
			*This for now, only stands for outputting the results from URL execution to stdout
			*/

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

					default:
						return array_merge($numVariables,$strVariables);
						break;

				}

			}

			private function orderRequestVariables(){


				if(in_array("numeric-only",$keys)){


				}elseif(in_array("strings-only",$keys)){

					$strings	=	$vars["strings"];

				}else{

					return $requestVariables;

				}

			}

		}

	}

?>
