<?php

	if($_SERVER["REMOTE_ADDR"]!=="127.0.0.1"){
		die();
	}

	mysql_connect("localhost","root","your-password-here");
	mysql_select_db("aidsqltest");

	function query($sql){

		echo "<h1>Query</h1>";
		echo "<p>".$sql."</p>\n";

		$result	= mysql_query($sql);

		echo "<h1>Error</h1>";
		echo "<p>".mysql_error()."</p>\n";


		echo "<h1>Result</h1>";
		while($row	=	mysql_fetch_array($result)){
			var_dump($row);
		}

		return $row;

	}

?>
