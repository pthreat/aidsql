<?php

	namespace aidSQL\core {

		class File {

			private $_dirname = NULL;
			private $_file		= NULL;


			public function __construct($file=NULL){

				if(!is_null($file)){

					if(!file_exists($file)){
						throw (new \Exception ("File $file not found!"));
					}

					$this->_dirname = dirname($file);
					$this->_file    = basename($file);

				}

			}

			public function isUsable(){

				$file	=	$this->getFile();

				if(!is_readable($file)){
					throw(new \Exception("File $file is not readable, please check your permissions!"));
				}

				if(!is_file($file)){
					throw(new \Exception("File $file is a directory!"));
				}

			}

			public function dirname(){
				return $this->_dirname;
			}

			public function basename(){
				return $this->_basename;
			}

			public function getFile(){
				return $this->_dirname.DIRECTORY_SEPARATOR.$this->_file;
			}

			public function getContents(){
				return file_get_contents($this->_dirname.DIRECTORY_SEPARATOR.$this->_file);
			}

			public function getContentsAsArray(){
				return file($this->_dirname.DIRECTORY_SEPARATOR.$this->_file);
			}

			public function __toString(){

				return $this->getFile();

			}

		}

	}

?>
