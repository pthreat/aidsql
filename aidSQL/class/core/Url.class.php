<?php

	namespace aidSQL\core {

		class Url {

			private	$_url						=	array();
			private	$_variables				=	array();
			private	$_varDelimiter			=	'&';
			private	$_equalityOperator	=	'=';
			private	$_queryIndicator		=	'?';
			private	$_pathSeparator		=	'/';
			private	$_restorePath			=	array();

			public function __construct ($url=NULL){

				$this->parse($url);

			}

			public function getVariableDelimiter(){
				return $this->_varDelimiter;
			}

			public function changePath($matchPath=NULL,$newPath=NULL){

				if(empty($matchPath)||empty($newPath)){
					throw(new \Exception("Must enter a path and a new value to assign to the old path when using changePath!"));
				}

				$urlPaths	=	$this->getPathAsArray(TRUE);

				if(!in_array($matchPath,$urlPaths)){	
					throw(new \Exception("Path $matchPath wasnt found in this url"));
				}

				foreach($urlPaths as $index=>&$path){

					if($path == $matchPath){

						$restorePathExists	=	FALSE;

						foreach($this->_restorePath as $restore){

							if(($restore["index"]==$index) && ($path==$matchPath)){
								$restorePathExists	=	TRUE;
							}

						}

						if(!$restorePathExists){

							$this->_restorePath[]	=	array("index"=>$index,"path"=>$path);

						}

						$path	=	$newPath;

					}

				}

				var_dump($urlPaths);
				$this->setPathArray($urlPaths);

			}

			public function restorePath($position=NULL){
	
				$urlPaths	=	$this->getPathAsArray();

				foreach($this->_restorePath as $pathInfo){

					if(is_int($position)){
						if($pathInfo["index"]==$position){
							$urlPaths[$position]	=	$pathInfo["path"];
						}
					}else{
						$urlPaths[$pathInfo["index"]]	=	$pathInfo["path"];
					}
	
				}

				$this->setPathArray($urlPaths);

			}

			public function setPathArray(Array $pathArray){

				$this->_url["path"]	=	implode($this->_pathSeparator,$pathArray);

			}

			public function parse($url=NULL){
	
				if(is_array($url)){
					throw(new \Exception("Array given when String was required!"));
				}

				if(empty($url)){
					throw(new \Exception("URL cant be empty!"));
				}

				$url	= trim($url);
				$url	= rtrim($url,'/');


				$parsedUrl	=	array(

					"fullUrl"		=>	NULL,
					"scheme"			=>	"http",
					"host"			=>	NULL,
					"path"			=>	'/',
					"page"			=>	NULL,
					"is_relative"	=>	NULL
					
				);

				//FULL URL
				/////////////////////////////////////////////////

				$parsedUrl["fullUrl"]	=	$url;

				//SCHEME PARSING
				/////////////////////////////////////////////////

				if(preg_match("#://#",$url)){

					$parsedUrl["scheme"]	=	substr($url,0,strpos($url,":"));

				}

				$host							=	substr($url,strlen($parsedUrl["scheme"])+3);

				//HOST PARSING
				/////////////////////////////////////////////////

				if(($pos=strpos($host,$this->_pathSeparator))!==FALSE){	// '/'

					$parsedUrl["host"]	=	substr($host,0,$pos);

				}elseif($pos = strpos($host,$this->_queryIndicator)){		// '?'

					$parsedUrl["host"]	=	substr($host,strlen($parsedUrl["scheme"])+3,$pos);

				}else{

					$host						=	trim($host);						//Just a URL without parameters;
					$parsedUrl["host"]	=	$host;
					return $this->_url	=	$parsedUrl;

				}

				//PATH PARSING
				/////////////////////////////////////////////////

				$length					=	strlen($parsedUrl["scheme"])+3+strlen($parsedUrl["host"]);
				$parsedUrl["path"]	=	substr($url,$length);


				//PAGE PARSING
				/////////////////////////////////////////////////

				$lastPathPiece	=	substr($parsedUrl["path"],strrpos($parsedUrl["path"],$this->_pathSeparator)+1);
				$lastPathPiece	=	substr($lastPathPiece,0,strpos($lastPathPiece,$this->_queryIndicator));

				if($pos = strrpos($lastPathPiece,'.')){

					$pageExtension	=	substr($lastPathPiece,$pos+1);
					
					if(strlen($pageExtension)>=1 && $pageExtension!='.'){

						$parsedUrl["page"]	=	$lastPathPiece;

						//MORE PATH PARSING
						$parsedUrl["path"]	=	substr($parsedUrl["path"],0,strpos($parsedUrl["path"],$parsedUrl["page"]));

					}
					
				}else{

					$parsedUrl["page"]	=	NULL;

				}


				if(strpos($url,$this->_queryIndicator)==FALSE){

					$parsedUrl["query"]	=	"";

				}else{

					$parsedUrl["query"]	=	substr($url,strpos($url,$this->_queryIndicator)+1);
					$this->addRequestVariables($this->queryStringToArray($parsedUrl["query"]));

				}

				//Checkout if its a relative path
				
				if(preg_match("/\.\./",$parsedUrl["path"])){

					$parsedUrl["path"]			=	$this->parseRelativePath($parsedUrl["path"]);
					$parsedUrl["is_relative"]	=	TRUE;

				}else{

					$parsedUrl["is_relative"]	=	FALSE;

				}

				$this->_url	=	$parsedUrl;

				var_dump($parsedUrl);
				die();

			}

			public function setPath($path=NULL){
	
				$this->_url["path"]=$path;

			}

			private function queryStringToArray($queryString=NULL){

				$variables	=	array();

				if(empty($queryString)){
					return $variables;
				}

				$tmpQuery	=	explode($this->_varDelimiter,$queryString);

				foreach($tmpQuery as $tmpString){

					$tmpVarValue	=	explode($this->_equalityOperator,$tmpString);
					$variables[$tmpVarValue[0]]	=	(isset($tmpVarValue[1])) ? $tmpVarValue[1] : NULL;

				}

				return $variables;

			}

			public function isRelative(){
				return $this->_url["is_relative"];
			}

			public function addRequestVariable($var,$value=NULL,$urlEncode=TRUE){

				if($urlEncode){

					$this->_variables[$var]=urlencode($value);

				}else{

					$this->_variables[$var]=$value;

				}

			}

			public function getRequestVariable($var=NULL){

				if(isset($this->_variables[$var])){
					return $this->_variables[$var];
				}

				return NULL;

			}

			function addRequestVariables(Array $array){

				foreach($array as $k=>$v){
					$this->addRequestVariable($k,$v);
				}

			}

			public function deleteRequestVariable($var){

				if(isset($this->requestVariables[$var])){
					unset($this->requestVariables[$var]);
					return TRUE;
				}

				return FALSE;

			}

			private function parseVariables(){

				$vars = "";

				foreach ($this->_variables as $k=>$v){

					if (is_null($v)){
						$vars .= $k . $this->_varDelimiter;
						continue;
					}

					$vars .= $k . $this->_equalityOperator . $v . $this->_varDelimiter;

				}

				return substr($vars,0,-1);

			}

			public function getQueryAsArray(){
				return	$this->_variables;
			}

			public function setVariableDelimiter($delimiter=NULL){

				$this->_varDelimiter = $delimiter;

			}

			public function setEqualityOperator($char=NULL){

				$this->_equalityOperator = $char;

			}

			public function getEqualityOperator(){

				return $this->_equalityOperator;

			}

			public function setPathSeparator($char=NULL){

				$this->_pathSeparator = $char;

			}


			public function getPathSeparator($char=NULL){

				return $this->_pathSeparator;

			}


			public function setQueryIndicator($char=NULL){

				$this->_queryIndicator = $char;

			}

			public function getQueryIndicator(){

				return $this->_queryIndicator;

			}

			public function getScheme(){
				return $this->_url["scheme"];
			}

			public function getHost(){
				return $this->_url["host"];
			}

			public function getPath($full=FALSE){

				if($full){

					if(!empty($this->_url["page"])){

						return $this->_url["path"].$this->_pathSeparator.$this->_url["page"];

					}else{

						return $this->_url["path"];

					}

				}

				return $this->_url["path"];

			}

			public function getPathAsArray($full=FALSE){

				$paths	=	explode($this->_pathSeparator,$this->_url["path"]);

				if($full&&!empty($this->_url["page"])){

					$paths[]	=	$this->_url["page"];

				}

				return $paths;

			}

			public function getPage(){
				return $this->_url["page"];
			}

			public function getQueryAsString(){
				return $this->parseVariables();
			}

			public function getUrlAsString($parameters=TRUE){

				$full	=	$this->_url["scheme"]."://".$this->_url["host"];
				$path	=	(isset($this->_url["path"]))	?	'/'.trim($this->getPath(TRUE),'/') : '/';

				if($path=='/'){
					$path=NULL;
				}

				$full	.=	$path;

				if(sizeof($this->_variables)&&$parameters){

					$full	.=	$this->_queryIndicator.$this->parseVariables();

				}

				return $full;
				
			}

			public function getURLAsArray(){
				return $this->_url;
			}

			public function getVariables(){
				return $this->_variables;
			}

			private function parseRelativePath($path=NULL){

				$pathArray			=	explode($this->_pathSeparator,$path);
				$cleanPath			=	array();
				$count				=	0;
				$goBackPositions	=	array();

				foreach($pathArray as $index=>$path){

					if(empty($path)){
						continue;
					}

					if($path==".."){
						$goBackPositions[]	=	$index;
					}

				}

				var_dump($goBackPositions);

				foreach($goBackPositions as $goBack){

					unset($pathArray[$goBack]);

					if(isset($pathArray[$goBack-1])){

						unset($pathArray[$goBack-1]);
						//resort array keys

					}

				}

				var_dump($pathArray);
				die();

				if(!sizeof($cleanPath)){

					$cleanPath	=	array('/');

				}

				return implode($cleanPath,$this->_pathSeparator);

			}

			public function __toString(){
				return $this->getUrlAsString(TRUE);
			}

		}	

	}

?>
