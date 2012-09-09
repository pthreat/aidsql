#!/bin/bash

	#Script for installing aidSQL's test site

	export TESTHOST="dev.aidsql.org";
	export WWWDIR="/var/www/aidsql";
	export APACHEDIR="/etc/apache2/";
	export SITESDIR="$APACHEDIR/sites-available";
	export SITESENABLED="$APACHEDIR/sites-enabled";
	export SCRIPT=$(basename $0);
	export SCRIPT_PATH="${BASH_SOURCE[0]}";
	export BASE_PATH=$(dirname $SCRIPT_PATH);
	export MYSQL_CLIENT_ATTEMPTS=3;
	export SQL_FILE="$BASE_PATH/aidsqltest.sql";


	function banner(){
		echo "***********************************************************";
		echo "aidSQL TEST SITE INSTALLER ";
		echo -e "***********************************************************\n";
	}

	function isRoot(){

		local user=$(whoami);

		if [[ "$user" == "root" ]]
			then return 1;
		fi;

		return 0;

	}


	function apacheExists(){
		echo $(which apachectl);
	}

	function mysqlServerExists(){
		echo $(/etc/init.d/mysql);
	}

	function mysqlClientExists(){
		echo $(which mysql);
	}

	function installDatabase(){

		local password="$1";
		local sqlFile="$2";
		local mysqlClient=$(mysqlClientExists);
		$mysqlClient -uroot -p$password <<< $sqlFile;

	}

	#arguments $1 password

	function checkMySQLPassword(){

		local password=$1;
		local mysqlClient=$(mysqlClientExists);

		eval $($mysqlClient -uroot -p$password << eof);
		return $?;
		
	}

	function vHostExists(){

		[ -d "$SITESDIR/aidsql" ] && return 1;
		return 0;	

	}

	function restartApache(){

		local CMD=$(apacheExists);
		$CMD restart;

	}

	function createVHost(){

		[ -d "$WWWDIR" ]; return 0;

		mkdir -p "$WWWDIR";
		mkdir -p "$WWWDIR/logs/";

		echo "<VirtualHost *:80>
        ServerAdmin root@localhost
        ServerName $TESTHOST
        DocumentRoot $WWWDIR
        ErrorLog \"/$WWWDIR/logs/aidsql_error.log\"
        CustomLog \"/$WWWDIR/logs/aidsql_access.log\" common
</VirtualHost>" > $SITESDIR/aidsql;

		ln -s $SITESDIR/aidsql $SITESENABLED/aisql;

	}

	function secureVHost(){

		echo "order allow,deny
allow from 127.0.0.1
deny from all" > $WWWDIR/.htaccess;

	}

	function checkEtcHosts(){

		local testHost=$(cat /etc/hosts|grep $TESTHOST);
		[ -z "$testHost" ]; return 1;
		return 0;

	}

	banner;

	echo -e "Checking for user privileges ... \c";

	if [ isRoot == 0 ]
		then echo "ERROR";
			echo "You must be root in order to install aidSQL's test site";
			exit 1;
	fi;

	echo "OK";

	echo -e "Checking for Apache HTTP Server ... \c";

	if [ -z apacheExists ]
		then echo "ERROR";
		echo "You dont seem to have apache installed in your system, please install apache before installing the test site";
		exit 1;
	fi;

	echo "OK";


	echo -e "Checking for mySQL Server ... \c";

	if [ -z mysqlServerExists ]
		then echo "ERROR";
		echo "You dont seem to have mysql server installed in your system, please install mysqld before installing the test site";
		exit 1;
	fi;

	echo "OK";

	echo -e "Checking for mySQL client ... \c";

	if [ -z mysqlClientExists ]
		then echo "ERROR";
		echo "You dont seem to have mysql client installed in your system, please install mysql client before installing the test site";
		exit 1;

	fi;

	echo "OK";


	echo  "Checking for mySQL Password ... ";

	#Verify mysql root password

	read -s -p "Enter mysql root password:" password;
	echo $(checkMySQLPassword $password);
	exit;
	until [ $(checkMySQLPassword $password) -eq 0 ] 
		do
			let attempts+=1;

			if [ $attempts -gt $MYSQL_CLIENT_ATTEMPTS ]
				then echo "ERROR";
					echo "max password attempts reached, exiting";
					break;
			fi;

			echo "Invalid password, please try again or abort with CTRL + C";

	done;

	exit 1;

	echo -e "Checking /etc/hosts ... \c";

	if [ !checkEtcHosts ]
		then
			echo "Adding $TESTHOST to /etc/hosts";
			echo -e "127.0.0.1\t$TESTHOST" >> /etc/hosts;
		else
			echo "OK";
	fi;

	
	echo -e "Creating apache vhost config ... \c";

	if [ !vHostExists ] 
		then 
		echo "Creating VHOST $TESTHOST ... ";
		createVhost;
		echo "OK";

	else

		echo OK;

	fi;

