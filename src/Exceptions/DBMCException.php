<?php

namespace EWC\DB\Exceptions;

use RuntimeException;
use Exception;

/**
 * Exception DBMCException
 * 
 * Group DBMC connection exceptions and errors.
 *
 * @version 1.0.0
 * @author Russell Nash <evil.wizard95@googlemail.com>
 * @copyright 2018 Evil Wizard Creation.
 * 
 * @uses	RuntimeException As an exception base.
 * @uses	Exception To rethrow.
 */
class DBMCException extends RuntimeException {
	
	/**
	 * @var	Integer Code for general or unknown issues.
	 */
	const CODE_GENERAL = 0;
	
	/**
	 * @var	Integer Code for unknown connection alias.
	 */
	const CODE_UNKNOWN_ALIAS = 1;
	
	/**
	 * @var	Integer Code for no PDO connection for alias.
	 */
	const CODE_NO_PDO_CONNECTION = 2;
	
	/**
	 * @var	Integer Code for no database config loaded.
	 */
	const CODE_NO_CONFIG = 10;
	
	/**
	 * DBMCException constructor.
	 * 
	 * @param	String $message An error message for the exception.
	 * @param	Integer $code An error code for the exception.
	 * @param	Exception $previous An optional previously thrown exception.
	 */
    public function __construct($message, $code=DBMCException::CODE_GENERAL, Exception $previous=NULL)  {
        parent::__construct($message, $code, $previous);
    }
	
    /**
	 * Create an exception for unknown connection alias attempted.
	 * 
	 * @param	String $alias The connection alias name tried.
	 * @param	Exception $previous An optional previously thrown exception.
     * @return	DBMCException For unknown connection alias.
     */
	public static function withUnknownConnectionAlias($alias, Exception $previous=NULL) {
		return new static("Connection alias [{$alias}] is an unknown connection.", static::CODE_UNKNOWN_ALIAS, $previous);
	}
	
    /**
	 * Create an exception for failing to get a PDO connection for a connection alias.
	 * 
	 * @param	String $alias The connection alias name.
	 * @param	Exception $previous An optional previously thrown exception.
     * @return	DBMCException For unable to get PDO connection for an alias.
     */
	public static function failedToGetPDOForAlias($alias, Exception $previous=NULL) {
		return new static("Unable to get PDO connection for alias [{$alias}].", static::CODE_NO_PDO_CONNECTION, $previous);
	}
	
    /**
	 * Create an exception for failing to get a PDO connection for a connection failure.
	 * 
	 * @param	String $user The username used.
	 * @param	String $host The data source name. connecting to
	 * @param	String $dsn The data source name connecting to
	 * @param	Exception $previous An optional previously thrown exception.
     * @return	DBMCException For unable to get PDO connection.
     */
	public static function failedToGetPDO($user, $host, $dsn, Exception $previous=NULL) {
		return new static("PDO Connection Failed for [{$user}]@[{$host}] using [{$dsn}].", static::CODE_NO_PDO_CONNECTION, $previous);
	}
	
    /**
	 * Create an exception for failing to load database config.
	 * 
	 * @param	String $config_file The database config source filename.
	 * @param	Exception $previous An optional previously thrown exception.
     * @return	DBMCException For unable to load database config.
     */
	public static function failedToLoadConfig($config_file, Exception $previous=NULL) {
		return new static("Unable to load database config from [{$config_file}].", static::CODE_NO_CONFIG, $previous);
	}
}
