<?php

	namespace aidSQL\core {

		class PluginLoader {
			
			private	$_logger						=	NULL;
			private	$_pluginsDir				=	NULL;
			private	$_plugins					=	array();
			private	$_infoLoadOrder			=	array();
			private	$_sqliLoadOrder			=	array();
			private	$_config						=	array();

			public function __construct($pluginsDir=NULL,\aidSQL\core\Logger &$log=NULL){

				if(!is_null($log)){
					$this->setLog($log);
				}

				if(is_null($pluginsDir)){
					$pluginsDir	=	__CLASSPATH."/plugin/";	
					return;
				}

				$this->setPluginsDir($pluginsDir);

			}

			public function setConfig(Array $config){

				if(isset($config["plugin-info-load-order"])){

					$this->setInfoPluginLoadOrder(explode(',',$config["plugin-info-load-order"]));

				}

				foreach($config as $opt=>$value){
					if(preg_match("#sqli-.*|info-.*#",$opt)){

						unset($config[$opt]);

						$type		=	substr($opt,0,$pos=strpos($opt,"-"));

						$option	=	substr($opt,strpos($opt,"-")+1);
						$plugin	=	substr($option,0,strpos($option,"-"));
						$option	=	substr($option,strpos($option,"-")+1);

						$config[$type][$plugin][$option]	=	$value;

					}

				}

				$this->_config	=	$config;

			}

			public function setInfoPluginLoadOrder(Array $order){
				$this->_infoLoadOrder	=	$order;
			}

			public function setSQLiPluginLoadOrder(Array $order){
				$this->_sqliLoadOrder	=	$order;
			}

			public function setPluginsDir($dir){

				$dir	=	rtrim($dir,"/");

				if(!is_dir($dir)){
					throw(new \Exception("Invalid plugins directory specified \"$dir\"!"));
				}

				if(!is_readable($dir)){
					throw(new \Exception("Plugins directory \"$dir\", cant be read, please chek permissions!"));
				}

				$this->_pluginsDir	=	$dir;

			}

			public function getPluginsDir(){
				return $this->_pluginsDir;
			}


			public function listPluginTypes(){

				$types	=	array_values($this->_list($this->_pluginsDir,"dirsnodots"));
				return array_map("basename",$types);

			}

			public function getPlugins(){
				return $this->_plugins;
			}

			private function _normalizePluginName($plugin){
				return substr($plugin,strrpos($plugin,"/")+1);
			}

			/**
			*Builds the whole plugin list of plugins of any type, it also validates plugin configurations
			*/

			public function listPlugins(){

				$plugins	=	array();
				$types	=	$this->listPluginTypes();

				$this->log("Building plugin list ...",0,"light_cyan");

				foreach($types as $t){

					$list	=	$this->_list($this->_pluginsDir.DIRECTORY_SEPARATOR.$t,"dirsnodots");

					if(!sizeof($list)){
						throw(new \Exception("No $t plugins found!"));
					}

					foreach($list as $plugin){

						$name			=	$this->_normalizePluginName($plugin);

						$this->log("Found $t => $name...",0,"white");

						$confFile	=	$plugin.DIRECTORY_SEPARATOR.strtolower($name).".conf.php";
						$iniFile		=	$plugin.DIRECTORY_SEPARATOR.strtolower($name).".ini";
						if(!file_exists($confFile)){
							throw(new \Exception("Config file not found for plugin \"$name\", if youre developing a plugin, please remember that *every* plugin should have a config file, no matter if its empty"));
						}
					
						if(!file_exists($iniFile)){
							throw(new \Exception("INI file not found for plugin \"$name\", every plugin needs to have a .ini file, no matter if its empty or not! in this case the .ini file should be named $name.ini"));
						}	

						include $confFile;

						//$config should now be defined by the included config file

						if(!isset($config)||!is_array($config)){
							throw(new \Exception("Malformed configuration file found for plugin \"$name\""));
						}

						$this->log("Parsing plugin configuration ...",0,"white");
						$parsedIni	=	parse_ini_file($iniFile);
						$iniCfg		=	array();

						foreach($parsedIni as $opt=>$value){
							$iniCfg[]	=	"--$opt=$value";
						}

						$confObj		=	new \aidSQL\parser\CmdLine($config,$iniCfg);
						$confObj->setConfig($config);
						$confObj->setCmdLineOptions($iniCfg);
						$plugin		.= DIRECTORY_SEPARATOR.ucwords($name).".class.php";

						$_plugin = array(
							"file"=>new \aidSQL\core\File($plugin),
							"name"=>$name,
							"type"=>$t,
							"config"=>$confObj->parse()
						);

						$plugins[]	=	$_plugin;

					}

				}

				if(sizeof($this->_infoLoadOrder)){
					$this->_arrangePluginOrder($plugins,$this->_infoLoadOrder,"info");
				}

				if(sizeof($this->_sqliLoadOrder)){
					$this->_arrangePluginOrder($plugins,$this->_sqliLoadOrder,"sqli");
				}

				return $this->_plugins	=	$plugins;

			}

			private function _arrangePluginOrder(&$plugins,$order,$type){

				$names		=	array();
				$newOrder	=	array();

				foreach($plugins as $plugin){

					if($plugin["type"]!==$type){
						continue;
					}

					$names[]	=	$plugin["name"];

				}

				foreach($order as $ord){
					if(!in_array($ord,$names)){
						throw(new \Exception("Invalid plugin specified into $type plugin load order \"$ord\"!"));
					}
				}

				unset($names);

				$this->log("Arranging testing order for $type plugins as in [".implode($order,', ').']',0,"white");

				foreach($order as $ord){

					foreach($plugins as $key=>$plugin){

						if($plugin["type"]!==$type){
							continue;
						}

						if($ord==$plugin["name"]){
							unset($plugins[$key]);
							$newOrder[]	=	$plugin;
						}

					}

				}

				$plugins	=	array_merge($newOrder,$plugins);

			}

			public function setLog(\aidSQL\core\Logger &$log){
				$this->_logger	=	$log;
			}

			private function log($msg = NULL,$color="white",$level=0,$toFile=FALSE){

				if(isset($this->_config["log-all"])){
					$toFile	=	TRUE;
				}

				if(!is_null($this->_logger)){

					$this->_logger->setPrepend('['.__CLASS__.']');
					$this->_logger->log($msg,$color,$level,$toFile);
					return TRUE;

				}

				return FALSE;

			}

			public function load (Array $plugin){

				if(!isset($plugin["file"])&& !is_a($plugin["file"],"\\aidSQL\\core\\File")){

					throw(new \Exception("Couldnt load plugin because it doesnt contains a valid \\aidSQL\\core\\File instance!"));

				}

				$fileObj	=	$plugin["file"];
				$load		=	$fileObj->getFile();
				$index	=	$this->getPluginIndex($plugin["type"],$plugin["name"]);

				if($index===FALSE){
					throw(new \Exception("ERROR OBTAINING PLUGIN INDEX!"));
				}

				$pluginConstant	=	"__aidSQL_PLUGIN_".strtoupper($plugin["type"]).
				'_'.strtoupper($plugin["name"])."__";

				if(defined($pluginConstant)){

					$this->log("Load $plugin[type] => $plugin[name] ... PVL ",0,"light_green");
					return TRUE;
					
				}

				if(include $load){

					$this->log("Load $plugin[type] => $plugin[name] ... OK ",0,"light_green");
					define($pluginConstant,TRUE);
					return TRUE;

				}

				$this->log("Load $plugin[type] => $plugin[name] ... ERROR!",0,"red");
				return FALSE;

			}

			private function getPluginIndex($type,$name){

				foreach($this->_plugins as $key=>$plugin){

					if($plugin["name"]==$name && $plugin["type"]==$type){
						return $key;
					}

				}

				return FALSE;

			}

			private function isValidPlugin(Array $plugin=array()){

				$type =	key($plugin);
				$name	=	$plugin[$type];

				if(isset($this->_plugins[$type])){
					foreach($this->_plugins[$type] as $plugin){
						if($plugin["name"]==$name){
							return TRUE;
						}
					}
				}

				return FALSE;

			}

			//name must be the normalized name
			public function getPluginInstance($type,$name,\aidSQL\http\Adapter &$httpAdapter,\aidSQL\core\Logger &$log=NULL){

				if(empty($name)||empty($type)){
					throw(new \Exception("Must specify normalized plugin name and type when using getPlugin!"));
				}

				foreach($this->_plugins as $plugin){ 

					if($plugin["type"]!=$type||$plugin["name"]!==$name){ 
						continue; 
					} 

					if($this->load($plugin)===FALSE){
						throw(new \Exception("Cant get instance of plugin $type => $name, plugin doesnt exists!"));
					}

					$args	=	func_get_args();
					unset($args[0]);
					unset($args[1]);
	
					$pluginName	=	"aidSQL\\plugin\\$type\\$name";

					$name	=	strtolower($name);

					if(isset($this->_config[$type][$name])){

						$pluginOptions	=	$this->_config[$type][$name];
						$config			=	array_merge($plugin["config"],$pluginOptions);

					}else{

						$config	=	$plugin["config"];

					}

					$config["all"]	=	$this->_config;

					return new $pluginName($httpAdapter,$config,$log);

				}

			}

			private function _list($dir,$what="all"){

				$dir		=	rtrim($dir,"/").DIRECTORY_SEPARATOR;

				if(!is_dir($dir)){
					throw(new \Exception("Invalid directory specified $dir!"));
				}

				$dp		=	opendir($dir);
				$files	=	array();

				while ($file=readdir($dp)){

					$cFile	=	$dir.$file;	//Complete file

					switch($what){

						case "files":
							if(!is_dir($cFile)){
								$files[]	=	$cFile;
							}
						break;

						case "filesnodots":
							if(!is_dir($cFile) && !preg_match("#^\.#",$file)){
								$files[]	=	$cFile;
							}
						break;

						case "dirs":
							if(is_dir($cFile)){
								$files[]	=	$cFile;
							}
						break;

						case "dirsnodots":

							if(is_dir($cFile) && !preg_match("#^\.#",$file)){
								$files[]	=	$cFile;
							}
						break;

						case "all":
							$files[]	=	$cFile;
							break;

					}

				}

				closedir($dp);

				return $files;

			}

		}
	
	}

?>
