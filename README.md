
# SANDALs - Simple And Nifty Data Abstraction Layers

This library aims to simplify database interaction by doing away with SQL queries. In order to be as simple as possible yet effective, it is written in Vanilla PHP. That means no frameworksor external dependencies are required for it to work.

## Installation

Download the sandal for your database type and copy it in a directory in your app.

## Usage
### Database Connection
Set some database constants.

	<?php
	define( 
		'appconfig', 
		[
			'dbname' => 'jabali',
			'dbuser' => 'root',
			'dbpassword' => '',
			'dbhost' => 'localhost',
			'dbport' => '',
			'dbprefix' => 'db_',
			'dbpath' => root.'/db/'
		]
	);


### Data ( Access ) Objects

	<?php
	$dao = new \PatiPati\Slipper( $table, $collumns, $whitelist );

#### Inserting Data

	<?php
	$records = $dao -> insert( $values, $collumns, $conditions )

#### Fetching Data
	$records = $dao -> find( ["*"], ["details" => "the"], "search" );
