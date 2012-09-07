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

	function isRoot(){

		local user=$(whoami);

		[ "$user" == "root" ]; return 1;
		return 0;

	}

	function apacheExists(){
		echo $(which apachectl);
	}

	function mysqlServerExists(){
		echo $(/etc/init.d/mysql);
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

	if [ !isRoot ]
		then echo "You must be root in order to install aidSQL's test site";
			exit 1;
	fi;

	if [ -z apacheExists ]
		then echo "You dont seem to have apache installed in your system, please install apache before installing the test site";
		exit 1;
	fi;

	if [ -z mysqlServerExists ]
		then echo "You dont seem to have mysql server installed in your system, please install mysqld before installing the test site";
		exit 1;
	fi;
	

	echo "Checking /etc/hosts ...";

	if [ !checkEtcHosts ]
		then
			echo "Adding $TESTHOST to /etc/hosts";
			echo -e "127.0.0.1\t$TESTHOST" >> /etc/hosts;
		else
			echo "OK";
	fi;

	
	echo "Creating apache vhost config ...";

	if [ !vHostExists ] 
		then 
		echo "Creating VHOST ...";
		createVhost;
		echo "OK";

	else

		echo OK;

	fi;

