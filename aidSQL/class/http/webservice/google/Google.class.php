<?php

	//CopyRight: Juan Pablo Stange (11-06-2008)
	//Modified: 04-11-2010	(just added namespaces and changed some other stuff)
	//3 Clause BSD License
	//http://code.google.com/p/uoogle

	//:set tabstop=3 #for viewing with vi or vim :)

	namespace aidsql\http\webservice {

		class Google { 

			private	$_baseUrl			=	'http://ajax.googleapis.com/ajax/services/search/web';
			private	$_searchQuery		=	NULL;
			private	$_result				=	NULL;
			private	$_url					=	NULL;
			private	$_httpAdapter		=	NULL;
			private	$_logger				=	NULL;
			private	$_config				=	array();

			//protected $gLKey:	Google license key. This is a valid license. Get your own license, by going to www.google.com/api

			//protected $rsz			:	This optional argument supplies the number of results that the application would like to recieve. 
			//									A value of small indicates a small result set size or 4 results. A value of large indicates a large 
			//									result set or 8 results. If this argument is not supplied, a value of small is assumed.

			//protected $language	:	This optional argument supplies the host language of the application making the request. 
			//									If this argument is not present then the system will choose a value based on the value of the Accept-Language http header.
			//									If this header is not present, a value of en is assumed.

			//protected $start		:	This optional argument supplies the start index of the first search result. 
			//									Each successful response contains a cursor object (see below) which includes an array of pages.
			//									The start property for a page may be used as a valid value for this argument.
	

			protected $_gLKey			=	NULL;
			protected $_rsz			=	'large';
			protected $_version		=	'1.0';
			protected $_language		=	NULL;
			protected $_start			=	NULL;

			public function __construct(\aidSQL\http\Adapter &$adapter=NULL,\aidSQL\core\Logger &$log=NULL){

				$this->_url	=	new \aidSQL\core\URL($this->_baseUrl);

				if(!is_null($log)){

					$this->setLog($log);
					$this->log("Google search engine started",0,"light_cyan");

				}

				if(!is_null($adapter)){
					$this->setHttpAdapter($adapter);
				}
	
			}


			private function log($msg=NULL,$color="white",$level=0,$toFile=FALSE){

				if(isset($this->_config["log-all"])){
					$toFile	=	TRUE;
				}

				if(!is_null($this->_logger)){

					$this->_logger->setPrepend('['.__CLASS__.']');
					$this->_logger->log($msg,$color,$level,$toFile);
					return TRUE;

				}

				return FALSE;

			}


			public function setLog(\aidSQL\core\Logger &$log){

				$log->setPrepend('['.__CLASS__.']');
				$this->_logger = $log;

			}

			public function setHttpAdapter(\aidSQL\http\Adapter &$adapter){

				$this->_httpAdapter = $adapter;

			}

			public function setBaseUrl($url=NULL){

				$this->_url	=	new \aidSQL\core\Url($url);	

			}

			public function setConfig(Array $config){

				$this->setQuery($config["google"]);

				(isset($config["google-language"])) ? $this->setLanguage($config["google-language"]) 	:	NULL;

				$offset		=	(isset($config["google-offset"]))			? $config["google-offset"] 		:	0;

				$this->setStart($offset);

				$this->_config	=	$config;

			}

			public function setQuery($query=NULL) {

				if(empty($query)){
					throw (new Exception("Search query must not be empty"));
				}

				$this->log("Setting search query to \"$query\"",0,"white");
				$this->_searchQuery	=	$query;

			}

			public function getUrl(){

				return $this->_url;

			}

			public function search() { 

				$this->log("Getting results ...",0,"white");

				$searchQuery	=	preg_replace("/ /","%20",$this->_searchQuery);
				$this->_url->addRequestVariable('v',$this->_version);
				$this->_url->addRequestVariable('q',$searchQuery,FALSE);

				($this->_language)	?	$this->_url->addRequestVariable("hl",$this->_language)	:	NULL;
				($this->_gLKey)	 	?	$this->_url->addRequestVariable("key",$this->_gLKey)		:	NULL;
				($this->_rsz)			?	$this->_url->addRequestVariable("rsz",$this->_rsz)			:	NULL;
				($this->_start)		?	$this->_url->addRequestVariable("start",$this->_start)	:	NULL;


				$this->_httpAdapter->setUrl($this->_url);
				$this->_httpAdapter->setMethod("GET");

				$this->_result	=	json_decode($this->_httpAdapter->fetch());

				if (!is_object($this->_result)) {

					$msg	=	"Query {$this->_searchQuery}  didnt retrieved any results";
					throw (new \Exception($msg));

				}

				if ($this->_result->responseStatus !== 200) { 

					$msg = "[ERROR {$this->_result->responseStatus} ] [DETAILS] {$this->_result->responseDetails} [DATA] {$this->_result->responseData}";
					throw (new \Exception($msg));

				}

				return $this->_result;

			}

			public function setAPIVersion ($version=NULL){

				if(empty($version)){
					throw (new Exception("Version number must no be empty"));
				}

				$this->_version	=	$version;

			}



			public function setStart($start=NULL){

				$this->log("Setting start offset to $start",0,"white");
				$this->_start	=	(int)$start;

			}


			public function setLanguage ($lang=NULL){

				if(empty($lang)||is_null($lang)){
					throw (new Exception("Search language must not be empty"));
				}

				$this->log("Setting language to $lang",0,"light_cyan");

				$this->_language	=	$lang;

			}

			public function setGoogleLicenseKey ($glKey=NULL) {

				if(empty($gLKey)||is_null($gLKey)){
					throw (new Exception("Google License Key must not be null or empty"));
				}

				$this->log("Setting license key to $glKey",0,"white");

				$this->siteKey	=	NULL;	 

			}


			public function setResultSize($rsz=NULL)	{

				switch (strtolower(trim($rsz))) {

					case 'big':
					case 'large':
						$this->log("Setting result size to $rsz",0,"white");
						return $this->rsz='large';
					break;

					case 'tiny':
					case 'small':
						$this->log("Setting result size to $rsz",0,"white");
						return $this->rsz='small';
					break;


					default:
						throw (new Exception("Invalid Result size provided, valid result sizes: {big,large}  | {tiny,small}"));
					break;

				}

				return FALSE;

			}

		}

	}

?>
