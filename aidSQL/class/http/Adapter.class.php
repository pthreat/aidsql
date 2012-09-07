<?php

	namespace \aidSQL\http {

		abstract class Adapter implements \aidSQL{

			public function getHttpAdapterVersion(){

 	       	$constant   =  "static::ADAPTER_VERSION";

				if(defined($constant)){

					return constant($constant);

				}

				return "UNKNOWN";

			}

			public function getHttpAdapterName(){

 		     	$constant   =  "static::ADAPTER_NAME";

				if(defined($constant)){

					return constant($constant);

				}

				return "UNKNOWN";

			}

		}

	}

?>
