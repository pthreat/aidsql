<?php

	namespace aidSQL\db {

		class MySQLDbBuilder {

			private	$_xmlFile		=	NULL;
			private	$_config			=	NULL;
			private	$_logger			=	NULL;
			private	$_httpAdapter	=	NULL;
			private	$_pLoader		=	NULL;

			public function __construct(Array $config,\aidSQL\http\adapter &$httpAdapter,\aidSQL\core\PluginLoader &$pLoader,\aidSQL\core\Logger &$log){
	
				$this->_xmlFile		=	new \aidSQL\core\File($config["makedb"]);
				$this->_logger			=	$log;	
				$this->_httpAdapter	=	$httpAdapter;
				$pLoader->setConfig($config);
				$this->_pLoader		=	$pLoader;

				$this->parseXML();
				$this->makeDB();
	
			}

			public function setConfig(Array $config){
				$this->_config	=	$config;
			}

			public function parseXML(){

				$dom	=	new \DomDocument("1.0");
				$dom->load($this->_xmlFile->getFile());

				$host				=	$dom->getElementsByTagName("host")->item(0)->nodeValue;
				$link				=	$dom->getElementsByTagName("vulnlink")->item(0)->nodeValue;
				$domInjection	=	$dom->getElementsByTagName("injection")->item(0)->childNodes;
				$injection		=	array();

				foreach($domInjection as $inject){

					$nodeName		=	$inject->nodeName;
					$injectChilds	=	$inject->childNodes;

					foreach($injectChilds as $injectChild){

						if(sizeof($injectChild->childNodes)>0){
							$injection[$nodeName][]	=	$injectChild->nodeValue;
						}else{
							$injection[$nodeName]	=	$injectChild->nodeValue;
						}

					}

				}

				$url	=	new \aidSQL\core\Url($link);
				$url->addRequestVariable($injection["requestVariable"],$injection["requestValue"]);

				foreach($injection["requestVariables"] as $name=>$value){

					$url->addRequestVariable($name,$value);

				}

				$this->_httpAdapter->setUrl($url);

				$plugin			=	$this->_pLoader->getPluginInstance("sqli","mysql5",$this->_httpAdapter,$this->_logger);
				$plugin->injectionUnionWithConcat();
				var_dump($plugin->unionQueryIterateLimit("authority.users.username","authority.users"));

			}

			public function makeDb(){

			}

		}

	}

?>
