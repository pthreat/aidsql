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

				$dom		=	new \DomDocument();
				@$dom->loadHTML($content);
				$tagName	=	preg_replace("#<|>#","",$this->_openTag);
				$tags		=	$dom->getElementsByTagName(preg_replace('/\<|\>/','',$this->_openTag));
				$dom->strictErrorChecking	=	FALSE;

				if($tags->length > 0){

					$matches	=	array();

					foreach($tags as $tag){

						$matches[]	=	$tag->nodeValue;

					}

					return $matches;	

				}

				return FALSE;
				
			}

		}

	}

?>
