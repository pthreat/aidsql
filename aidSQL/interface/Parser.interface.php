<?php

namespace aidSQL\parser {

	interface ParserInterface{

		public function setLog(\aidSQL\core\Logger &$log);
		public function analyze($content);

	}

}

?>
