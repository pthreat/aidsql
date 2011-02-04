<?php

	namespace aidSQL\plugin\sqli {
		
		interface InjectionPluginInterface {

			public function getPluginAuthor();
			public function getPluginName();
			public function getSchemas();								//Has to return a DatabaseSchema Object
			public function getColumns($table=NULL);				//Separated from the original getSchema due to the --partial-schema opt
			public function getUser();
			public function getVersion();
			public function isVulnerable();
			public function isRoot($dbUser=NULL);
			public function setShellCode($shellCode=NULL);
			public function getShell(\aidSQL\core\PluginLoader &$ploader,\aidSQL\http\crawler &$crawler);
			public function setVerbose($boolean);
			public function getVerbose();
			public function setParser(\aidSQL\parser\ParserInterface &$parser);
			public function getParser();
			public function getAffectedDatabases();
			public function setConfig(Array $config);
			public static function getHelp(\aidSQL\core\Logger $logger);

		}

	}

?>
