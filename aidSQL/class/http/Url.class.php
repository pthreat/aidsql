<?php

	namespace aidSQL\http {

		class Url {

			private	$_url						=	array();
			private	$_variables				=	array();
			private	$_varDelimiter			=	'&';
			private	$_equalityOperator	=	'=';
			private	$_queryIndicator		=	'?';
			private	$_pathSeparator		=	'/';

			public function __construct ($url=NULL){

				$this->parse($url);

			}

			public function getVariableDelimiter(){
				return $this->_varDelimiter;
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

				$parsedUrl=array();

				if(!preg_match("#://#",$url)){

					$scheme	=	"http";
					$url		=	$scheme."://".$url;

				}else{

					$scheme	=	substr($url,0,strpos($url,":"));

				}

				$parsedUrl["fullUrl"]	=	$url;
				$parsedUrl["scheme"]		=	$scheme;

				$host	=	substr($url,strlen($scheme)+3);

				if(strpos($host,"/")!==FALSE){

					$host	=	substr($host,0,strpos($host,"/"));

				}else{

					$host	=	substr($url,strlen($scheme)+3);

				}

				if(strpos($host,$this->_queryIndicator)){
					$host	=	substr($host,0,strpos($host,$this->_queryIndicator));
				}

				$parsedUrl["host"]		=	$host;

				$path	=	substr($url,strlen($scheme)+3+strlen($host));

				if(strrpos($path,"/")!==FALSE){

					$path = substr($path,0,strrpos($path,"/")+1);

				}else{

					$path	=	"/";

				}

				$parsedUrl["path"]	=	$path;

				if(strrpos($path,$this->_queryIndicator)!==FALSE){
					$parsedUrl["path"]	=	substr($path,0,strpos($path,$this->_queryIndicator));
				}

				$parsedUrl["page"]	=	basename($url);

				if(strpos($url,$this->_queryIndicator)==FALSE){

					$parsedUrl["query"]	=	"";

				}else{

					$parsedUrl["query"]	=	substr($url,strpos($url,$this->_queryIndicator)+1);

					$this->addRequestVariables($this->queryStringToArray($parsedUrl["query"]));

					$parsedUrl["page"]	=	substr($parsedUrl["page"],0,strpos($parsedUrl["page"],$this->_queryIndicator));

				}

				if($parsedUrl["page"]==$parsedUrl["host"]){
					$parsedUrl["page"]="";
				}

				if(preg_match("#..#",$parsedUrl["path"])){
					$parsedUrl["path"]		=	$this->parseRelativePath($parsedUrl["path"]);
					$parsedUrl["is_relative"]	=	TRUE;
				}else{
					$parsedUrl["is_relative"]	=	FALSE;
				}

				$this->_url	=	$parsedUrl;

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

			public function getPath(){
				return $this->_url["path"];
			}

			public function getPage(){
				return $this->_url["page"];
			}

			public function getQueryAsString(){
				return $this->parseVariables();
			}

			public function getUrlAsString($parameters=TRUE){

				$full	=	$this->_url["scheme"]."://".$this->_url["host"];
				$path	=	(isset($this->_url["path"]))	?	'/'.trim($this->_url["path"],'/') : '/';
				$page	=	(isset($this->_url["page"]))	?	'/'.trim($this->_url["page"],'/') : NULL;

				if($path=='/'){
					$path=NULL;
				}

				$full	.=	$path.$page;

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

				$path				=	"/".trim($path,"/");
				$token			=	strtok($path,"/");
				$ascendCount	=	0;
				$cleanPath		=	array();
				$count			=	0;

				while($token!==FALSE){

					if($token!==".."){
						$cleanPath[$count++]=$token;
					}

					$token = strtok("/");

				}

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
