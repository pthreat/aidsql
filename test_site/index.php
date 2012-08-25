<?php

	require "db.php";
	$sql	=	"SELECT * FROM products WHERE category='$_GET[category]' AND subcat='$_GET[subcat]'";
	echo $sql."\n";
	query($sql);

?>
