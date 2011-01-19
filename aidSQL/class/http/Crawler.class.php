<?php

	namespace aidSQL\http {

		class Crawler {

			private	$_host				=	NULL;
			private	$_httpAdapter		=	NULL;
			private	$_content			=	NULL;
			private	$_pages				=	array();
			private	$_depth				=	0;
			private	$_depthCount		=	0;
			private	$_externalUrls		=	array();
			private	$_scheme				=	NULL;
			private	$_emails				=	array();
			private	$_files				=	array();		//PHP, HTM,PDF, TXT other extensions
			private	$_omitPaths			=	array();
			private	$_omitPages			=	array();
			private	$_pageTypes			=	array();
			private	$_lpp					=	0;				//Links per page
			private	$_log					=	NULL;
			private	$_maxLinks			=	0;				//Amount of links desired to crawl
			private	$_urlList			=	array();		//Holds all the urls
			private	$_config				=	array();		//Holds configuration parameters passed from the command line and config

			public function __construct(\aidSQL\http\Adapter &$httpAdapter,\aidSQL\http\Url $url,\aidSQL\core\Logger &$log){

				$httpAdapter->setUrl($url);
				
				$this->_urlList[]		=	array("url"=>$url,"method"=>$httpAdapter->getMethod());

				$this->_host			=	$url;
				$this->_httpAdapter	=	$httpAdapter;
				$this->setLog($log);

				$this->log("Normalized URL: ".$this->_host->getUrlAsString());

			}

			public function setConfig(Array $config){

				if(isset($config["lpp"])){

					$this->setLinksPerPage($config["lpp"]);

				}


				if(isset($config["max-links"])){

					$this->setMaxLinks($config["max-links"]);

				}


				if(isset($config["page-types"])){

					$this->addPageTypes(explode(",",$config["page-types"]));

				}


				if(isset($config["omit-paths"])){

					$omitPaths = explode(",",$config["omit-paths"]);
					$this->addOmitPaths($omitPaths);

				}


				if(isset($config["omit-pages"])){

					$omitPages = explode(",",$config["omit-pages"]);
					$this->addOmitPages($omitPages);

				}

				if(isset($config["crawl"])){

					$this->setDepth($config["crawl"]);

				}

				$this->_config	=	$config;

			}

			private function log($msg=NULL,$color="white",$level=0,$toFile=FALSE){

				if(sizeof($this->_config)&&!$this->_config["verbose"]){
					return;
				}

				if(isset($this->_config["log-all"])){
					$toFile	=	TRUE;
				}

				if(!is_null($this->_log)){

					$this->_log->setPrepend('['.__CLASS__.']');
					$this->_log->log($msg,$color,$level,$toFile);
					return TRUE;

				}

				return FALSE;

			}

			public function setLog(\aidSQL\core\Logger &$log){

				$this->_log = $log;

			}

			public function addPageType($type){

				if(empty($type)){
					throw(new \Exception("Given page type was empty!"));
				}

				if(!in_array($type,$this->_pageTypes)){

					$this->_pageTypes[] = $type;
					return TRUE;

				}

				return FALSE;

			}

			public function addPageTypes(Array $types){

				foreach($types as $type){
					$this->addPageType($type);
				}

			}

			public function pageHasValidType($page){

				if(!sizeof($this->_pageTypes)){
					return NULL;
				}

				$pageType = substr($page,strrpos($page,".")+1);

				if(in_array($pageType,$this->_pageTypes)){
					return TRUE;
				}

				return FALSE;

			}

			public function addOmitPath($path){

				if(empty($path)){
					throw(new \Exception("Given path was empty!"));
				}

				if(!in_array($path,$this->_omitPaths)){

					$this->_omitPaths[] = $path;
					return TRUE;

				}

				return FALSE;

			}

			public function addOmitPaths (Array $paths){

				foreach ($paths as $path){
					$this->addOmitPath($path);
				}

			}

			public function addOmitPage($page=NULL){

				if(empty($page)){
					throw(new \Exception("Given page was empty!"));
				}

				if(!in_array($page,$this->_omitPages)){

					$this->_omitPages[] = $page;
					return TRUE;

				}

				return FALSE;

			}

			public function addOmitPages(Array $pages){

				foreach ($pages as $page){
					$this->addOmitPage($page);
				}

			}

			public function isOmittedPath($path=NULL){

				if(empty($path)){
					throw(new \Exception("Path to be tested cant be empty!"));
				}

				$path = trim($path,"/");

				if(in_array($path,$this->_omitPaths)){
					return TRUE;
				}
	
				return FALSE;

			}


			public function isOmittedPage($page=NULL){

				if(empty($page)){
					throw(new \Exception("Page to be tested cant be empty!"));
				}

				if(in_array($page,$this->_omitPages)){
					return TRUE;
				}
	
				return FALSE;

			}


			public function isEmailLink($link){

				if(!preg_match("#mailto:.*#",$link)){
					return FALSE;	
				}

				return TRUE;

			}

			/**
			*Adds an email link, if the link is an email link returns TRUE, else it returns false
			*/

			public function addEmailLink($link){

				$mail	= substr($link,strpos($link,":"));

				if(!in_array($mail,$this->_emails)){
					$this->_emails[] = $mail;
				}

				return TRUE;

			}


			public function setMaxLinks($amount=0){
				$this->_maxLinks=(int)$amount;
			}

			public function setLinksPerPage($amount=0){

				$amount = (int)$amount;

				if($amount==0){
					throw(new \Exception("Amount of links per page can't be 0"));	
				}

				$this->_lpp = $amount;

			}


			public function getEmailLinks($link){

				return $this->_emails;

			}


			private function reduxLinks(Array $links){

				$sizeOfLinks = sizeof($links);

				if(!$sizeOfLinks){
					$this->log("No links to reduce");	
					return $links;
				}


				if($sizeOfLinks < $this->_lpp){

					$this->log("Amount of links not enough to perform redux!");
					return $links;

				}

				$this->log("Shuffling Links ...");

				$shuffled = array_keys($links);
				shuffle($shuffled);

				for($i=0;$i<$this->_lpp;$i++){
					unset($links[$shuffled[$i]]);
				}

				return $links;

			}


			public function getOtherSites(){
				return $this->_otherSites;
			}

			public function setDepth($depth=5){
				$this->_depth = $depth;
			}

			public function getUrlList($withParameters=TRUE){


				return $this->_urlList;
//urls;

			}

			private function addExternalSite(\aidSQL\http\Url $extUrl){

				foreach($this->_externalUrls as $ext){

					if($ext->getHost()!=$this->_host->getHost()){

						$this->log("External URL detected ".$ext->getFullUrl($parameters=TRUE),0,"green");
						$this->_externalUrls[$ext->getHost()][] = $ext;
						return TRUE;

					}

				}

				return FALSE;

			}

			/**
			*@return int  Crawled, Array position of the url in the urlList
			*@return bool FALSE not crawled
			*/

			public function wasCrawled(\aidSQL\http\Url $url,$method=NULL){

				foreach($this->_urlList as $index=>$_url){

					$equalUrls	= (!strcasecmp($_url["url"]->getUrlAsString($parameters=FALSE),$url->getUrlAsString($parameters=FALSE)));
					$sameMethod	= !strcasecmp($_url["method"],strtoupper($method));

					if($equalUrls&&$sameMethod){
						return $index;
					}

				}

				return FALSE;
				
			}

			/**
			*Some sites make bad use of mod_rewrite and other server side URL rewriting
			*techniques which can cause the crawler to go into recursion mayhem, hopefully,
			*this function will avoid that kind of recursion.
			*@param String $path only the path, not the hostname
			*@param Int    $fuckLimit count until fuckLimit is reached
			*@return boolean TRUE  The URL is fucked up
			*@return boolean FALSE The URL is not fucked up
			*/

			public function detectModRewriteFuckUp($path,$fuckLimit=2){

				if($fuckLimit==0){
					throw(new \Exception("Fuck limit cant be 0!"));
				}

				$token	=	strtok($path,"/");
				$paths	=	array();
				$fucked	=	0;
				$i			=	0;
				$fuckLimit--;

				for($i=0;($fucked<$fuckLimit)&&($token!==FALSE);$i++){

					$paths[$i]=$token;

					if($i!=0){

						for($x=0;$x<$i;$x++){
							if($paths[$x]==$paths[$i]){
								$fucked++;
							}
						}

					}
			
					$token = strtok("/");

				}

				if($fucked>=$fuckLimit){
					return TRUE;
				}

				return FALSE;

			}

			public function addFile(Array $file){

				$key		= key($file);
				$files	=	array_keys($this->_files);

				if(in_array($key,$files)){
					return FALSE;
				}

				$this->_files[$key]	=	$file[$key];
				return TRUE;

			}

			public function getFiles(){
				return $this->_files;
			}


			private function makeUrl($uri,$path=NULL){

				if(!preg_match("#://#",$uri)){	

					//Means that the uri is relative to the path
					//We *have* to normalize the url passing also the host 

					$path	=	(dirname($uri)=='.')	? $path.$this->_host->getPathSeparator() : $this->_host->getPath().$this->_host->getPathSeparator();

					$url				=	new \aidSQL\http\URL($this->_host->getScheme()."://"				.
																	$this->_host->getHost()							.
																	$this->_host->getPathSeparator()				.
																	$path													.
																	$uri
										);

				}else{

					$url	=	new \aidSQL\http\URL($uri);

				}

				return $url;

			}

			private function makeUrls(Array &$uris,$path=NULL){

				foreach($uris as $key=>$uri){

					$uris[$key]	=	$this->makeUrl($uri,$path);

				}

			}

			private function makeUrlFromForm(Array $form,$url){

				$form	=	$form[key($form)];

				if(!isset($form["elements"])){
					return FALSE;
				}

				$method	=	(isset($form["attributes"])&&isset($form["attributes"]["method"]))	?	$form["attributes"]["method"]	:	"GET";

				if(isset($form["attributes"]["action"])){

					$action	=	$this->makeUrl($form["attributes"]["action"],$url->getPath())->getUrlAsString();

				}else{

					$action	=	$url->getUrlAsString();

				}


				$query	=	array();

				foreach($form["elements"] as $formElement){

					$formElement	=	$formElement[key($formElement)];
					$name				=	$formElement["attributes"]["name"];

					if(isset($formElement["attributes"]["value"])){

						//Ok, has value
						$value	=	$formElement["attributes"]["value"];

					}elseif(isset($formElement["attributes"]["values"])){

						//Choose a random value
						$value	=	$formElement["attributes"]["values"][mt_rand(0,sizeof($formElement["attributes"]["values"])-1)];

					}else{	//Seems like the field is for setting user data into it

						//Generate some content
						if(isset($formElement["attributes"]["maxlength"])){

							$length	=	$formElement["attributes"]["maxlength"];

						}else{

							$length	=	mt_rand(1,5);

						}

						$value	=	substr(time(),0,$length);

					}

					$query[]	=	$name.$this->_host->getEqualityOperator().$value;

				} //foreach($form["elements"] as $formElement)

				$url	=	$action.$url->getQueryIndicator().implode($query,$url->getVariableDelimiter());
				$url	=	$this->makeUrl($url,NULL,$method);

				return $url;

			}

			private function searchForms(\aidSQL\http\Url $url,\aidSQL\core\Dom &$dom){

				$forms	=	$dom->fetchForms();

				if(!sizeof($forms)){
					return array();
				}

				$this->log("Found ".sizeof($forms)." forms ...",0,"light_cyan");

				$formLinks	=	array();	

				foreach($forms as $key=>$form){

					$frmKey		=	key($forms[$key]);
					$method		=	isset($forms[$key][$frmKey]["attributes"]["method"])&&!empty($forms[$key][$frmKey]["attributes"]["method"]);

					if($method){

						$method	=	strtoupper($forms[$key][$frmKey]["attributes"]["method"]);

						if(!($method=="POST"||$method=="GET")){

							$method	=	"GET";
		
						}

					}else{

							$method	=	"GET";

					}

					$frmUrl		=	$this->makeUrlFromForm($form,$url);

					if($frmUrl===FALSE){	//Form has no elements
						continue;
					}

					if($this->isExternalSite($frmUrl)){

						$this->addExternalSite($frmUrl,$method);

					}else{
					
						$this->addUrl($frmUrl,$method,FALSE);

					}

				}

			}

			public function addUrl(\aidSQL\http\Url $url=NULL,$method=NULL,$validatePageType=TRUE){

				$method	=	(empty($method))	?	"GET"	:	trim(strtoupper($method));

				if($method!=="POST"&&$method!=="GET"){
					$method	=	"GET";
				}

				$_empty	=	$url->getPage();

				if(!empty($_empty)){

					$page	=	$url->getPath().$url->getPathSeparator().$url->getPage();

					if($this->isOmittedPage($page)){

						$this->log("*$page  was meant to be omitted",0);
						return FALSE;

					}

					if($validatePageType){

						if($this->pageHasValidType($url->getPage())===FALSE){
							
							$this->log($url->getPage()." doesnt matches given file types",0,"yellow");
							return FALSE;

						}else{
						
							$this->log("Page \"".$url->getPage()."\" matches required types ".implode($this->_pageTypes,","),0,"light_green");

						}
	
					}

				}

				$urlListIndex	=	$this->wasCrawled($url,$method);

				if($urlListIndex!==FALSE){

					$this->log("Parsing previously crawled URL, looking for new parameters ...",0,"white");

					$parameters	=	$url->getQueryAsArray();

					if(sizeof($parameters)){

						$storedParameters		=	$this->_urlList[$urlListIndex]["url"]->getQueryAsArray();

						if(sizeof($storedParameters)){

							$sizeOfStoredParams	=	sizeof($storedParameters);

							foreach($parameters as $parameter=>$value){

									if(in_array($parameter,array_keys($storedParameters))){

										$this->log("Parameter $parameter was already inside",0,"yellow");
										continue;

									}else{

										$this->log("Adding new parameter \"$parameter\"!",0,"cyan");
										$this->_urlList[$urlListIndex]["url"]->addRequestVariable($parameter,$value);

									}

							}

						}else{

							foreach($parameters as $parameter=>$value){

								$this->log("Adding new parameter \"$parameter\"!",0,"cyan");
								$this->_urlList[$urlListIndex]["url"]->addRequestVariable($parameter,$value);

							}
						
						}

					}else{
	
								$this->log("No parameters found");
								return FALSE;

					}

				}else{

					$this->log("Add URL \"$url\"!",0,"cyan");
					$this->_urlList[]	=	array("url"=>$url,"method"=>$method);
					return TRUE;

				}

			}

			public function crawl(\aidSQL\http\Url $url=NULL){

				$this->log($this->drawLine($this->_depthCount++,0,"light_cyan"));

				if($this->_depth>0){
					if($this->_depthCount>$this->_depth){
						return NULL;
					}
				}

				if(!is_null($url)){

					$this->_httpAdapter->setURL($url);

				}else{

					$url	=	$this->_httpAdapter->getUrl();

				}


				if($this->isOmittedPath($url->getPath())){

					$this->log('*'.$url->getPath()." is omitted will NOT fetch content from here!");
					return FALSE;

				}

				$this->log("Fetching content from ".$url->getUrlAsString($parameters=TRUE),0,"light_green");


				try{

					$requestContent	=	$this->_httpAdapter->fetch();
					$dom					=	new \aidSQL\core\Dom($requestContent);

					if($this->_maxLinks>0){

						if(sizeof($this->_urlList)>$this->_maxLinks){

							$this->log("Link limit reached!",2,"white");
							return NULL;

						}

					}

					if($this->detectModRewriteFuckUp($url->getPath())){

						$this->log("Possible url rewrite Fuck up detected in ".$url->getPath());
						return FALSE;

					}


					if(($httpCode = $this->_httpAdapter->getHttpCode()) != 200){

						$this->log("Got $httpCode",1,"red");
						return FALSE;

					}else{

						$this->log("200 OK",0,"light_green");

					}


					//Fetches all the links, we are through with this page, hence we have effectively
					//got all links on the given content.

					$images	=	$dom->fetchImages();		//Get all the images, image location is important to know
																	//certain DocumentRoot locations in order to get a shell.
																	//This is the case of the mysql5 plugin

					$this->makeUrls($images,$url->getPath());


					$this->filterExternalSites($images);
	
					if(sizeof($images)){

						$this->log("Found ".sizeof($images)." images",0,"light_cyan");

						foreach($images as $img){

							$file		=	$img->getPath().$img->getPathSeparator().$img->getPage();

							if ($this->addFile($this->whatIs($file))){
								$this->log("Add file $file",0,"light_purple");
							}

						}

					}else{
	
						$this->log("No images found",2,"yellow");

					}
				
					//This also returns javascript links and anchors
					//might want to use them in the future.
	
					$urls	=	$dom->fetchLinks();
					$urls	=	$urls["links"];

					if($this->_lpp>0 && sizeof($urls) > $this->_lpp){

						$this->log("Reducing links amount to ".$this->_lpp,0,"yellow");
						$urls = $this->reduxLinks($urls);

					}

					$this->makeUrls($urls,$url->getPath(),"GET");	//Foreach URI returned by the content makes a URL Object
					$this->searchForms($url,$dom);


					$this->filterExternalSites($urls);					//Foreach made URL object takes away the external sites

					$amountOfUrls = sizeof($urls);

					if(!$amountOfUrls){

						$this->log("No links found",2,"yellow");
						return FALSE;

					}else{

						$this->log("TOTAL URL's found: $amountOfUrls",0,"light_cyan");

					}

					foreach($urls as $key=>$_url){

						$file		=	trim($_url->getPath().$_url->getPathSeparator().$_url->getPage(),'/');
						$file		=	$this->whatIs($file);

						if(is_array($file)){

							if($this->addFile($file)){

								$this->log("Add file ".$_url->getPage()." ...",0,"light_purple");

							}

						}

						$addUrl	=	$this->addUrl($_url,"GET");

						if($this->_depth > 0 && $addUrl){
	
							$crawlResult		=	$this->crawl($_url);
							$this->depthCount	=	0;

							if(is_null($crawlResult)){

								$this->log("DEPTH LIMIT REACHED!",1,"yellow");

							}

						}

					}

				}catch(\Exception $e){

					$this->log($e->getMessage(),1,"red");
					return NULL;

				}

			}

			private function filterExternalSites(Array &$links){

				foreach($links as $key=>$url){

					if($this->isExternalSite($url)){

						if($this->addExternalSite($url)){

							$this->log($url->getHost().", external site detected adding to other sites list ...",0,"purple");

						}

						unset($links[$key]);

					}

				}
			
			}

			private function drawLine($depth){

				$depth = ($depth == 0) ? 1 : $depth;

				$line = "";

				for($i=0;$i<$depth;$i++){
					$line.="-";
				}

				$line.=">";

				return $line;

			}

			private function whatIs($link){

				$bName				=	basename($link);
				$dotPos				=	strrpos($bName,".");
				$return				=	array();

				if(!$dotPos){
					$return[$link] = array("type"=>"path");
					return $return;
				}

				$docExt						=	strtolower(substr($bName,$dotPos+1));
				$return[$link]["type"]	=	$docExt;
				$argPos						=	strpos($docExt,"?");

				if($argPos){

					$return[$link]["arguments"]	=	substr($docExt,$argPos+1);

				}

				return $return;

			}


			public function getHostURL($parse_url){

				if(!isset($parse_url["scheme"])){
					$parse_url["scheme"] = "http";
				}

				return $parse_url["scheme"]."://".$parse_url["host"];

			}

			public function isExternalSite(\aidSQL\http\Url $url){

				$currentHost	=	$this->_host->getHost();
				$givenHost		=	$url->getHost();

				if($currentHost!==$givenHost){
						return TRUE;
				}

				return FALSE;

			}


			private function getRelativePath($link=NULL,$path="/"){

				$link				=	trim($link,"/");
				$path				=	"/".trim($path,"/");
				$token			=	strtok($link,"/");
				$ascendCount	=	0;

				while($token!==FALSE){

					if($token==".."){
						$ascendCount++;
					}

					$token = strtok("/");

				}


				while($ascendCount--){

					$path	=	substr($path,0,strrpos($path,'/'));
					$link	=	substr($link,strpos($link,'/')+1);

				}

				$link = trim($link,".");
				$link = trim($link,"/");

				if(empty($path)){
					$path="/";
				}

				if($path=="/"){
					return $this->parseUrl($this->_host["scheme"]."://".$this->_host["host"].$path.$link);
				}

				return $this->parseUrl($this->_host["scheme"]."://".$this->_host["host"].$path."/".$link);

			}

		}

	}
?>
