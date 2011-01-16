<?php

	/**
	 * Just some a class with some static method for handling hex encoding and others
	 */


	class String {
		
		/**
		* String to HEX | HEX to String. Automatic detection.
		* This script just takes input from the command line
		* and transforms an ASCII String to HEX or viceversa.
		* If you want to use it in a web page just change the $str variable below.
		* Cheers, Juan Stange.-
		*/

		public static function hexEncode($str=NULL){

			if(is_null($str)){
				return FALSE;
			}

			$hexStr = "";

			for($i=0;isset($str[$i]);$i++){
				$char = dechex(ord($str[$i]));
				$hexStr .= $char;
			}

			return "0x".$hexStr;

		}

		public static function asciiEncode($str=NULL){

			if(!preg_match("/^0x[A-Fa-f0-9]+/",$str)){
				return FALSE;	//Not a hex string
			}

			$str = substr($str,2);
			$asciiString = "";

			for($i=0;isset($str[$i]);$i+=2){
				$hexChar = substr($str,$i,2);
				$asciiString .= chr(hexdec($hexChar));
			}

			return $asciiString;

		}

	}

?>