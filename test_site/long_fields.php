<?php

	require "db.php";	
	query("SELECT * FROM lots_o_fields WHERE id=$_GET[id]");


?>
