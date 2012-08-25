<?php

	namespace aidSQL\plugin\info {

		class Apache extends InfoPlugin {

			public function getInfo(){

				if(!class_exists("\\aidSQL\\http\\Fuzzer")){	//This shouldnt be here, its just a temporary fix

					$class	=	 __CLASSPATH."class".DIRECTORY_SEPARATOR."http".DIRECTORY_SEPARATOR."Fuzzer.class.php";
					require $class;

				}

				$fuzzer	=	new \aidSQL\http\Fuzzer($this->_httpAdapter,$log);

				$info		=	$fuzzer->generate404(); //Attempt to generate a 404 request

				$banner	=	NULL;

				if($info["http_code"]==200){

					$info["mod_rewrite"] = TRUE;

					//Try some extensions that are probably avoided by .htaccess directives in the server :D
					$extensions	=	array("jpeg","html","php","phtml","cgi");

					foreach($extensions as $ext){

						$info	=	$fuzzer->generate404(".".$ext);	//Try to generate 404 (URI Length exceeded)

						if($info["http_code"]>=400){
							$banner	=	$info["error"];
						}

					}

				}else{

					if($info["http_code"]>=400){
						$banner	=	$info["error"];
					}

				}	

				if(empty($banner)){
					$info	=	$fuzzer->generate414();	//Try to generate 414 (URI Length exceeded)
				}

				$info["error"]	=	$this->parseError($info["error"]);

				$apacheInfo	=	array();

				if($info["error"]){

					$this->log("Got banner $info[error]",0,"light_green");

					if(preg_match("#apache#i",$info["error"])){

						$this->log("Server *seems* to be Apache",0,"light_green");
						$apacheInfo["version"]	=	$this->getApacheVersion($info["error"]);
						$apacheInfo["os"]			=	$this->getOperatingSystem($info["error"]);

					}else{

						$this->log("Unfortunately this server seems not to be Apache :(",2,"yellow");

					}

				}else{

					$this->log("Couldnt disclose any information regarding to Apache :/",2,"yellow");

				}

				return $apacheInfo;

			}

			public function getApacheVersion($info){

				$token	=	strtok($info," ");
				$version	=	FALSE;

				while($token!==FALSE){

					$pos	=	strpos($token,"/");

					if($pos!==FALSE){

						$version	=	substr($token,$pos+1);
						break;

					}

					$token = strtok(" ");

				}
	
				return $version;

			}

			public function getOperatingSystem($info){

				$token	=	strtok($info," ");
				$OS		=	FALSE;

				while($token!==FALSE){

					$pos	=	strpos($token,"(");
					$pos2	=	strpos($token,")");

					if($pos!==FALSE&&$pos2!==FALSE){

						$OS	=	substr($token,$pos+1,$pos2-1);
						break;

					}

					$token = strtok(" ");

				}
	
				return $OS;

			}


			private function parseError($errorHTML){

				$item		=	FALSE;
				$dom		=	new \DomDocument();

				$dom->loadHTML($errorHTML);
				
				$server	=	$dom->getElementsByTagName("address");

				if($server->length){

					$item	=	$server->item(0)->nodeValue;

				}

				return $item;

			}

			public static function getHelp(\aidSQL\core\Logger $logger){
				$logger->log(__CLASS__. " HELP!");
			}

		}

	}

?>
