<?php

	namespace aidSQL\core {

		class Runner{

			private	$_debug			=	TRUE;
			private	$_vulnerable	=	FALSE;		//boolean vulnerable TRUE or not vulnerable FALSE
			private	$_logger			=	NULL;			//Log object
			private	$_httpAdapter	=	NULL;
			private	$_options		=	array();
			private	$_pLoader		=	NULL;			//Plugin Loader Instance
			private	$_crawler		=	NULL;			//This object might contain important information that 
																//may be used by the plugins.

			public function __construct(\aidSQL\parser\CmdLine $parser,\aidSQL\http\Adapter &$adapter,\aidSQL\http\crawler &$crawler,\aidSQL\core\Logger &$log=NULL,\aidSQL\core\PluginLoader &$pLoader){

				$this->_pLoader	=	$pLoader;

				if(!is_null($log)){
					$this->setLog($log);
				}

				$options				=	$parser->getParsedOptions();

				foreach($options as $opt=>$value){

					if(preg_match("#sqli-.*|info-.*#",$opt)){

						unset($options[$opt]);

						$type	=	substr($opt,0,$pos=strpos($opt,"-"));

						$option										=	substr($opt,strpos($opt,"-")+1);
						$plugin										=	substr($option,0,strpos($option,"-"));
						$option										=	substr($option,strpos($option,"-")+1);

						$options[$type][$plugin][$option]	=	$value;

					}

				}


				$this->_options	=	$options;
				$this->_pLoader->setConfig($options);
			
				$this->setHttpAdapter($adapter);
				$this->configureHttpAdapter($options);
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


			public function isVulnerable(){

				if($this->_vulnerable){
					return TRUE;
				}

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

							$this->_plugin			= $plugin;
							$this->_vulnerable	= TRUE;

							if(!$this->_options["verbose"]){
								echo "\n";
							}

							return TRUE;

						}

						unset($this->_plugin);

						$this->log("Not vulnerable to this plugin ...");

					}catch(\Exception $e){

						$this->log($e->getMessage(),1,"red");
						return FALSE;

					}


				}


			}

			/*
			*Generates a full report 
			*combines common vulnerability methods provided by the injection plugin interface
			*/

			public function generateReport(){
	
				if(is_null($this->_vulnerable)){

					$msg = "Site seems not to be vulnerable or has not been checked for being vulnerable, cannot generate report";
					throw(new Exception($msg));

				}


				try{	

					$plugin		=	$this->_plugin;
					$db			=	$plugin->getDatabase();
					$dbUser		=	$plugin->getUser();
					$dbVersion	=	$plugin->getVersion();


					$this->log("BASIC INFORMATION",0,"cyan",TRUE);
					$this->log("---------------------------------",0,"white",TRUE);
					$this->log("PLUGIN\t\t:\t".$plugin->getPluginName(),0,"cyan",TRUE);
					$this->log("DBASE\t\t:\t$db",0,"white",TRUE);
					$this->log("VERSION\t\t:\t$dbVersion",0,"white",TRUE);
					$this->log("USER\t\t:\t$dbUser",0,"white",TRUE);

					if(!in_array("no-schema",array_keys($this->_options))){

						$dbSchema	=	$plugin->getSchema();

						if(!is_a($dbSchema,"\\aidSQL\\core\\DatabaseSchema")){

							throw(new \Exception("The getSchema method for your plugin must return a \\aidSQL\\core\\DatabaseSchema Object!"));
						}

						$tables		=	$dbSchema->getSchema();

						if(sizeof($tables)){

							if(!in_array("partial-schema",array_keys($this->_options))){

								$tables	=	array_keys($tables);

								foreach($tables as $table){

									$columns	=	$plugin->getColumns($table);

									if(!sizeof($columns)){

										$this->log("Unable to fetch schema for table $table",2,"yellow");
										$columns	=	array("Unable to fetch schema for this table");

									}

									$dbSchema->addTable($table,$columns);

								}

								if(!$this->_options["verbose"]){
									echo "\n";
								}

								$this->log("COMPLETE DATABASE SCHEMA",0,"light_cyan",TRUE);
								$this->log("-----------------------------------------------------------------",0,"light_cyan",TRUE);
								
							}else{

								$this->log("PARTIAL DATABASE SCHEMA",0,"yellow",TRUE);
								$this->log("-----------------------------------------------------------------",0,"yellow",TRUE);

							}

							$schema	=	$dbSchema->getSchema();

							foreach($schema as $table=>$columns){

								if(sizeof($columns)){

									$this->log("TABLE $table\t:\t".implode(',',$columns),0,"light_green",TRUE);

								}else{

									$this->log("TABLE $table\t:\t(remove --partial-schema option to see columns)",0,"light_green",TRUE);

								}

							}

						}else{

							$this->log("Couldnt fetch database Schema :(",0,"yellow");

						}
	
					}else{

						$this->log("Skipping database schema fetching by user request",2,"yellow");

					}
	

					if(!in_array("no-shell",array_keys($this->_options))){

						if($plugin->isRoot($dbUser)){

							$this->log("IS ROOT\t:\tYES",0,"light_green",TRUE);
							$this->log("Trying to get Shell ...",1,"light_cyan",TRUE);


							$shellName	=	(isset($this->_options["shell-name"])) ? $this->_options["shell-name"] : NULL;

							$plugin->setShellName($shellName);

							if($plugin->setShellCode($this->_options["shell-code"])){

								//Getshell method must return FALSE on error or String path/to/shellLocation
								$g0tShell = $plugin->getShell($this->_pLoader,$this->_crawler,$this->_options);

								if($g0tShell){
									$this->log("Got Shell => $g0tShell",0,"light_green",TRUE);
								}else{
									$this->log("Couldn't get shell :(",2,"yellow",TRUE);
								}

							}

						}else{
				
							$this->log("IS ROOT\t:\tNO",0,"white",TRUE);

						}

						return TRUE;

					}else{

						$this->log("Not trying to get a shell since the user has specified he doesnt wants to",0,"yellow");

					}

				}catch(\Exception $e){

					$this->log($e->getMessage(),1,"red",TRUE);
					return FALSE;

				}

				unset($this->_plugin);

				return TRUE;

			}

			/**
			*Loads, instantiates, configures http object
			*/

			private function configureHttpAdapter(Array $options){
				
				$this->_httpAdapter->setMethod($options["http-method"]);
				
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
