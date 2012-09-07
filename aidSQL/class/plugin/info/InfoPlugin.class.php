<?php

	namespace aidSQL\plugin\info {

		abstract class InfoPlugin implements InfoPluginInterface {

			protected	$_httpAdapter	=	NULL;
			private		$_log				=	NULL;
			protected	$_config			=	NULL;

			final public function __construct(\aidSQL\http\Adapter &$httpAdapter,Array $config,\aidSQL\core\Logger &$log=NULL){

				$this->setHttpAdapter($httpAdapter);

				if(!is_null($log)){
					$this->setLog($log);
				}

				$this->setConfig($config);

			}

			public function setConfig(Array $config){
				$this->_config	=	$config;
			}

			public function setHttpAdapter(\aidSQL\http\Adapter &$httpAdapter){
				$this->_httpAdapter	=	$httpAdapter;
			}

			public function setLog(\aidSQL\core\Logger &$log){
				
				$this->_log = $log;

			}


			protected function log($msg=NULL,$color="white",$level=0,$toFile=FALSE){

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

		}

	}

?>
