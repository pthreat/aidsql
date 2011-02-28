<?php

	//Search engine functions library

	function googleSearch(\aidSQL\http\Adapter &$httpAdapter,\aidSQL\core\Logger &$log,Array $parsedOptions){

		$google	=	new \aidSQL\http\webservice\Google($httpAdapter,$log);
		$google->setConfig($parsedOptions);

		$userTotal	=	(isset($parsedOptions["google-max-results"]))	?	$parsedOptions["google-max-results"]	:	0;

		try{

			$sites	=	array();
			$total	=	0;
			$offset	=	(isset($parsedOptions["google-offset"]))	?	$parsedOptions["google-offset"]	:	0;

			do{

				if($offset>0){

					$google->setStart($offset);

				}

				$result = $google->search();

				if(isset($result->responseData->cursor->estimatedResultCount)){

					$total = $result->responseData->cursor->estimatedResultCount - $offset;

					if($userTotal==0){
						$userTotal = $total;
					}

				}

				foreach($result->responseData->results as $searchResult){

					$url = new \aidSQL\core\Url($searchResult->visibleUrl);

					if(!in_array($url,$sites)){
						$sites[] = $url;
					}

				}

				$offset+=8;

			}while($offset<$total && $offset<$userTotal);

		}catch(\Exception $e){

			$log->log($e->getMessage(),1,"red",FALSE);

		}

		return $sites;

	}

	function bingSearch(\aidSQL\http\Adapter &$httpAdapter,\aidSQL\core\Logger &$log,Array $parsedOptions){

		$bing	=	new \aidSQL\http\webservice\Bing($httpAdapter,$log);
		$bing->setConfig($parsedOptions);

		try{

			$sites = $bing->getHosts($parsedOptions["bing"]);

		}catch(\Exception $e){

			$log->log($e->getMessage(),1,"red");

		}

		return $sites;

	}

	function binGoo(\aidSQL\http\Adapter &$httpAdapter,\aidSQL\core\Logger &$log,Array $parsedOptions){

		if(!strpos($parsedOptions["bingoo"],'|')){
			throw(new \Exception("--bingoo option must be used as follows <domain>|<search term> (note the pipe \"|\")"));
		}

		$parsedOptions["bing"]		=	substr($parsedOptions["bingoo"],0,strpos($parsedOptions["bingoo"],'|'));

		//Do reverse DNS

		$bingSites						=	bingSearch($httpAdapter,$log,$parsedOptions);

		if(!sizeof($bingSites)){

			throw(new \Exception("Bing search returned no sites"));

		}

		//foreach reversed host do a google search

		$googleSearch	=	substr($parsedOptions["bingoo"],strpos($parsedOptions["bingoo"],'|')+1);

		foreach($bingSites as $site){

			$parsedOptions["google"]	=	"site:$site $googleSearch";
			$sites							=	googleSearch($httpAdapter,$log,$parsedOptions);

			if(!sizeof($sites)){
				$log->log("Nothing found for $parsedOptions[google]",2,"yellow");
			}

		}

		return $sites;

	}

?>
