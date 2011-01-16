<?php

	namespace aidSQL\plugin\sqli {

		abstract class InjectionPlugin implements InjectionPluginInterface {

			private		$_stringEscapeCharacter				=	NULL;
			private		$_queryConcatenationCharacter		=	NULL;
			private		$_queryCommentOpen					=	NULL;
			private		$_queryCommentClose					=	NULL;
			private		$_table									=	NULL;
			private		$_verbose								=	FALSE;
			
			protected	$_log										=	NULL;
			protected 	$_httpAdapter							=	NULL;
			protected 	$_httpCode								=	NULL;
			protected	$_affectedVariable					=	Array();
			protected 	$_injectionAttempts					=	40;
			protected 	$_parser									=	NULL;
			protected 	$_dbUser									=	NULL;
			protected	$_config									=	NULL;
			protected	$_isVulnerable							=	FALSE;
			protected	$_shellCode								=	NULL;
			protected	$_shellFileName						=	NULL;


			public final function __construct(\aidSQL\http\Adapter $adapter,Array $config,\aidSQL\core\Logger &$log=NULL){

				$url	=	$adapter->getUrl();

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
				$this->_log = $log;
			}

			public function log($msg = NULL,$color="white",$type="0",$toFile=FALSE){

				if(!(isset($this->_config["all"]["verbose"])&&(bool)$this->_config["all"]["verbose"])){
					echo ".";
					return;
				}

				$logToFile			=	(isset($this->_config["log-all"]))	?	TRUE	:	$toFile;

				if(!is_null($this->_log)){

					$this->_log->setPrepend("[".get_class($this)."]");
					$this->_log->log($msg,$color,$type,$logToFile);
					return TRUE;

				}

				return FALSE;

			}

			/**
			*Good for decoupling execution with injection string generation
			*/

			protected function execute($variable,$value){

				$url	=	$this->_httpAdapter->getUrl();
				$url->addRequestVariable($variable,$value);
				$this->_httpAdapter->setUrl($url);

				if(isset($this->_config["all"]["decode-requests"]) && (bool)$this->_config["all"]["decode-requests"]){
					$this->log("Fetching ".urldecode($url->getURLAsString()));
				}else{
					$this->log("Fetching ".$url->getURLAsString());
				}

				try{

					$content				=	$this->_httpAdapter->fetch();

					if($content===FALSE){

						if(!$this->_config["all"]["http-ignore-errors"]){

							throw(new \Exception("There was a problem processing the request! Got ". $this->_httpCode."!!!"));

						}else{

							$this->log("Got ". $this->_httpCode." proceeding anyways by user request ...");

						}

					}

				}catch(\Exception $e){

					$this->log($e->getMessage());

				}


				$this->_httpCode	=	$this->_httpAdapter->getHttpCode();

				if($this->_httpCode!==200){

					return FALSE;

				}

				if($this->_verbose){
					$this->log($content);
				}

				return $content;

			}

			public function setStringEscapeCharacter($escape=NULL){

				if(empty($escape)){
					throw(new Exception("String escape character cannot be empty!"));
				}

				$this->_stringEscapeCharacter	=	$escape;

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

			public function getTable(){
				return $this->_table;
			}

			public function getStringEscapeCharacter(){

				return $this->_stringEscapeCharacter;

			}

			public function setQueryConcatenationCharacter($concatChar=NULL){

				if(empty($concatChar)){
					throw(new Exception("String escape character cannot be empty!"));
				}

				$this->_queryConcatenationCharacter = $concatChar;

			}

			public function getQueryConcatenationCharacter(){
				return $this->_queryConcatenationCharacter;
			}

			public function setQueryCommentOpen($commentOpen=NULL){

				if(is_null($commentOpen)){
					throw(new \Exception("Query comment open character cant be null!"));
				}

				$this->_queryCommentOpen = $commentOpen;

			}

			public function getQueryCommentOpen(){
				return $this->_queryCommentOpen;
			}

			public function setQueryCommentClose($commentClose=NULL){

				if(is_null($commentClose)){
					throw(new Exception("Query comment close character cant be NULL!"));
				}

				$this->_queryCommentClose = $commentClose;

			}

			public function getQueryCommentClose(){
				return $this->_queryCommentClose;
			}

			public function setAffectedVariable($var,$value){

				$this->_affectedVariable = array("variable"=>$var,"value"=>$value);

			}

			public function getAffectedVariable(){
				return $this->affectedVariable;
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

			public function setParser(\aidSQL\parser\ParserInterface $parser){
				$this->_parser = $parser;
			}

			public function getParser($requiredParser=NULL){

				if(!is_null($requiredParser)){

					$allParsers	= $this->listParsers();

					$flag = FALSE;

					foreach($allParsers as $theParser){

						if(strtolower($theParser) == strtolower($requiredParser)){

							$parserClassName	= "\\aidSQL\\parser\\$theParser";
							$parserInstance	= new $parserClassName();
							$this->setParser($parserInstance);
							$flag = TRUE;
							break;

						}

					}

					if(!$flag){
						throw (new \Exception("$requiredParser parser was not found in class/parser/TagMatcher.parser.php!"));
					}

				}

				return $this->_parser;

			}


			/**
			 *	Performs directory listing on the parser directory to find out which parsers
			 * are available.
			 * @return Array Parsers as string
			 */
			public function listParsers(){

					$dir	= __CLASSPATH."/parser/";
					$dp	= opendir($dir);

					$baseParser = "Generic.parser.php";
					$parserList = array();

					while($file = readdir($dp)){

						if(is_dir($file)||$file==$baseParser||preg_match("/^[.]/",$file)){
							continue;
						}

						$class = substr($file,0,strpos($file,"."));
						$parserList[] = $class;

					}

					closedir($dp);

					return $parserList;

			}

		}

	}

?>
