<?php

	/*
	Copyright (c) 2010, dealnews.com, Inc.
	All rights reserved.
	*/

	function progressBar($done, $total,$title="Progress",$size=30) {

		static $start_time;

		// if we go over our bound, just ignore it

		if($done > $total){
			return;
		}

		if(empty($start_time)){ 
			$start_time=time();
		}

		$now = time();

		$perc=(double)($done/$total);

		$bar=floor($perc*$size);

		$status_bar="\r$title [";
		$status_bar.=str_repeat("=", $bar);

		if($bar<$size){
			$status_bar.=">";
			$status_bar.=str_repeat(" ", $size-$bar);
		} else {
			$status_bar.="=";
		}

		$disp=number_format($perc*100, 0);

		$status_bar.="] $disp%  $done/$total";

		$rate = ($now-$start_time)/$done;
		$left = $total - $done;
		$eta = round($rate * $left, 2);

		$elapsed = $now - $start_time;

		$status_bar.= " remaining: ".number_format($eta)." sec.  elapsed: ".number_format($elapsed)." sec.";

		echo "$status_bar  ";

		flush();

		// when done, send a newline
		if($done == $total) {
				echo "\n";
		}

	}
