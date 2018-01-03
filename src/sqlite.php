<?php
namespace PatiPati\SQLite;

/**
 * @package SANDALs - Simple And Nifty Data Abstraction Layers
 * @subpackage SQLite Data Access Layer
 * @version 18.01
 * @author Mauko < hi@mauko.co.ke >
 * @link https://phpsandals.co.ke/dals/sqlite
 */
class SANDAL
{
	// Database tables prefix, if any
	private $prefix;

	// Database connection object
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
	 */
	public function __construct( $table, $blacklist = null )
	{
		if ( !defined( 'appconfig' ) ){
			die( 'Please define appconfig( Server Variables )');
		}

		$dbname = appconfig['dbname'];
		$dbuser = appconfig['dbuser'] ?? 'root'; 
		$dbpassword = appconfig['dbpassword'] ?? '';
		$dbhost = appconfig['dbhost'] ?? 'localhost';
		$dbport = appconfig['dbport'] ?? $_SERVER['SERVER_PORT'];

		$dbpath = appconfig['dbpath'] ?? __DIR__; 

		$this -> conn = new \SQLite3( $dbpath.$dbname.'.db' );

		if ( $this -> conn -> connect_errno ){
		    die( "Connection failed: \n {$this -> conn -> connect_error}" );
		}

		$prefix = appconfig['dbprefix'] ?? '';
		$table = $prefix.$table;

		$collumns = [];
		$query = $this -> query(
			"SELECT COLUMN_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = '{$table}'
			ORDER BY ORDINAL_POSITION"
		);

		while( $collumn = $this -> assoc( $query ) ){
			$collumns[] =  $collumn['COLUMN_NAME'];
		}

		$this -> table = $table;
		$this -> collumns = $collumns;
		$this -> blacklist = is_null( $blacklist ) ? $collumns : $blacklist;
	}

	/**
	 * Destructor method - Closes database connection when there are no more instances of this class object
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
		return $this -> conn -> exec( $sql );
	}

	/**
	 * Method to return last error
	 * @return string
	 */
	public function error()
	{
		return $this -> conn -> lastErrorMsg();
	}

	/**
	 * Method to escape user input to prevent SQL injection
	 * @param string $data String to escape
	 * @return string
	 */
	public function clean( $data )
	{
		return $this -> conn -> escapeString( $data );
	}

	/**
	 * Method to convert database query result as an associative array
	 * @param array $result Database query result
	 * @return array
	 */
	public function assoc( $result )
	{
		return $result -> fetchArray( SQLITE3_ASSOC );
	}

	/**
	 * Method to check if database query has any result items(rows)
	 * @param object $query Database query
	 * @return bool
	 */
	public function rows( $query )
	{
		return ( $this -> conn -> changes() > 0 );
	}

	/**
	 * Method to create new record
	 * Don't use it directly, use insert() instead
	 * @param array $collumns List of database collumns to fill
	 * @param array $values List of values to insert into table
	 * @return bool
	 */
	public function create( $values, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		$collumns = implode(", ", $collumns);

		array_walk( $values, [$this, "clean"] );
		$nuvals = [];
		foreach ($values as $value) {
			$nuvals[] = "'{$value}'";
		}
		$values = implode(", ", $nuvals);

		$sql = "INSERT INTO {$this -> table} ( {$collumns} ) VALUES ( {$values} )";

		return $this -> query( $sql );
	}

	public function inserted()
	{
		return $this -> conn -> lastInsertRowID();
	}

	public function affected()
	{
		return $this -> conn -> changes();
	}

	/**
	 * Method to select records with given conditions - strict
	 * Don't use it directly, use find() instead and pass 'read' as the third argument(callable)
	 * @param array $conditions Constraints to apply to action, e.g ['title' => 'Sandal']
	 * @param array $collumns List of database collumns to select
	 * @return bool
	 */
	public function read( $conditions = null, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		$collumns = implode( ", ", $collumns );

		if ( !is_null( $conditions ) ) {
			array_walk( $conditions, [$this, "clean"] );
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
		} else {
			$results = ["error" => "Record Not Found"];
		}

		return $results;
	}

	/**
	 * Method to search for records with given conditions - Uses regular expressions, not strict
	 * Don't use it directly, use find() instead and pass 'search' as the third argument(callable)
	 * @param array $conditions Constraints to apply to action, e.g ['title' => 'Sandal']
	 * @param array $collumns List of database collumns to select
	 * @return bool
	 */
	public function search( $conditions = null, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		$collumns = implode( ", ", $collumns );

		if ( !is_null( $conditions ) ) {
			array_walk( $conditions, [$this, "clean"] );
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
		} else {
			$results = ["error" => "Record Not Found"];
		}

		return $results;
	}

	/**
	 * Method to reset array index
	 * @param array $result The database query to reset
	 * @param int $row Index to reset to
	 * @return bool/mixed
	 */
	public function reset( $result, $row = 0 )
	{
		return $result -> reset( $row );
  	}

	/**
	 * Method to update existing record
	 * Don't use it directly, use insert() instead
	 * @param array $conditions Constraints to apply to action, e.g ['title' => 'Sandal']
	 * @param arry $values List of values to insert into table row
	 * @param array $collumns List of database collumns to update
	 * @return bool
	 */
	public function update( $conditions, $values, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		$collumns = implode(", ", $collumns);

		array_walk( $values, [$this, "clean"] );
		$nuvals = [];
		$colvals = array_combine( $collumns, $values);
		foreach ( $colvals as $collumn => $value ) {
			$nuvals[] = "{$collumn} = '{$value}'";
		}
		$values = implode(", ", $nuvals);

		array_walk( $conditions, [$this, "clean"] );
		$nuconds = [];
		foreach ($conditions as $key => $value) {
			$nuconds[] = "{$key} = '{$value}'";
		}
		$conditions = implode("AND ", $nuconds);

		$sql = "UPDATE {$this -> table} SET {$values} WHERE {$conditions}";
		return $this -> query( $sql );
	}

	/**
	 * Method to delete a record with given conditions
	 * @param array $conditions Constraints to apply to action, e.g ['id' => 2] OR ['status' => 'spam']
	 * @return bool
	 */
	public function remove( $conditions = null )
	{
		if ( !is_null( $conditions )) {
			array_walk( $conditions, [$this, "clean"] );

			$nuconds = [];
			foreach ($conditions as $key => $value) {
				$nuconds[] = "$key = '{$value}'";
			}
			$conditions = implode("AND ", $nuconds);

			$sql = "DELETE FROM ".$this -> table." WHERE {$conditions}";
		} else {
			$sql = "DELETE FROM ".$this -> table;
		}

		return $this -> query( $sql );
	}

	/**
	 * Method to order/sort $results array by a given key's value
	 * @param array $results Database query results to sort
	 * @param string $key The key whose value to order by
	 * @param string $order Way to order array, either Ascending(ASC) of Descending(DESC)
	 * @return array
	 */
	public function order( $results, $key, $order = "ASC" )
	{
		define( 'key', $key );
		usort( $results, function ( $a, $b )
		{
			return strcmp( $a[key], $b[key] );
		});

		if( $order == "DESC" ){
			array_reverse( $results );
		}
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
	 * @param array $collumns Database collumns for whose values to insert
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
				return;
			}
		}
	}

	/**
	 * Method for selecting records with given constraints
	 * @param array $conditions Constraints to apply to selection
	 * @param array $collumns Database collumns to select
	 * @param callable $callable Callback method - either read(strict) or search(flexible)
	 * @return array
	 */
	public function fetch( $conditions = null, $collumns = null, $callable = "read" )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;
		return $this -> $callable( $conditions, $collumns );
	}

	/**
	 * Method for selecting a single records with given constraints
	 * Conditions must be on primary keys/unique fields e.g [ 'id' => 1 ]
	 * @param array $conditions Constraints to apply to selection
	 * @param array $collumns Database collumns to select
	 */
	public function single( $conditions, $collumns = null )
	{
		$collumns = is_null( $collumns ) ? $this -> collumns : $collumns;

		$results = $this -> read( $conditions, $collumns );

		foreach ( $results[0] as $property => $value ) {
			$this -> $property = $value;
		}
	}

	public function delete( $conditions = null )
	{
		return $this -> remove( $this -> table, $conditions );
	}
}
