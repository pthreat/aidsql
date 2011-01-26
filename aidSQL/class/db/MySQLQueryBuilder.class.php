<?php

	/**
	*MySQL query Builder class, this class is used to build SQL queries
	*/

	namespace aidSQL\db{

		class MySQLQueryBuilder implements QueryBuilderInterface{

			private	$_fieldDelimiter			=	',';
			private	$_fieldEqualityChar		=	'=';
			private	$_space						=	" ";	//This could be aswell /**/ for evading ids's
			private	$_commentOpen				=	"/*";
			private	$_commentClose				=	"*/";
			private	$_sql							=	array();
			private	$_sqlStr						=	NULL;

			//This is just an accesory method for you being able to wrap a certain value

			public function group(Array $group){
				$this->_sql[] = "GROUP".$this->_space."BY".$this->_space.implode(',',$group);
			}

			public function toOutFile($file){
				$this->_sql[] = "INTO".$this->_space."OUTFILE".$this->_space."'".$file."'";
			}

			public function reset(){
				$this->_sql	=	array();	
			}

			public function setCommentOpen($commentOpen){

				$this->_commentOpen	=	$commentOpen;

			}

			public function setCommentClose($commentClose){

				$this->_commentClose	=	$commentClose;

			}

			public function select(Array $fields){

				$this->_sql[]	=	"SELECT".$this->_space.implode($this->_fieldDelimiter,$fields);

			}

			public function join($joinType="INNER",$table,Array $condition){

				$this->_sql[]	=	$joinType.$this->_space."JOIN".$this->_space.$table.implode($this->_space,$condition);

			}

			public function from($table){

				$this->_sql[]	=	"FROM".$this->_space.$table;

			}

			public function where(Array $conditions){

				$this->_sql[]="WHERE".$this->_space.implode($this->_space,$conditions);

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

				$this->_sql[]	=	"LIMIT".$this->_space.implode($limit,',');

			}

			public function setFieldEqualityCharacter($equalityCharacter){

				$this->_fieldEqualityCharacter	=	$equalityCharacter;

			}

			public function setSpaceCharacter($_space){

				$this->_space	=	$_space;

			}

			public function getSpaceCharacter(){
				return $this->_space;
			}	

			public function getSQL(){

				return implode($this->_space,$this->_sql);

			}

			public function setSQL($sql){

				$this->_sql	=	array($sql);

			}

			public function __toString(){

				return $this->getSQL();

			}

		}

	}
