<?php

namespace EWC\DB;

use EWC\DB\Exceptions\DBMCException;
use EWC\Commons\Traits\TErrors;
use PDO;
use PDOException;

/**
 * Class Connection
 * 
 * Wrapper class to simulate multiple connections to a single database server with 
 * multiple databases hosted using a single connection.
 *
 * @version 1.0.0
 * @author Russell Nash <evil.wizard95@googlemail.com>
 * @copyright 2018 Evil Wizard Creation.
 * 
 * @uses	TErrors The errors Traits functionality.
 * @uses	DBMCException For connection exceptions.
 * @uses	PDO For the database handler.
 * @uses	PDOException Catches named exception.
 */
class Connection {
	
	/*
	 * @var	String The connection name.
	 */
	protected $name = NULL;
	
	/*
	 * @var	Array The list of connection alias names.
	 */
	protected $aliases = [];
	
	/*
	 * @var	String The database connection host.
	 */
	protected $host = NULL;
	
	/*
	 * @var	String The database connection port.
	 */
	protected $port = NULL;
	
	/*
	 * @var	String The database connection username.
	 */
	protected $username = NULL;
	
	/*
	 * @var	String The database connection password.
	 */
	protected $password = NULL;
	
	/*
	 * @var	String The database name.
	 */
	protected $database = NULL;
	
	/*
	 * @var	String The Data Source Name connection string.
	 */
	protected $dsn = NULL;
	
	/*
	 * @var	String The current database name in use.
	 */
	protected $currentDatabase = NULL;
	
	/*
	 * @var	String The current database alias in use.
	 */
	protected $currentDatabaseAlias = NULL;
	
	/*
	 * @var PDO The actual PDO connection object.
	 */
	protected $oPDOConnection = NULL;
	
	/**
	 * Include the TErrors traits.
	 * 
	 * Adds the following methods for public access.
	 * 
	 * getLastError()
	 * getErrors()
	 * 
	 * Adds the following methods for internal access.
	 * 
	 * addError($error, $trigger=FALSE)
	 */
	use TErrors;
	
	/**
	 * Connection constructor.
	 * 
	 * @param	String $host The database host name.
	 * @param	String $username The database username.
	 * @param	String $password The password for the database username.
	 * @param	String $database The database name to connect to and use.
	 * @param	String $port The port number to connect to the database host on.
	 * @param	Boolean $use_mamp_sockets Flag to idication the need to use MAMP socket configuration.
	 * @throws	DBMCException If unable to make the PDO connection.
	 */
	public function __construct($name, $host, $username, $password, $database, $port="3306", $use_mamp_sockets=TRUE) {
		// Set the main connection config details.
		$this->name = $name;
		$this->host = $host;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->port = $port;
		// Add as an alias to make things easier later.
		$this->addAlias($this->name, $this->database);
		$this->dsn = "mysql:dbname={$this->database};host={$this->host};port={$this->port}";
		// Check to see if the unix socects needs adding for MAMP developers.
		if($use_mamp_sockets) {
			$this->dsn .= ";unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock";
		}
		// Set the connection batabase as the current database name.
		$this->currentDatabase = $this->database;
		$this->currentDatabaseAlias = $this->name;
		$this->makeConnection();
		// Enable big selects.
		$this->query("SET SQL_BIG_SELECTS=1");
	}
		
	/**
	 * Pass-thru method to the PDO setAttribute method.
	 * 
	 * @param	Integer $attribute The PDO attribute MYSQL_ATTR_* id.
	 * @param	Mixed $value The attribute value to set.
	 * @return	Boolean The result of the passed call.
	 */
	public function setAttribute($attribute, $value) { return $this->getConnection()->setAttribute($attribute, $value); }
		
	/**
	 * Pass-thru method to the PDO getAttribute method.
	 * 
	 * @param	Integer	$attribute	The PDO attribute MYSQL_ATTR_* id.
	 * @return	Mixed The result of the passed call.
	 */
	public function getAttribute($attribute) { return $this->getConnection()->getAttribute($attribute); }
	
	/**
	 * Get the connection port.
	 * 
	 * @return	String The connection port.
	 */
	public function getPort() { return $this->port; }
		
	/**
	 * Get the database host name.
	 * 
	 * @return	String The database host name.
	 */
	public function getHost() { return $this->host; }
	
	/**
	 * Get the current database name.
	 * 
	 * @return	String The current database name.
	 */
	public function getDatabase() { return $this->currentDatabase; }
	
	/**
	 * Add a database name alias to simulate connection switching.
	 * 
	 * @param	String $alias The connection alias name.
	 * @param	String $database The alias connection database name.
	 */
	public function addAlias($alias, $database) { $this->aliases[$alias] = $database; }
	
	/**
	 * Switch the connection to the specified alias database name for use.
	 * 
	 * @param	String $alias The connection alias name.
	 * @throws	DBMCException If the specified alias has not been assigned.
	 */
	public function useAlias($alias) {
		// Check to make sure the connection knows about the alias.
		if(!array_key_exists($alias, $this->aliases)) {
		// The alias is unknown to the connection so throw the approriate exception.
			// errors will be logged by the connection manager
			throw DBMCException::withUnknownConnectionAlias($alias);
		}
		// Check to see if the alias to use is different from the current one.
		$aliasDBName = $this->aliases[$alias];
		if($aliasDBName != $this->currentDatabase) {
			// Set the current database to that of the alias and switch databases.
			$this->currentDatabase = $aliasDBName;
			$this->switchDatabase();
		}
	}
	
	/**
	 * Check to see if the username and password are the same as the connection.
	 * 
	 * @return	Boolean True if the username and password match the ones set.
	 */
	public function isAlias($username, $password) { return ($this->username == $username && $this->password == $password); }
	
	/**
	 * Get the database name for the alias.
	 * 
	 * @param	String $alias The connection alias to get the database name of.
	 * @return	String The database name for the alias.
	 * @throws	DBMCException If the alias has not been defined.
	 */
	public function getDatabaseForAlias($alias) {
		// Check to make sure the connection knows about the alias.
		if(!array_key_exists($alias, $this->aliases)) {
		// The alias is unknown to the connection so throw the approriate exception.
			// errors will be logged by the connection manager
			throw DBMCException::withUnknownConnectionAlias($alias);
		}
		return $this->aliases[$alias];
	}
	
	/**
	 * Get the current PDO connection resource.
	 * 
	 * @return	PDO The current PDO connection resource.
	 */
	public function &getCurrentPDOConnection() { return $this->oPDOConnection; }
	
	/**
	 * Ensure there is a current PDO connection set.
	 * 
	 * @param	Boolean $keepAlive Flag to force the connection to keep alive.
	 */
	public function ensurePDOConnection($keepAlive=FALSE) {
		// Check to see if the connection needs a keep alive SQL.
		if($keepAlive) {
			$this->keepAlive();
		} else {
		// make sure the PDO connection is actually set
			$this->getConnection();
		}
	}
	
	/**
	 * Trigger a keep alive SQL on the connection.
	 * 
	 * @return Connection An instance of self for stacking methods.
	 */
	public function keepAlive() {
		// Make sure the MySQL Server has not gone away during the script run.
		$this->query("SELECT 1", 1);
		return $this;
	}
	
	/**
	 * Run an SQL on the connection with optional retry attempts.
	 * 
	 * @param	String $sql The SQL statement to execute.
	 * @param	Integer $retry Number of retry attempts if any.
	 * @return	PDOStatement The PDO query statement object.
	 * @throws	DBMCException If unable to make the PDO connection.
	 */
	public function query($sql, $retry=0) {
		try {
			// Run the SQL on the PDO connection.
			$stmt = $this->getConnection()->query($sql);
		} catch (PDOException $ex) {
			// @todo Log SQL failure
			$this->logException($ex);
			// @todo Check if it is a MySQL Server has gone away error and needs 
			//       to reconnect.
			if($retry) {
				// Server went away, make a new connection.
				$this->makeConnection();
				// retry the statement after the reconnection.
				$stmt = $this->query($sql, --$retry);
			}
		}
		return $stmt;
	}
	
	/**
	 * Get the number of found rows in the previously executed SQL query.
	 * 
	 * @return	Integer The number of found rows in the last query.
	 */
	public function getFoundRows() { return $this->query("SELECT FOUND_ROWS()")->fetchColumn(); }
	
	/**
	 * Check to see if any rows were in the previously executed SQL query.
	 * 
	 * @return	Boolean True if rows were found in the last query.
	 */
	public function rowsFound() { return ($this->getFoundRows()->fetchColumn() > 0) ? TRUE : FALSE; }
	
	/**
	 * Determine the type of error information to return.
	 * 
	 * @param	String $type Either the error code or error info.
	 * @return	Mixed The PDO error code or info.
	 */
	public function getPDOError($type=NULL) {
		switch(strtolower($type)) {
			case "code" :
				$error = $this->getConnection()->errorCode();
			break;
			case "info" :
				$error = $this->getConnection()->errorInfo();				
			break;
		}
		return $error;
	}
	
	/**
	 * Close the PDO Connection.
	 */
	public function close() {
		// Close the PDO connection by setting the reference to NULL.
		$this->oPDOConnection = NULL;
	}

	/**
	 * Get a string representation of the DBMCConnectionWrapper object.
	 * 
	 * @return	String The connection details.
	 */
	public function __toString() {
		$_ = "";
		$_ .= "DSN: {$this->dsn}\n";
		$_ .= "Host: {$this->host}\n";
		$_ .= "Port: {$this->port}\n";
		$_ .= "Alias Name: {$this->name}\n";
		$_ .= "Alias DB: {$this->database}\n";
		$_ .= "Current Alias: {$this->currentDatabaseAlias}\n";
		$_ .= "Current DB: {$this->currentDatabase}\n";
		// @todo Add the DB aliases for the connection
		// @todo Add the PDO connections attributes
		return $_;
	}

	/**
	 * Make the database change on the connection.
	 * Issue the USE statement to change databases on the connection.
	 */
	protected function switchDatabase() { $this->query("USE {$this->currentDatabase}", 1); }
	
	/**
	 * Create the PDO connection from the supplied credentials.
	 * 
	 * @return	PDO The PDO connection.
	 * @throws	DBMCException If unable to make the PDO connection.
	 */
	protected function &getConnection() {
		// Check that the connection is still a valid PDO connection.
		if(is_null($this->oPDOConnection) || !($this->oPDOConnection instanceof PDO)) {
		// remake the connection
			$this->makeConnection();
		}
		// return the active PDO connection
		return $this->oPDOConnection;
	}
	
	/**
	 * Create the PDO connection from the supplied credentials.
	 * 
	 * @throws	DBMCException If unable to make the PDO connection.
	 */
	protected function makeConnection() {
		try {
			// Try to create the PDO connection object from the credentials.
			$this->oPDOConnection = new PDO($this->dsn, $this->username, $this->password);
			// Make the PDO use persistent DB connections.
			$this->setAttribute(PDO::ATTR_PERSISTENT, TRUE);
			// Make the PDO use Exceptions instead of returning FALSE.
			$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// check to see if the default database is different from the database in use.
			if($this->database != $this->currentDatabase) {
				// Switch back to using the database before the new connection was made.
				$this->switchDatabase();
			}
		} catch (PDOException $ex) {
		// unable to create the PDO connection
			throw DBMCException::failedToGetPDO($this->name, $this->username, $this->dsn, $ex);
		}
	}
}