<?php

	namespace aidSQL\plugin\info {

		class InfoResult {
				
			private	$_info	=	array();

			public function __call($method,$args){	

				$method		=	strtolower($method);
				$setGet		=	substr($method,0,3);
				$property	=	substr($method,3);

				switch($setGet){

					case "set":

						if(sizeof($args)>1){

							$this->_info[$property]	=	$args;

						}else{

							$this->_info[$property]	=	$args[0];

						}

						break;

					case "get":
						if(!isset($this->_info[$property])){
							return NULL;
						}

						return $this->_info[$property];
						break;

				}

			}

		}

	}

?>
