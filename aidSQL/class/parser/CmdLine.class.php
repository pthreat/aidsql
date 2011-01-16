<?php

	namespace aidSQL\parser {

		class CmdLine {

			private	$_options				=	array();
			private	$_cmdLineOptions		=	array();
			private	$_parsedOptions		=	array();
			private	$_ignoreOptionsWith	=	array();

			public function setConfig(Array $optArray){
				$this->_options = $optArray;
			}

			public function setIgnoreOptionsWith(Array $regex){
				$this->_ignoreOptionsWith	=	$regex;
			}

			public function setCmdLineOptions(Array $options){

				$this->_cmdLineOptions = $options;

			}

			public function parse(){

				$this->parseOptions();
				$this->optionOverlapsOption();
				$this->optionRequiresOption();
				$this->parseRequiredOptions($this->_parsedOptions);

				return $this->_parsedOptions;

			}

			public function setOption($option=NULL,$value){

				try{			

					$this->validateOption($option,$value);
					$this->_parsedOptions[$option]=$value;	
					return TRUE;

				}catch(\Exception $e){

					echo $e->getMessage();
					return NULL;

				}

			}

			private function parseOptions(){

				for($i=0;isset($this->_cmdLineOptions[$i]);$i++){

					$rawOption  = $this->_cmdLineOptions[$i];
					$position	= strpos("-",$rawOption);
					$opt			= substr($rawOption,$position);
					$optSplit	= str_split($opt);

					if($optSplit[0]=='-' && $optSplit[1]=='-'){	//long option

						$this->parseLongOption($optSplit);

					}

					if($optSplit[1]!='-'){

						$i = $this->parseShortOption($optSplit,$i);

					}

				}


			}

			public function getParsedOptions(){
				return $this->_parsedOptions;
			}	

			private function parseShortOption($optSplit,$position){

				$offset = 1;

				$realOption			= $optSplit[1];
				$realOptionValue	= NULL;

				$next = NULL;

				if(isset($this->_cmdLineOptions[$position+1])){
					$next = $this->_cmdLineOptions[$position+1];
				}

				if(preg_match("/\-/",$next)){
					$realOptionValue = NULL;
				}else{
					$realOptionValue = $next;
					$position++;
				}

				$this->validateOption($realOption,$realOptionValue);

				return $position;

			}

			private function parseLongOption(Array $optSplit){

				$offset = 2;

				$realOption = array();

				for(;isset($optSplit[$offset]);$offset++){
					$realOption[] = $optSplit[$offset];	
				}

				$realOption			= implode($realOption);
				$realOptionValue	= NULL;

				if(strpos($realOption,"=")){
					$realOptionValue	= substr($realOption,strpos($realOption,"=")+1);
					$realOption			= substr($realOption,0,strpos($realOption,"="));
				}

				$this->validateOption($realOption,$realOptionValue);

				return TRUE; 

			}


			private function validateOption($realOption,$realOptionValue){

				if(sizeof($this->_ignoreOptionsWith)){

					foreach($this->_ignoreOptionsWith as $regex){

						if(preg_match("#$regex#",$realOption)){

							$this->_parsedOptions[$realOption]=$realOptionValue;
							return TRUE;

						}

					}

				}

				if(!$this->isValidOption($realOption)){
					throw(new \Exception("Unknown option specified \"$realOption\""));
				}

				$valueRequired = $this->optionRequiresValue($realOption);

				if($valueRequired&&is_null($realOptionValue)){
					throw(new \Exception("Option $realOption requires value"));
				}

				if(!$valueRequired && !is_null($realOptionValue)){
					throw(new \Exception("Option $realOption requires no value"));
				}	  

				if($valueRequired){
					if(!$this->isValidValueForOption($realOption,$realOptionValue)){

						$option			= $this->searchOption($realOption);
						$validValues	= implode($option["values"],",");
						throw(new \Exception("Invalid value specified for option $realOption,(".$realOptionValue."), valid values: $validValues"));
					}
				}

				//Option is OK, add it to the parsed options

				$option=$this->searchOption($realOption);

				$this->_parsedOptions[$realOption] = $realOptionValue;

				return TRUE;

			}

			private function optionRequiresOption(){

				$parsedOptions = array_keys($this->_parsedOptions);

				foreach($this->_options as $opt=>$optConfig){

					if(isset($optConfig["requires"]) && in_array($opt,$parsedOptions)){

						foreach($optConfig["requires"] as $required){

							if(!in_array($required,$parsedOptions)){
								throw(new \Exception("Option $opt requires option $required to be set!"));
							}

						}

					}	

				}

			}

			private function optionOverlapsOption(){

				$parsedOptions = array_keys($this->_parsedOptions);

				foreach($this->_options as $opt=>$optConfig){

					if(isset($optConfig["overlaps-with"]) && in_array($opt,$parsedOptions)){

						foreach($optConfig["overlaps-with"] as $required){

							if(in_array($required,$parsedOptions)){

								throw(new \Exception("Option $opt overlaps with option $required!"));

							}else{

								$option = $this->searchOption($required);

								if(isset($option["required"])&&$option["required"]){
									$this->_options[$required]="";	//Fake the option
								}

							}

						}

					}	

				}

			}


			private function parseRequiredOptions(Array $givenOptions){

				$givenOptions = array_keys($givenOptions);

				foreach($this->_options as $option=>$optionConfig){

					if(!isset($optionConfig["required"])){
			 			continue; 
					}

					$alias = NULL;

					if(isset($optionConfig["alias"])){
						$alias = $optionConfig["alias"];
					}

					if(!in_array($option,$givenOptions)&&!in_array($alias,$givenOptions)){
						throw(new \Exception("Option $option is required!"));
					}

				}

			}

			private function searchOption($option){

				foreach($this->_options as $optName=>$optConfig){

					if(($optName==$option)||(isset($optConfig["alias"])&&$optConfig["alias"]==$option)){
						return $optConfig;
					}

				}

				return FALSE;

			}

			private function isValidOption($option){

				if(isset($this->_options[$option])){
					return TRUE;
				}	

				//Else look out for aliases
				foreach($this->_options as $optName=>$optConfig){
						  if(isset($optConfig["alias"])){
									 if($optConfig["alias"]==$option){
												return TRUE;
									 }
						  }
				}

				return FALSE;

			}

			private function isValidValueForOption($option,$value){

				$optConfig = $this->searchOption($option);

				if($optConfig===FALSE){
					return NULL;
				}

				//Admits any value
				if(!isset($optConfig["values"])){
					return TRUE;
				}

			
				foreach($optConfig["values"] as $configValue){

					if($value==$configValue){
						return TRUE;
					}

				}

				return FALSE;

			}

			private function optionRequiresValue($option){

				$optConfig = $this->searchOption($option);

				if($optConfig===FALSE){
					return NULL;
				}

				if(isset($optConfig["novalue"])){
					return FALSE;
				}

				return TRUE;

			}

		}

	}

?>
