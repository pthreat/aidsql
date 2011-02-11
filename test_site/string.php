<?php

	require "db.php"
	$query	= "SELECT * FROM test.news WHERE news_text LIKE '$_GET[q]%'";
	query($query);

?>
