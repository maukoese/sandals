<?php
namespace PatiPati\MySQL;

/**
 * @package SANDALs - Simple And Nifty Data Abstraction Layers
 * @subpackage MySQLI Data Access Layer
 * @version 18.01
 * @author Mauko < hi@mauko.co.ke >
 * @link https://sandals.github.io/mysqli.html
 */
class SANDAL
{
	// Database tables prefix, if any
	private $prefix;

	// Temporary database connection object
	private $tconn;

	// Main database connection object
	private $conn;

	// Database table name
	private $table;

	// Array of database table collumn names
	public $collumns;

	// Array of blacklisted database collumns
	public $blacklist;

	/**
	 * Constuctor method sets basic server connection parameters, as well as database table name prefixes
	 * As an added bonus, we can create a database if it does not exist.
	 * @param string $table Database table to connect to for queries
	 * @param array $blacklist Array of database table collumns to ignore
	 * @todo Consider passing $config as an argument to the constructor
	 */
	public function __construct( $config, $table, $blacklist = null )
	{
		if ( is_array( $config ) ) {
			$dbname = $config['dbname'];
			$dbuser = $config['dbuser'] ?? 'root';
			$dbpassword = $config['dbpass'] ?? '';
			$dbhost = $config['dbhost'] ?? 'localhost';
			$dbport = $config['dbport'] ?? $_SERVER['SERVER_PORT'];

			$prefix = $config['dbprefix'] ?? '';
		} else {
			$dbname = $config;
			$dbuser = 'root';
			$dbpassword = '';
			$dbhost = 'localhost';
			$dbport = $_SERVER['SERVER_PORT'];

			$prefix = '';
		}

		$this -> tconn =  new \mysqli( $dbhost, $dbuser, $dbpassword );
		$this -> tconn -> query( "CREATE DATABASE IF NOT EXISTS {$dbname}" );
		$this -> tconn -> close();

		$this -> conn = new \mysqli( $dbhost, $dbuser, $dbpassword, $dbname );

		if ( $this -> conn -> connect_errno ){
		    die( "Connection failed: \n {$this -> conn -> connect_error}" );
		}

		$prefix = dbconfig['dbprefix'] ?? '';
		$this -> table = $prefix.$table;

		$collumns = [];
		$query = $this -> query(
			"SELECT COLUMN_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = '{$this -> table}'
			ORDER BY ORDINAL_POSITION"
		);

		while( $collumn = $this -> assoc( $query ) ){
			$collumns[] =  $collumn['COLUMN_NAME'];
		}

		$this -> collumns = $collumns;
		$this -> blacklist = is_null( $blacklist ) ? [] : $blacklist;
	}

	/**
	 * Destructor method - Closes database connection w/hen there are no more instances of the SANDAL object
	 * @return bool
	 */
	function __destruct()
	{
		$this -> conn -> close();
	}

	/**
	 * Method for running generic queries
	 * @param string $sql SQL query to execute
	 * @return bool/mixed
	 */
	public function query( $sql )
	{
		return $this -> conn -> query( $sql );
	}

	/**
	 * Method for running resultless generic queries
	 * @param string $sql SQL query to execute
	 * @return bool/mixed
	 */
	public function execute( $sql )
	{
		return $this -> conn -> query( $sql );
	}

	/**
	 * Method to return last error
	 * @return string
	 */
	public function error()
	{
		return $this -> conn -> error;
	}

	/**
	 * Method to escape user input to prevent SQL injection
	 * @param string $data String to escape
	 * @return string
	 */
	public function clean( $data )
	{
		return $this -> conn -> real_escape_string( $data );
	}

	/**
	 * Method to convert database query result into an associative array
	 * @param array $result Database query result
	 * @return array
	 */
	public function assoc( $result )
	{
		return $result -> fetch_assoc();
	}

	/**
	 * Method to check if database query has any result items(rows)
	 * @param object $query Database query
	 * @return bool
	 */
	public function rows( $query )
	{
		return ( $query -> num_rows > 0 );
	}

	/**
	 * Method to check if database query has no result items(rows)
	 * @param object $query Database query
	 * @return bool
	 */
	public function blank( $query )
	{
		return ( $query -> num_rows < 1 );
	}

	/**
	 * Method to create new record
	 * Don't use it directly, use insert() instead
	 * @param array $collumns List of database collumns to fill - defaults to all table collumns
	 * @param array $values List of values to insert into table
	 * @return bool
	 */
	public function create( $values, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		$collumns = implode( ", ", $collumns );

		$nuvals = [];
		$values = array_map( function( $val ){
			return $this -> clean( $val );
		}, $values );
		foreach ( $values as $value ) {
			$nuvals[] = "'{$value}'";
		}
		$values = implode( ", ", $nuvals );

		$sql = "INSERT INTO {$this -> table} ( {$collumns} ) VALUES ( {$values} )";

		return $this -> query( $sql );
	}

	public function inserted()
	{
		return $this -> conn -> insert_id;
	}

	/**
	 * Find number of rows affected by a query
	 * @param $result Database query result
	 * @return int
	 * @todo mysqli_num_rows??
	 */
	public function affected( $result = null )
	{
		return $result -> num_rows;
	}

	/**
	 * Method to select records with given conditions - strict
	 * Direct use discouraged, use find() instead and pass 'read' as the third argument as a callable
	 * @param array $conditions Constraints to apply to action, e.g ['title' => 'Sandal']
	 * @param array $collumns List of database collumns to select - defaults to all table collumns
	 * @return bool
	 */
	public function read( $conditions = null, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		$collumns = implode( ", ", $collumns );

		if ( !is_null( $conditions ) ) {
			$conditions = array_map( function( $cond ){
				return $this -> clean( $cond );
			}, $conditions );
			$nuconds = [];
			foreach ( $conditions as $key => $value ) {
				$nuconds[] = "{$key} = '{$value}'";
			}
			$conditions = implode( "AND ", $nuconds );
			$sql = "SELECT {$collumns} FROM {$this -> table} WHERE {$conditions}";
		} else {
			$sql = "SELECT {$collumns} FROM {$this -> table}";
		}

		$query = $this -> query( $sql );

		if ( $query && $this -> rows( $query ) ) {
			while ( $result = $this -> assoc( $query ) ) {
				$results[] = $result;
			}
			return $results;
		} else {
			return ['error' => 'No Record Found' ];
		}
	}

	/**
	 * Method to search for records with given conditions - Uses regular expressions, not strict
	 * Direct use discouraged, use find() instead and pass 'search' as the third argument as a callable
	 * @param array $conditions Constraints to apply to action, e.g ['title' => 'Sandal']
	 * @param array $collumns List of database collumns to select - defaults to all table collumns
	 * @return bool
	 */
	public function search( $conditions = null, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		$collumns = implode( ", ", $collumns );

		if ( !is_null( $conditions ) ) {
			$conditions = array_map( function( $cond ){
				return $this -> clean( $cond );
			}, $conditions );
			$nuconds = [];
			foreach ( $conditions as $key => $value ) {
				$nuconds[] = "{$key} LIKE '%{$value}%'";
			}
			$conditions = implode( "OR ", $nuconds );
			$sql = "SELECT {$collumns} FROM {$this -> table} WHERE {$conditions}";
		} else {
			$sql = "SELECT {$collumns} FROM {$this -> table}";
		}

		$query = $this -> query( $sql );

		if ( $query && $this -> rows( $query ) ) {
			while ( $result = $this -> assoc( $query ) ) {
				$results[] = $result;
			}
			return $results;
		} else {
			return ['error' => 'No Record Found' ];
		}
	}

	/**
	 * Method to reset array index
	 * @param array $result The database query result to reset
	 * @param int $row Index to reset to
	 * @return bool/mixed
	 */
	public function reset( $result, $row = 0 )
	{
		return $result -> data_seek( $row );
  	}

	/**
	 * Method to update existing record
	 * Don't use it directly, use insert() instead
	 * @param array $conditions Constraints to apply to action, e.g ['title' => 'Sandal']
	 * @param arry $values List of values to insert into table row
	 * @param array $collumns List of database collumns to update - defaults to all table collumns
	 * @return bool
	 */
	public function update( $conditions, $values, $collumns )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;

		$values = array_map( function( $val ){
			return $this -> clean( $val);
		}, $values );
		$nuvals = [];
		$colvals = array_combine( $collumns, $values );
		foreach ( $colvals as $collumn => $value ) {
			$nuvals[] = "{$collumn} = '{$value}'";
		}
		$values = implode(", ", $nuvals);

		$conditions = array_map( function( $cond ){
				return $this -> clean( $cond );
			}, $conditions );
		$nuconds = [];
		foreach ( $conditions as $key => $value ) {
			$nuconds[] = "{$key} = '{$value}'";
		}
		$conditions = implode("AND ", $nuconds);

		$sql = "UPDATE {$this -> table} SET {$values} WHERE {$conditions}";
		return $this -> query( $sql );
	}

	/**
	 * Method to offset array by given number
	 * @param array $results Database query results to offset
	 * @param int $offset Number of records to skip
	 * @return array
	 */
	public function offset( $results, $offset = 0 )
	{
		return array_slice( $results, $offset );
	}

	/**
	 * Method for creating or updating a record
	 * @param array $collumns Database table collumns for whose values to insert - defaults to all table collumns
	 * @param array $conditions Constraints to apply to action
	 * @return bool
	 */
	public function insert( $values, $collumns = null, $conditions = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;

		if ( is_null( $conditions ) ) {
			if ( !$this -> create( $values, $collumns ) ) {
				return;
			}
		} else {
			if ( !$this -> update( $conditions, $values, $collumns ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Method for selecting records with given constraints
	 * @param array $conditions Constraints to apply to selection
	 * @param array $collumns Database table collumns to select - defaults to all table collumns
	 * @param callable $callable Callback method - either read(strict) or search(flexible)
	 * @return array
	 */
	public function fetch( $conditions = null, $collumns = null, $callable = "read", $limit = 10, $key = "created", $order = "ASC" )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		$results = array_slice( $this -> $callable( $conditions, $collumns ), 0, $limit );
		
		// usort( $results, function ( $a, $b ) use ( $key )
		// {
		// 	return strcmp( $a[$key], $b[$key] );
		// });

		// if( $order == "DESC" ){
		// 	array_reverse( $results );
		// }

		return $results;

	}

	/**
	 * Method for selecting a single records with given constraints
	 * Conditions must be on primary keys/unique fields e.g [ 'id' => 1 ]
	 * @param array $conditions Constraints to apply to selection
	 * @param array $collumns Database collumns to select - defaults to all table collumns
	 */
	public function single( $conditions, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;

		$result = $this -> read( $conditions, $collumns );

		if( !isset( $result['error'] ) ){
			foreach ( $result as $property => $value ) {
				$this -> $property = $value;
			}

			return $result[0];
		} else {
			return [ 'error' => $result['error'] ];
		}
	}

	/**
	 * Deletes records from table with given constraints
	 * @param array $conditions Constraints to apply to query, e.g [ 'id' => 4 ] or [ 'status' => 'draft' ]
	 * @return bool/mixed
	 */
	public function delete( $conditions = null )
	{
		if ( !is_null( $conditions ) ) {
			$conditions = array_map( function( $cond ){
				return $this -> clean( $cond );
			}, $conditions );

			$nuconds = [];
			foreach ( $conditions as $key => $value ) {
				$nuconds[] = "$key = '{$value}'";
			}
			$conditions = implode("AND ", $nuconds);

			$sql = "DELETE FROM ".$this -> table." WHERE {$conditions}";
		} else {
			$sql = "DELETE FROM ".$this -> table;
		}

		return $this -> query( $sql );
	}
}
