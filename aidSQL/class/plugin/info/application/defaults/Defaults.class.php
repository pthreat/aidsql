<?php

	namespace aidSQL\plugin\info {

		class Defaults extends InfoPlugin {

			public function getInfo(){

				$oldUrl	=	clone($this->_httpAdapter->getURL());

				$return	=	new \aidSQL\plugin\info\InfoResult();
	
				$this->log("Trying to discover default directory locations ...",0,"light_cyan");

				$webDirectories		=	explode(',',$this->_config["web-directories"]);

				$possiblyWritable		=	array();

				if(!sizeof($webDirectories)){
					throw(new \Exception("Cant use defaults plugin with no web directories default location list!"))	;
				}

				$url	=	$this->_httpAdapter->getUrl();
				$url	=	$url->getScheme()."://".$url->getHost();

				foreach($webDirectories as $dir){

					$this->log("$url/$dir ...",0,"white");
					$this->_httpAdapter->setUrl(new \aidSQL\parser\Url($url.'/'.$dir));
					$this->_httpAdapter->fetch();

					$httpCode	=	$this->_httpAdapter->getHttpCode();

					if($httpCode==200||$httpCode==403){

						$possiblyWritable[]	=	$dir;
						$this->log("Found possible web writable directory $dir, got $httpCode!",0,"light_green");

					}

				}

				$return->setWebDirectories($possiblyWritable);
				$return->setUnixDirectories(explode(',',$this->_config["unix-directories"]));
				$return->setWindowsDirectories(explode(',',$this->_config["win-directories"]));

				if(!sizeof($possiblyWritable)){

					$this->log("No possible default writable web path was found :(",2,"yellow");

				}

				$this->_httpAdapter->setUrl($oldUrl);

				return $return;

			}

			public static function getHelp(\aidSQL\core\Logger $logger){
				$logger->log("--info-defaults-web-directories\t\tComma delimited list of web directories to check i.e: tempates_c,tmp,public");
				$logger->log("--info-unix-directories\t\t\tComma delimited list of unix directories to check i.e: /var/www/");
				$logger->log("--info-win-directories\t\t\t\tComma delimited list of windows directories to check i.e: c:\inetpub\www");
			}

		}

	}

?>
