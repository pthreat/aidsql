<?php

	namespace aidSQL\db {

		class MySQLDbAdapter extends \MySQLi implements DbAdapter{

			private	$_queryColor	=	"light_cyan";
			private	$_logger			=	NULL;

			public function __construct(\aidSQL\core\Logger &$log=NULL,$host=NULL,$username=NULL,$passwd=NULL,$dbname="",$port=NULL,$socket=NULL ){

				$this->setLog($log);

				parent::__construct($host,$username,$passwd,$dbname,$port,$socket);

				if($this->connect_error){
					throw(new \Exception("Couldnt connect to database $dbname. ".$this->connect_error));
				}

			}

			public function setLog(\aidSQL\core\Logger &$log){

				$log->setPrepend('['.__CLASS__.']');
				$this->_logger	=	$log;

			}

			private function log($msg=NULL, $color="white", $level=0, $toFile=FALSE) {

				if (!is_null($this->_logger)) {

					$this->_logger->setPrepend('[' . __CLASS__ . ']');
					$this->_logger->log($msg, $color, $level, $toFile);
					return TRUE;

				}

				return FALSE;

			}

			public function setVerbose($verbose=TRUE){
				$this->_verbose	=	$verbose;
			}

			public function setQueryColor($color){
				$this->_queryColor	=	$color;
			}

			public function query($sql){

				if($this->_verbose){
					$this->log($sql,0,$this->_queryColor);
				}

				$result	=	parent::query($sql);

				if(!$result){

					throw(new \Exception("MySQL ERROR ".$this->errno.':'.$this->error.' | SQL: '.$sql));

				}

				return $result;	

			}

		}

	}

?>
