<?php

	namespace aidSQL\parser{

		class Generic implements ParserInterface {

			private	$_openTag	=	"";
			private	$_closeTag	=	"";
			private	$_logger		=	NULL;

			public function setOpenTag($openTag,$hexEncode=FALSE){

				if($hexEncode){
					$this->_openTag	=	\String::hexEncode($openTag);
				}else{
					$this->_openTag	=	$openTag;
				}

			}

			public function setCloseTag($closeTag,$hexEncode=FALSE){

				if($hexEncode){
					$this->_closeTag	=	\String::hexEncode($closeTag);
				}else{
					$this->_closeTag	=	$closeTag;
				}


			}

			public function setLog(\aidSQL\core\Logger &$log){
				$this->_logger	=	$log;
			}

			private function log($msg=NULL,$color="white",$level=0,$toFile=FALSE){

				if(is_null($this->_logger)){
					return FALSE;
				}

				$this->_logger->setPrepend('['.__CLASS__.']');
				$this->_logger->log($msg,$color,$level,$toFile);

				return TRUE;

			}

			public function analyze($content){

				$this->log("String identifier is: ".$this->_openTag." - ".$this->_closeTag,0,"white");

				$regex	= '/'.$this->_openTag."+.*".$this->_closeTag.'/';
				$matches = NULL;

				preg_match_all($regex,$content,$matches,PREG_SET_ORDER);

				if(sizeof($matches)){

					$matching = array();

					foreach($matches as $key=>$match){

						if(empty($match)){
							continue;
						}

						$match=$match[0];
						$match = preg_replace("/^({$this->_openTag})/",'',$match);
						$match = preg_replace("/({$this->_closeTag})/",'',$match);
						$matching[$key]=$match;

					}

					return $matching;

				}

				return FALSE;

			}

		}

	}

?>
