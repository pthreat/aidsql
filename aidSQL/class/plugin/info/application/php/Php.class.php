<?php

	namespace aidSQL\plugin\info{

	//Perhaps good for checking http headers ??!?!?!
	//We could also check if there are PHP sessions
	//etc.
	//Could also check for a phpinfo file laying around the server :D
	//This way we could parse it, now where the application is hosted, precisely! :))
	

		class Php extends InfoPlugin {

			public function getInfo(){
				$this->log("Should get PHP info and stuff, try to find phpinfo perhaps in paths provided by the crawler etc",0,"yellow");
			}

			public static function getHelp(\aidSQL\core\Logger $logger){
				$logger->log(__CLASS__. " HELP");
			}

		}

	}

?>
