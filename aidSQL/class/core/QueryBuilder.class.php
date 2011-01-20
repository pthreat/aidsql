<?php

	/**
	*Query Builder class, this class is used to build SQL queries
	*/

	namespace aidSQL\core{

		class QueryBuilder{
	
			private	$_fieldWrapper				=	"CONCAT(0x7c,%value%,0x7c)";
			private	$_fieldDelimiter			=	',';
			private	$_fieldEqualityChar		=	'=';
			private	$_space						=	" ";	//This could be aswell /**/ for evading ids's
			private	$_commentOpen				=	"/*";
			private	$_commentClose				=	"*/";
			private	$_sql							=	array();

			//This is just an accesory method for you being able to wrap a certain value

			public function wrap($wrapping,$value){

				return preg_replace("/%value%/",$value,$wrapping);

			}

			public function wrapArray(Array $wrapMe,$wrapping){

				$return	=	array();

				foreach($wrapMe as $key=>$wrapIt){
					$return[]	=	$this->wrap($wrapping,$wrapIt);
				}

				return $return;

			}

			public function select(Array $fields,Array $where=array()){

				$this->_sql[]	=	"SELECT".$this->_space.implode($this->_fieldDelimiter,$fields);

			}

			public function join($joinType="INNER",$table,Array $condition){

				$this->_sql[]	=	$joinType.$this->_space."JOIN".$this->_space.$table.implode($this->_space,$condition);

			}

			public function from($table){

				$this->_sql[]	=	"FROM".$this->_space($table);

			}

			public function where(Array $conditions){

				$this->_sql[]="WHERE".implode($this->_space,$conditions);

			}

			public function orderBy($field,$sort=NULL){

				if(!empty($sort)){
					$sort	=	$this->_space.$sort;
				}

				$this->_sql[]="ORDER".$this->_space."BY".$this->_space.$field.$sort;

			}

			public function union(Array $selectFields,$unionType=""){


				$union			=	"UNION";

				if($unionType){

					$union.=$this->_space.$unionType;

				}

				$this->_sql[]	=	$union;

				$this->select($selectFields);

			}

			public function limit(Array $limit){

				return "LIMIT".$this->_space.implode($limit,',');

			}

			public function setFieldEqualityCharacter($equalityCharacter){

				$this->_fieldEqualityCharacter	=	$equalityCharacter;

			}

			public function setLimit(Array $limit){

				$this->_sql[]	=	$limit;

			}

			public function setFieldSpace($_space){

				$this->_space	=	$_space;

			}

			public function getSQL(){
				return implode($this->_space,$this->_sql);
			}

			public function __toString(){

				return $this->getSQL();

			}

		}

	}
