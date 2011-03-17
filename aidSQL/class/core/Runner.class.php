<?php

	namespace aidSQL\core {

		class Runner{

			private	$_debug					=	TRUE;
			private	$_vulnerable			=	FALSE;		//boolean vulnerable TRUE or not vulnerable FALSE
			private	$_logger					=	NULL;			//Log object
			private	$_httpAdapter			=	NULL;
			private	$_options				=	array();
			private	$_pLoader				=	NULL;			//Plugin Loader Instance
			private	$_crawler				=	NULL;			//This object might contain important information that 
																//may be used by the plugins.

			public function __construct(\aidSQL\parser\CmdLine $parser,\aidSQL\http\Adapter &$adapter,\aidSQL\http\crawler &$crawler,\aidSQL\core\Logger &$log=NULL,\aidSQL\core\PluginLoader &$pLoader){

				$this->_pLoader	=	$pLoader;

				if(!is_null($log)){
					$this->setLog($log);
				}

				$this->_options	=	$parser->getParsedOptions();
				$this->_pLoader->setConfig($this->_options);
			
				$this->setHttpAdapter($adapter);
				$this->configureHttpAdapter($this->_options);
				$this->setCrawler($crawler);

			}


			public function setCrawler(\aidSQL\http\crawler &$crawler){
				$this->_crawler	=	$crawler;
			}

			public function setHttpAdapter(\aidSQL\http\Adapter &$httpAdapter){

				$this->_httpAdapter = $httpAdapter;

			}


			public function getHttpAdapter(){

				return $this->_httpAdapter;

			}


			public function setLog(\aidSQL\core\Logger &$log){
				$this->_logger = $log;
			}


			private function log($msg=NULL,$color="white",$level=0,$toFile=FALSE){

				if(isset($this->_options["log-all"])){
					$toFile	=	TRUE;
				}

				if(!is_null($this->_logger)){

					$this->_logger->setPrepend('['.__CLASS__.']');
					$this->_logger->log($msg,$color,$level,$toFile);
					return TRUE;

				}

				return FALSE;

			}

			public function isVulnerableToSQLInjection(){

				$plugins	=	$this->_pLoader->getPlugins();

				if(!sizeof($plugins)){

					throw (new \Exception("No plugins found!"));

				}

				foreach($plugins as $plugin){

					if($plugin["type"]!="sqli"){
						continue;
					}

					try{

						$plugin	=	$this->_pLoader->getPluginInstance("sqli",$plugin["name"],$this->_httpAdapter,$this->_logger);

						$this->log("Testing ".get_class($plugin)." sql injection plugin...",0,"white");

						if($plugin->isVulnerable()){

							return $plugin;

						}

						unset($plugin);

						$this->log("Not vulnerable to this plugin ...");

					}catch(\Exception $e){

						$this->log($e->getMessage(),1,"red");
						return FALSE;

					}

					return FALSE;

				}


			}

			/**
			*Loads, instantiates, configures http object
			*/

			private function configureHttpAdapter(Array $options){
				
				if(!empty($options["proxy-server"])){

					$this->_httpAdapter->setProxyServer($options["proxy-server"]);
					$this->_httpAdapter->setProxyPort($options["proxy-port"]);
					$this->_httpAdapter->setProxyType($options["proxy-type"]);

				}

				if(!empty($options["proxy-auth"])){

					$this->_httpAdapter->setProxyAuth($options["proxy-auth"]);
					$this->_httpAdapter->setProxyUser($options["proxy-user"]);
					$this->_httpAdapter->setProxyPassword($options["proxy-password"]);

				}

			}

			public function setAffectedParameter($parameter){

				$this->_affectedParameter = $parameter;

			}

			private function addInjectionPlugin(\aidSQL\plugin\sqli\InjectionPluginInterface $plugin){

				$this->log("Adding injection plugin $plugin");
				$this->_injectionPlugins[] = $plugin;

			}

		}

	}

?>
