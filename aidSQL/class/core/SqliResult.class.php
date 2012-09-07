<?php

	namespace aidSQL\plugin\sqli{

		class SQLiResult{

			private	$_host					=	NULL;
			private	$_httpAdapterName		=	NULL;
			private	$_pluginDetails		=	NULL;
			private	$_databaseDetails		=	NULL;
			private	$_affectedUrl			=	NULL;
			private	$_vulnerableUrl		=	NULL;
			private	$_requestVariables	=	NULL;
			private	$_modRewrite			=	FALSE;

			public function setHost($host=NULL){

				if(empty($host)){
					throw(new \Exception(__FILE__.': '.__FUNCTION__.'|'.__LINE__.' $host cant be empty'));
				}

				$this->_host	=	$host;

			}

			public function setDatabaseDetails(\Array $databaseDetails){

				$this->_databaseDetails	=	$databaseDetails;	

			}

			public function setModRewrite($boolean=NULL){

				$msg	=	__FILE__.': '.__FUNCTION__.'|'.__LINE__."\n";

				if(is_null($boolean)){
					$msg.="Method takes a boolean argument, should be either TRUE or FALSE";
					throw(new \Exception($msg));
				}

				$this->_modRewrite($boolean);

			}

			public function setHttpAdapter(\aidSQL\http\Adapter $adapter){

				$this->_httpAdapter	=	Array(
														"name"=>$adapter->getHttpAdapterName(),
														"version"=>$adapter->getHttpAdapterVersion()
				);

			}

			public function setVulnerableUrl(\aidSQL\parser\Url $url){
				$this->_vulnerableUrl	=	$vulnerableUrl;
			}

			public function setRequestVariables(Array $requestVariables){

				$msg	=	__FILE__.': '.__FUNCTION__.'|'.__LINE__."\n".
				"When setting request variables you must respect ".
				" the following data structure Array('name'=>name,'value'=>value,affected=>1)". 
				"the affected should be 1 affected 0 not affected or -1 not tested.".
				"DEBUG: ".var_export($requestVariables,TRUE);

				foreach($requestVariables as $rVarName=>$rVarData){

					if(!isset($rVarData["affected"])||!isset($rVarData["name"])||!isset($rVarData["value"])){
						throw(new \Exception($msg));

					}

					switch($rVarData["value"]){

						case 0:
						case 1:
						case -1:
							break;
						default:
							throw(new \Exception($msg));
						break;

					}

				}

				$this->_requestVariables	=	$requestVariables;
			}

			public function setDatabaseUser($user){
				$this->_databaseUser	=	$user;
			}

			public function setPluginDetails(\Array $pluginDetails){
				$this->_pluginDetails	=	$pluginDetails;
			}

		}

	}

?>
