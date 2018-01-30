
#SANDALs - Simple And Nifty Data Abstraction Layers

This library aims to simplify database interaction by doing away with SQL queries. In order to be as simple as possible yet effective, it is written in Vanilla PHP. That means no frameworksor external dependencies are required for it to work.

## Installation

Download the sandal for your database type and copy it in a directory in your app.

## Usage
### Database Connection
Set some database constants and include the sandal file.

	<?php
	$config = [
		'dbname' => 'jabali',
		'dbuser' => 'root',
		'dbpassword' => '',
		'dbhost' => 'localhost',
		'dbport' => '',
		'dbprefix' => 'db_',
		'dbpath' => '/'
	]);
	require_once( 'path/to/sandal.php' );
	You can leave out most of the configuration and just pass the database name as $config instead.
	i.e 
	<?php
	$config = 'jabali';
		* dbuser will default to 'root'
		* dbbpass will default to '' - empty
		* dbhost will default to 'localhost'
		* dbport will default to the server port
		* dbprefix will default to '' - empty
		* dbpath will default to the SANDAL's directory

### Data ( Access ) Objects
To create a data access object( which may serve also as a data object when querying a single record ), instantiate the sandal by passing server configuration `$config` as the first argument, the name of the database table as the second argument and an array of fields to ignore/blaclist as the second argument. The blacklist is optional.

Replace MySQL with your database system ( SQLite | Postgre | Mongo | Pouch )

	<?php
	$dao = new \PatiPati\MySQL\SANDAL( $config, $table, $blacklist );
	Example data access object: 
		$dao = new \PatiPati\MySQL\SANDAL( $config, 'users', [ 'key', 'password' ] );

#### Inserting ( Creating/Deleting ) Data
If you are creating a new record, and you supply values for all collumns. then both `$collumns` and `$conditions` can be ignored. Sandal will create a new record if no `$conditions` are set.

	<?php
	$create = $dao -> insert( $values, $collumns, $conditions );
	Example create record: 
		$create = $dao -> insert( 
			[ 'Mauko', 'Maunde', 'maukoese@gmail.com' ], 
			[ 'first_name', 'last_name', 'email_address' ] 
		);
	Example update record: 
		$update = $dao -> insert(
			[ hi@mauko.co.ke' ],
			[ 'email_address' ],
			[ 'email_address' => 'maukoese@gmail.com' ]
		);

#### Fetching Data
To return multiple records, use the `fetch()` method. If no `$collumns` are supplied, all collumns will be returned, less those blacklisted - so the second argument can be ignored/set to null if all collumns are required. The `$callack` defaults to 'read', which is strict querying - alternately, you could pass 'search' as the `$callback` if you want the database query to select records using regular expressions( LIKE '%value%' ) 

The `single()` method returns a single record. Make sure the $collumn name supplied is UNIQUE/PRIMARY otherwise only the last record in the results will be returned by this function. 

	$records = $dao -> fetch( 
		array $conditions,
		array $collumns,  
		string $callback 
	);
	or;
	$records = $dao -> single( 
		array $conditions
	);
	Example fetch multiple records
	$records = $dao -> fetch(
		[ 'status' => 'active' ],
		[ 'first_name', 'last_name', 'email_address' ],
		'search | read' 
	);
	Example fetch single record
	$records = $dao -> single( 
		[ 'id' => 4 ]
	);
