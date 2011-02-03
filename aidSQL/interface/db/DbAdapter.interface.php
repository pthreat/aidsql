<?php

	namespace aidSQL\db {

		interface DbAdapter{

			public function connect();
			public function query($sql);

		}	

	}
