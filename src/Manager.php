<?php

namespace EWC\DB;

use EWC\DB\Exceptions\DBMCException;
use PDO;
use PDOException;
use EWC\Commons\Traits\TErrors;
use EWC\Config\Parser;
use EWC\Config\Interfaces\IConfigWrapper;
use EWC\Config\Exceptions\ConfigException;

/**
 * Class Manager
 * 
 * Manage DBMC connections.
 *
 * @version 1.0.0
 * @author Russell Nash <evil.wizard95@googlemail.com>
 * @copyright 2018 Evil Wizard Creation.
 * 
 * @uses	Connection For managed DBMC connections.
 * @uses	DBMCException For connection exceptions.
 * @uses	PDO For the database handler.
 * @uses	PDOException Catches named exception.
 * @uses	TErrors The errors Traits functionality.
 * @uses	Parser To load default configuration if none supplied.
 * @uses	IConfigWrapper To model the database config.
 * @uses	ConfigException Catches and throws named exceptions.
 */
class Manager {
	
	/*
	 * @var Manager The single instance of the DB Manager.
	 */
	protected static $ourInstance = NULL;
	
	/*
	 * @var	Array The list of PDO connection details for master and slave replication databases.
	 * 
	 * The array provides the following keys.
	 * <code>
	 *		[
	 *			"master"		=> Connection,
	 *			"slaves"		=> Array
	 *		];
	 * </code>
	 */
	protected $replicationConnections = [
		"master"	=> NULL,
		"slaves"	=> []
	];
	
	/*
	 * @var	Array The list of Connection objects indexed by host name.
	 */
	protected $connections = [];
	
	/*
	 * @var	Array An Alias to host map to access the correct Connection object.
	 */
	protected $connectionsMap = [];
	
	/*
	 * @var	String The actual current database name in use for the active connection.
	 */
	protected $currentDBName = NULL;
	
	/*
	 * @var	String The current database alias for the active connection.
	 */
	protected $currentDBAlias = NULL;
	
	/*
	 * @var	Connection The current PDO connection.
	 */
	protected $currentConnection = NULL;
	
	/*
	 * @var	IConfigWrapper The database config settings.
	 */
	protected $config;
	
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
	 * Manager constructor.
	 * 
	 * @param	IConfigWrapper $config The interactive config object to create connections with.
	 * @throws	DBMCException If unable to load connections.
	 * @throws	ConfigException If the connections or connection sub setcions are not an array or a valid JSON String.
	 * @throws	DBMCException If unable to make the PDO connection.
	 */
	protected function __construct(IConfigWrapper $config) {
		$this->config = $config;
		// get the connection details
		$connections_config = $this->config->getSubSection("connections");
		// loop through the connection aliases
		foreach($connections_config->getKeys() as $connection_alias) {
			try {
				// add the connection alias for use
				$this->addConnectionFromConfig($connections_config->getSubSection($connection_alias), $connection_alias);
			} catch (DBMCException $ex) {
			// unable to make a connection from the config
				$this->logException($ex);
			}
		}
		$default = $this->config->get("default_connection");
		// @todo set the connection as master
		// Set the default connection as the current PDO.
		$this->ensurePDOConnection($default);
	}

	/**
	 * Manager destructor.
	 */
	public function __destruct() { 
		static::$ourInstance = NULL;
	}
	
	/**
	 * Create an instance of the database connection management system using default config file.
	 * 
	 * @return	Manager The DBMC connection manager.
	 * @throws	DBMCException If unable to load connections.
	 * @throws	ConfigException If the connections or connection sub setcions are not an array or a valid JSON String.
	 */
	public static function &getInstance() {
		if(is_null(static::$ourInstance)) {
		// initialise the database connection management system using the default config
			static::createInstanceFromConfig(static::getDefaultConfig()->getSubSection("db"));
		}
		return static::$ourInstance;
	}
	
	/**
	 * Create an instance of the database connection management system using specified config.
	 * 
	 * @param	IConfigWrapper $config The interactive config object to create connections with.
	 * @return	Manager The DBMC connection manager.
	 * @throws	DBMCException If unable to load connections.
	 * @throws	ConfigException If the connections or connection sub setcions are not an array or a valid JSON String.
	 */
	public static function &createInstanceFromConfig(IConfigWrapper $config) {
		if(is_null(static::$ourInstance)) {
		// initialise the database connection management system
			static::$ourInstance = new static($config);
		}
		return static::$ourInstance;
	}
	
	/**
	 * Get the config from the default config location and filename.
	 * 
	 * @return	IConfigWrapper The parsed interactive config from the default location.
	 * @throws	DBMCException If unable to load the config from the default location.
	 */
	private static function getDefaultConfig() {
		$db_config_source = "/config/database.yml";
		try {
			// get the database config from the yaml source
			$config = Parser::load(APP_ROOT . $db_config_source, Parser::TYPE_YAML);
		} catch (ConfigException $ex) {
		// unable to load the config, log and rethrow
			$this->logException($ex);
			DBMCException::failedToLoadConfig($db_config_source, $ex);
		}
		return $config;
	}
	
	/**
	 * Add a connection alias from config details.
	 * 
	 * @param	IConfigWrapper $config The interactive config object to add connection from.
	 * @param	String $alias The connection alias name.
	 * @throws	DBMCException If unable to make the PDO connection.
	 */
	public function addConnectionFromConfig(IConfigWrapper $config, $alias) {
		// Check to see if the DSN host as already been added and a Connection object exists.
		if(!array_key_exists($config->get("dsn"), $this->connections)) {
			// Create the Connection object for the DSN host.
			$this->makeNewConnection($alias, $config);
		} else {
			// Add the connection as an alias of of the connections to the host.
			$this->makeAlias($alias, $config);
			// @todo Support multiple username connections on the same host.
		}
		// Add the alias name to map to the related host Connection .
		$this->connectionsMap[$alias] = $config->get("dsn");
	}


	/**
	 * Get the PDO connection object.
	 * 
	 * @param	String $alias The connection alias to get the PDO of.
	 * @param	Boolean $keepAlive Flag to force the connection to keep alive.
	 * @throws	DBMCException If the connection alias is unknown.
	 */
	public function ensurePDOConnection($alias=NULL, $keepAlive=FALSE) {
		// Check to see if an alias was supplied, this means a potential change
		// in database connection.
		if(is_null($alias)) {
			// No change in alias, so use the currently selected aliased connection.
			$connection = $this->currentConnection;
		} else {
			// Get the alias connection and set it as current.
			try {
				// try to get the connection and use the alias
				$connection = $this->getConnection($alias);
				$this->makeAliasCurrent($alias, $connection);
			} catch (DBMCException $ex) {
			// Unable to get the PDO connection
				$this->logException($ex);
				// pass the exception
				throw DBMCException::failedToGetPDOForAlias($alias, $ex);
			}
		}
		// return the PDO for the aliased connection.
		$connection->ensurePDOConnection($keepAlive);
	}
	
	/**
	 * Get the actual Connection object for the alias.
	 * 
	 * @param	String	$alias The connection alias of the database connection.
	 * @return	Connection The requested PDO connection.
	 * @throws	DBMCException If the connection alias is unknown.
	 */
	public function &getConnection($alias=NULL) {
		// If the alias is missing, just use the current DB alias
		if(is_null($alias)) {
			$alias = $this->currentDBAlias;
		}
		// Check to make sure the connection knows about the alias.
		if(!array_key_exists($alias, $this->connectionsMap)) {
			// The alias is unknown to the connection so throw the approriate exception.
			throw DBMCException::withUnknownConnectionAlias($alias);
		}
		// Return the Connection object using the alias map.
		return $this->connections[$this->connectionsMap[$alias]];
	}
	
	/**
	 * Get the database name for the alias.
	 * 
	 * @param	String $sAlias The connection alias to get the database name of.
	 * @throws	DBMCException If the alias is unknown.
	 * @return	String The database name for the alias connection.
	 */
	public function getDbName($sAlias) { return $this->currentConnection->getDatabaseForAlias($sAlias); }
	
	/**
	 * Pass-thru method to begin a transaction on the PDO connection.
	 * 
	 * @return	Boolean TRUE if the transaction was begun.
	 */
	public function beginTransaction() { return $this->currentConnection->getCurrentPDOConnection()->beginTransaction(); }

	/**
	 * Pass-thru method to roll back a transaction on the PDO connection.
	 * 
	 * @return	Boolean TRUE if the transaction was rolled back.
	 */
	public function rollBack() { return $this->currentConnection->getCurrentPDOConnection()->rollBack(); }

	/**
	 * Pass-thru method to commit a transaction on the PDO connection.
	 * 
	 * @return	Boolean TRUE on success or FALSE on failure.
	 */
	public function commit() { return $this->currentConnection->getCurrentPDOConnection()->commit(); }

	/**
	 * Pass-thru method to prepare an SQL statement on the PDO connection.
	 * 
	 * @param	String $statement The SQL statement to prepare.
	 * @throws	PDOException If there is an error in the SQL statement.
	 * @return	PDOStatement The prepared PDO Statement.
	 */
	public function prepare($statement) { return $this->currentConnection->getCurrentPDOConnection()->prepare($statement); }

	/**
	 * Pass-thru method to execute an SQL statement on the PDO connection.
	 * 
	 * @param	String $statement The SQL statement to execute.
	 * @return	Integer The number of rows affected by the last SQL.
	 */
	public function exec($statement) { return $this->currentConnection->getCurrentPDOConnection()->exec($statement); }

	/**
	 * Pass-thru method to run the SQL statement on the Connection.
	 * 
	 * @param	String $statement The SQL statement to run.
	 * @return	PDOStatement The PDP statement for the query.
	 */
	public function query($statement) { return $this->currentConnection->query($statement); }

	/**
	 * Pass-thru method to close the PDO Connection.
	 */
	public function close() { $this->currentConnection->close(); }

	/**
	 * Pass-thru method to quote a string.
	 * 
	 * @param	String $string The string to quote.
	 * @param	Integer $parameter_type Provides a data type hint for drivers that have alternate quoting styles.
	 * @return	String The quoted string.
	 */
	public function quote($string, $parameter_type = null) { return $this->currentConnection->getCurrentPDOConnection()->quote($string, $parameter_type); }

	/**
	 * Get the PDO error code.
	 * 
	 * @return	Integer The last error code.
	 */
	public function errorCode() { return $this->currentConnection->getPDOError("code"); }

	/**
	 * Get the PDO error info.
	 * 
	 * @return	Mixed The last error info.
	 */
	public function errorInfo() { return $this->currentConnection->getPDOError("info"); }
	
	/**
	 * Add the buffered query mode attribute to the PDO connection.
	 * 
	 * @return	Boolean TRUE if the attribute was set.
	 */
	public function bufferedMode() { return $this->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE); }

	/**
	 * Pass-thru method to the PDO setAttribute method.
	 * 
	 * @param	Integer $attribute The PDO attribute MYSQL_ATTR_* id.
	 * @param	Mixed $value The attribute value to set.
	 * @return	Boolean TRUE if the attribute was set.
	 */
	public function setAttribute($attribute, $value) { return $this->currentConnection->setAttribute($attribute, $value); }

	/**
	 * Pass-thru method to the PDO getAttribute method.
	 * 
	 * @param	Integer $attribute The PDO attribute MYSQL_ATTR_* id.
	 * @return	Mixed The attribute value.
	 */
	public function getAttribute($attribute) { return $this->currentConnection->getAttribute($attribute); }

	/**
	 * Get a list of available PDO drivers.
	 * 
	 * @return	Array A list of available PDO drivers.
	 */
	public function getAvailableDrivers() { return PDO::getAvailableDrivers(); }

	/**
	 * Pass-thru method to get the number of found rows in the previously executed SQL query.
	 * 
	 * @return	Integer The number of rows the last SQL would return.
	 */
	public function getFoundRows() { return $this->currentConnection->getFoundRows(); }
	
	/**
	 * Pass-thru method to check to see if any rows were in the previously executed SQL query.
	 * 
	 * @return	Boolean TRUE if the last SQL found rows or affected any rows.
	 */
	public function rowsFound() { return $this->currentConnection->rowsFound(); }

	/**
	 * Pass-thru method to get the last insert ID of the PDO connection.
	 * 
	 * @param	String $name Name of the sequence object from which the ID should be returned.
	 * @return	String The last inserted row ID.
	 */
	public function lastInsertId($name=null) { return $this->currentConnection->getCurrentPDOConnection()->lastInsertId($name); }
		
	/**
	 * Create a new DB Connection (a PDO connection wrapper).
	 * 
	 * @param	String $connection_alias The connection alias of the database connection.
	 * @param	IConfigWrapper $connection_config The config for the DB alias.
	 * @throws	DBMCException If unable to make the PDO connection.
	 */
	protected function makeNewConnection($connection_alias, IConfigWrapper $connection_config) {
		// @todo check alias name for _slave to use replication slaves and 
		//       seperate the configs and connections
		// get the connection alias Data Source Name
		$dsn = $connection_config->get("dsn");
		// create the new DB Connection (a PDO connection wrapper)
		$this->connections[$dsn] = new Connection(
										$connection_alias,
										$dsn,
										$connection_config->get("username"),
										$connection_config->get("password"),
										$connection_config->get("database"),
										$connection_config->get("port", 3306),
										$connection_config->get("use_mamp_sockets")
									);
	}
		
	/**
	 * Set the Connection and alias as the current connection.
	 * 
	 * @param	String $alias The connection alias of the database connection.
	 * @param	Connection $connection The Connection object of the alias.
	 * @throws	DBMCException If the specified alias has not been assigned.
	 */
	protected function makeAliasCurrent($alias, Connection &$connection) {
		$this->currentDBAlias = $alias;
		// set the connection alias
		$connection->useAlias($this->currentDBAlias);
		$this->currentDBName = $connection->getDatabaseForAlias($this->currentDBAlias);
		$this->currentConnection = $connection;
	}
		
	/**
	 * Check to see if the connection config to the host can be aliased.
	 * 
	 * @param	String $connection_alias The connection alias of the database connection.
	 * @param	IConfigWrapper $connection_config The config for the DB alias.
	 */
	protected function makeAlias($connection_alias, IConfigWrapper $connection_config) {
		// get the connection alias Data Source Name
		$alias_dsn = $connection_config->get("dsn");
		// Check to see if the connection config can be aliased to the 
		// same host connection.  Different users have different privledges.
		if($this->connections[$alias_dsn]->isAlias($connection_config->get("username"), $connection_config->get("password"))) {
			// add the database name as an alias for the connection.
			$this->connections[$alias_dsn]->addAlias($connection_alias, $connection_config->get("database"));
		}
	}
}