<?php

	//Parsers
	require	"lib/aidSQL/interface/Parser.interface.php";
	require	"lib/aidSQL/class/parser/Generic.class.php";

	$openTag		=	"{!";
	$closeTag	=	"!}";

	$content		=	file_get_contents($_SERVER["argv"][1]);

	$tagMatch = new aidSQL\parser\Generic($content);
	$tagMatch->setOpenTag($openTag);
	$tagMatch->setCloseTag($closeTag);
	var_dump($tagMatch->analyze($content));


?>
