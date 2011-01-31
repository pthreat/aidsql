<?php

	namespace aidSQL\http {

		interface Adapter {

			public function setUrl(\aidSQL\core\Url $url);
			public function getUrl();
			public function fetch();
			public function setMethod($method);
			public function setRequestInterval($interval);
			public function getRequestInterval();
			public function setConnectTimeout($timeout);
			public function getConnectTimeout();
			public function setProxyServer($server);
			public function setProxyTunnel($boolean);
			public function setProxyPort($port);
			public function setProxyAuth($auth);
			public function setProxyType($type);
			public function getHttpCode();
			public function setLog(\aidSQL\core\Logger &$log);
	
		}

	}

?>
