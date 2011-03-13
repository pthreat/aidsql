<?php

	namespace aidSQL\http{

		class ProxyHandler {

			private	$_logger				=	NULL;
			private	$_httpAdapter		=	NULL;
			private	$_ipUrl				=	NULL;	//An ip that will just tell you your ip AKA echo $_SERVER["REMOTE_ADDR"]
			private	$_proxyList			=	array();
			private	$_revalidateOnGet	=	FALSE;

			public function __construct(\aidSQL\http\Adapter $adapter,\aidSQL\core\Logger &$log, $ipUrl="http://cfaj.freeshell.org/ipaddr.cgi"){

				$this->setLog($log);

				if(!is_null($ipUrl)){

					$this->setIpUrl($ipUrl);

				}

				$this->setHttpAdapter($adapter);

			}

			public function setProxyFile(\aidSQL\core\File $file){
				echo $file;
			}

			public function revalidateOnGet($revalidate=TRUE){

				$this->_revalidateOnGet	=	$revalidate;

			}

			public function setIpUrl($ipUrl){

				$this->_ipUrl	=	new \aidSQL\core\Url($ipUrl);

			}

			public function setHttpAdapter(\aidSQL\http\Adapter $adapter){

				$adapter	=	clone($adapter);
				$adapter->setUrl($this->_ipUrl);
				$adapter->setConnectRetry(1);
				$adapter->setConnectTimeout(10);
				$adapter->setTimeout(15);

				$this->_httpAdapter = $adapter;

			}

			public function setLog(\aidSQL\core\Logger &$log){
				$this->_logger = $log;
			}

			private function log($msg = NULL,$color="white",$type="0",$toFile=FALSE){

				if(!is_null($this->_logger)){

					$this->_logger->setPrepend("[".get_class($this)."]");
					$this->_logger->log($msg,$color,$type);
					return TRUE;

				}

				return FALSE;

			}

			public function checkProxyList($file=NULL,$shuffle=FALSE){

				$file		=	new \aidSQL\core\File($file);
				$proxies	=	$file->getContentsAsArray();

				if(!sizeof($proxies)){
					throw(new \Exception("Empty proxy file specified!"));
				}

				$this->log("Validating proxy list | (".count($proxies).") proxies found",0,"light_cyan");

				if($shuffle){
					shuffle($proxies);
				}

				foreach($proxies as $proxy){

					$proxy	=	explode(':',$proxy);
					$port		=	(isset($proxy[1])&&is_int($proxy[1])) ? $proxy[1]	:	80;
					$proxy	=	$proxy[0];

					//Do host/ip validation  etc,etc

					$isValid	=	$this->checkProxy($proxy,$port);

					if($isValid){

						$this->log("Found valid proxy $proxy:$port!",0,"light_green");

					}else{

						$this->log("Invalid proxy $proxy:$port!",1,"red");

					}

				}

			}

			public function checkProxy($proxy,$port=80){

				$this->_httpAdapter->setProxyServer($proxy);
				$this->_httpAdapter->setProxyPort($port);

				try{	

					$contents	=	trim($this->_httpAdapter->fetch());
					var_dump($contents);
					if($contents==$proxy){

						$tmpProxy	=	array("proxy"=>$proxy,"port"=>$port,"valid"=>TRUE);

					}else{

						$tmpProxy	=	array("proxy"=>$proxy,"port"=>$port,"valid"=>FALSE);

					}

					if(sizeof($this->_proxyList)){

						foreach($this->_proxyList as &$proxy){

							if($proxy["proxy"] == $tmpProxy["proxy"]){
								$proxy	=	$tmpProxy;
							}

						}

					}else{

						$this->_proxyList[]	=	$tmpProxy;

					}

					return $tmpProxy["valid"];

				}catch(\Exception $e){

					$this->log($e->getMessage(),1,"red");
				}

			}

			public function getValidProxy(){

				foreach($this->_proxyList as $proxy){

					if($proxy["valid"]){

						if($this->_revalidateOnGet){

							if(!$this->checkProxy($proxy["proxy"],$proxy["port"])){

								continue;

							}else{

								return $proxy;

							}

						}else{

							return $proxy;

						}

					}

				}

			}

			public function getAllProxies(){
				return $this->_proxyList;
			}

		}

	}
?>
