<?php

	namespace aidSQL\parser {

		class Dom {

			private $_content	=	NULL;

			public function __construct($content=NULL){

				$this->setContent($content);
				
			}
			
			//Fetches all A elements from a given content

			public function fetchLinks(){

				$links	=	$this->fetchTag('a',"href");

				$javascripts	=	Array();
				$anchors			=	Array();
				$pureLinks		=	Array();
				$emails			=	Array();

				foreach($links as $link){
				
					if(preg_match("/javascript\:/i",$link)){

						if(!in_array($link,$javascripts)){

							$javascripts[]=$link;

						}

					}elseif(preg_match("/^#.*/",$link)){

							if(!in_array($link,$anchors)){

								$anchors[]=$link;

							}

					}elseif(preg_match("/^mailto\:/i",$link)){

						if(!in_array($link,$emails)){

							$emails[]=$link;

						}
						
					}else{

						if(!in_array($link,$pureLinks)){

							$pureLinks[]=$link;

						}

					}

				}

				return array(
					"javascript"	=>	$javascripts,
					"anchors"		=>	$anchors,
					"links"			=>	$pureLinks,
					"emails"			=>	$emails
				);

			}

			//Fetches all img elements from a given content

			public function fetchImages(){

				return $this->fetchTag("img","src");

			}

			public function fetchForms(){

				$return	=	array();
				$dom		=	new \DomDocument();

				@$dom->loadHTML($this->_content);

				$forms	=	$dom->getElementsByTagName("form");

				$return = array();

				$length	=	$forms->length;

				if($length > 0){

					for($i=0;$i<$length;$i++){

						$form			=	$forms->item($i);
						$formName	=	$form->getAttribute("name");
						$formName	=	(!empty($formNname)) ? $formName : "form_$i";
						$curForm					=	array();
						$curForm[$formName]	=	array();

						$curForm[$formName]["attributes"]	=	$this->getNodeAttributes($form);

						$childTypes	=	array( "input",
							"select",
							"textarea"
						);

						//Get all the childs from the current <form>

						$childs		=	$this->getChildNodes($form,$childTypes);	

						//for every child of the <form>

						if($childs){

							foreach($childs as $childKey=>$childNode){

								$nodeName				=	$childNode->nodeName;

								$tmpChild				=	array();
								$tmpChild[$nodeName]	=	array();

								if ($childNode->hasChildNodes()){	//should be a <select>

									$childNodeChilds	=	$this->getChildNodes($childNode);
									$attributes			=	$this->getNodeAttributes($childNode);

									if(!isset($attributes["name"])){
										continue;
									}

									$elementName		=	$attributes["name"];

									$tmpChild[$nodeName]["attributes"]["name"]	=	$elementName;

									$elementValues		=	array();

									foreach($childNodeChilds as $childNodeChild){	//get all the <option> values

										$attributes	=	$this->getNodeAttributes($childNodeChild);

										if(sizeof($attributes)){
											if(isset($attributes["value"])){
												$tmpChild[$nodeName]["attributes"]["values"][]	=	$attributes["value"];
											}
										}

									}

								}else{	//Should be anything else, like an <input>

									$attributes	=	$this->getNodeAttributes($childNode);

									if(sizeof($attributes)){

										if(!isset($attributes["name"])){
											continue;
										}

										$tmpChild[$nodeName]["attributes"]	=	$attributes;

										if(isset($tmpChild[$nodeName]["attributes"]["value"])){

											if(isset($curForm[$formName]["elements"])){

												foreach($curForm[$formName]["elements"] as $key=>$formElement){

													$formElementName	=	key($formElement);
													$formElement		=	$formElement[$formElementName];

													if($formElement["attributes"]["name"]	==	$tmpChild[$nodeName]["attributes"]["name"]){
	
														$tmpChildValue		=	$tmpChild[$nodeName]["attributes"]["value"];

														if(!isset($curForm[$formName]["elements"][$key][$formElementName]["attributes"]["values"])){
															unset($curForm[$formName]["elements"][$key][$formElementName]["attributes"]["value"]);

															$formElementValue	=	$formElement["attributes"]["value"];

															$curForm[$formName]["elements"]
															[$key][$formElementName]["attributes"]
															["values"][]	=	$formElementValue;

															$curForm[$formName]["elements"]
															[$key][$formElementName]["attributes"]
															["values"][]	=	$tmpChildValue;

														}else{

															if(!in_array($tmpChildValue,$curForm[$formName]["elements"][$key][$formElementName]["attributes"]["values"])){
																$curForm[$formName]["elements"]
																[$key][$formElementName]["attributes"]
																["values"][]	=	$tmpChildValue;

															}

														}
													
													}else{
													
														foreach($curForm[$formName]["elements"] as $key=>$formElement){

															$names[]	=	$formElement[key($formElement)]["attributes"]["name"];

														}

														if(!in_array($tmpChild[$nodeName]["attributes"]["name"],$names)){
															$curForm[$formName]["elements"][]	=	$tmpChild;
														}

													}

												}

											}else{

												$curForm[$formName]["elements"][]	=	$tmpChild;

											}

										}else{

											$curForm[$formName]["elements"][]	=	$tmpChild;

										}

									}

								}

							}

							$return[]	=	$curForm;

						}


					}

				}

				return $return;

			}

			public function getChildNodes(\DomNode $node,Array $types=array()){

				if(!$node->hasChildNodes()){
					return FALSE;
				}

				$childs	=	$node->childNodes;

				$return	=	array();

				foreach($childs as $child){

					$nodeName	=	$child->nodeName;

					if(is_a($child,"DomElement")){

						if($hasChilds	=	$this->getChildNodes($child,$types)){

							foreach($hasChilds as $childChild){
								$return[]	=	$childChild;
								continue;
							}
	
						}

						if(sizeof($types)){

							if(in_array($nodeName,$types)){
								$return[]=$child;
							}

						}else{

								$return[]=$child;

						}

					}

				}

				return $return;

			}

			private function getPostFieldsFromInputs($form){

			}

			public function fetchTag($tagName,$attrName=NULL){

				$return	=	array();
				$dom		=	new \DomDocument();

				@$dom->loadHTML($this->_content);

				$tags	=	$dom->getElementsByTagName($tagName);

				$return = array();

				if($tags->length > 0){

					foreach($tags as $tag){

							$return[]	=	$tag->getAttribute($attrName);

					}

				}

				return $return;

			}

			public function getNodeAttributes(\DomNode $node){

				$attr			=	$node->attributes;
				$attributes	=	array();

				foreach($attr as $attribute=>$domAttr){

					$attributes[$attribute]	=	$domAttr->value;

				}

				return $attributes;

			}

			public function setContent($content=NULL){

				if(empty($content)){
					throw (new \Exception(__CLASS__.": Content is empty!"));
				}

				$this->_content	=	$content;
				
			}

			public function getContent(){
				return $this->_content;
			}

	
			public function getInnerHTML($node){

				$doc = new \DOMDocument();

				foreach ($node->childNodes as $child){

					$doc->appendChild($doc->importNode($child, true));

				}

				return $doc->saveHTML();

			}


		}

	}
	
?>
