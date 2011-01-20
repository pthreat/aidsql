<?php

	namespace aidSQL\plugin\sqli {
		
		interface InjectionPluginInterface {

			public function getPluginName();
			public function setLog(\aidSQL\core\Logger &$log);	//Log setter
			public function log($msg=NULL);							//Log wrapper
			public function setTable();
			public function getSchema();								//Has to return a DatabaseSchema Object
			public function getColumns($table=NULL);				//Separated from the original getSchema due to the --partial-schema opt
			public function getDatabase();
			public function getUser();
			public function getVersion();
			public function isVulnerable();
			public function isRoot($dbUser=NULL);
			public function setShellCode($shellCode=NULL);
			public function getShell(\aidSQL\core\PluginLoader &$ploader,\aidSQL\http\crawler $crawler, Array $options);
			public function setAffectedURLVariable($var);
			public function getAffectedURLVariable();
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
