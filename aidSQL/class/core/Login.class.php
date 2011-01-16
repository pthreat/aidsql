<?php

	namespace aidsql {

		class Login {

			private $_httpAdapter	= NULL;
			private $_loginPage	= NULL;
			private $_userField	= "username";
			private $_passField	= "password";
			private $_username	= NULL;
			private $_password	= NULL;
			private $_debug		= TRUE;

			public function __construct(HttpAdapter $httpAdapter){
				$this->_httpAdapter = $httpAdapter;
			} 

			public function setLoginPage($loginPage=NULL){

				if(empty($loginPage)){
					throw(new \Exception("Login page cannot be empty!"));
				}

				$this->_loginPage = $loginPage;

			}


			/**
			*User related setters and getters
			*/

			public function setUserField($userField=NULL){

				if(empty($userField)){
					throw(new \Exception("User field cannot be empty!"));
				}

				$this->_userField = $userField;

			}

			public function setUsername($username=NULL){

				if(empty($username)){
					throw(new \Exception("Username cannot be empty!"));
				}

				$this->_username = $username;

			}

			public function getUsername(){
				return $this->_username;
			}

			/**
			*Password related setters and getters
			*/

			public function setPassword($password=NULL){

				if(empty($password)){
					throw(new \Exception("Password cannot be empty!"));
				}

				$this->_password = $username;

			}

			public function getPassword(){
				return $this->_password;
			}

			public function setPasswordField($passField=NULL){

				if(empty($passField)){
					throw(new \Exception("Password field cannot be empty!"));
				}

				$this->_passField = $passField;

			}

			public function getPasswordField(){
				return $this->_passField;
			}

			public function debug($msg){

				if($this->_debug){
					echo __CLASS__.":",$msg."\n";
				}

			}

			public function do(){

				$this->debug("Attempting to login ...");
				$this->_httpAdapter->setUrl($this->_loginPage);
				$this->_httpAdapter->setMethod("POST");
				$this->_httpAdapter->fetch();

			}


		}

	}
