<?php

namespace EWC\DB;

use PDO;
use PDOStatement;

/**
 * PDOStatementWrapper Utility Class
 * 
 * @version 1.0.0
 * @author Russell Nash <evil.wizard95@googlemail.com>
 * @copyright 2019 Evil Wizard Creation.
 * 
 * @uses	PDO For parameter type constants.
 * @uses	PDOStatement For the data resultset.
 */
class PDOStatementWrapper {
	
	/*
	 * @var	Array The list of connection alias names.
	 */
	protected $index_by_field_names = [];
	
	/**
	 * Decide the correct PDO data type constraint of the variable value.
	 *
	 * @param	Mixed $var the variable value to determine type of.
	 * @return	Integer the PDO data type enum.
	 */
	public static function getPDOConstantType($var) {
		switch(TRUE) {
			case is_int($var): return PDO::PARAM_INT;
			case is_bool($var): return PDO::PARAM_BOOL;
			case is_null($var): return PDO::PARAM_NULL;
			default: return PDO::PARAM_STR;
		}
	}
	
	/**
	 * Get the list of field names used in an indexResultsBy SQL result set.
	 * 
	 * @return	Array List of field names.
	 */
	public function getIndexResultsByFieldNames() { return $this->index_by_field_names; }
	
    /**
     * Get the full results SQL query indexed by field values.
     *
     * @param	PDOStatement $stmt The PDOStatement of the executed query.
     * @param	Mixed $field The field/array of field names to index the result set by.
     * @param	Boolean $field_names One of the PDO::FETCH_* constants.
     * @param	Integer $fetch_style One of the PDO::FETCH_* constants.
     * @return	Array The results array indexed by the specified field value.
     */
	public function indexResultsBy(PDOStatement $stmt, $field, $field_names=FALSE, $fetch_style=PDO::FETCH_ASSOC) {
		// set the return result as an empty array
		$results = [];
		// set the flag to make sure the index by keys are checked first time round
		$key_check_flag = $val_check_flag = FALSE;
		if($field_names) {
		// reset the index by field names
			$this->index_by_field_names = [];
		}
		// loop through the results
		while(($result = $stmt->fetch($fetch_style))):
			// check if the index by is a single field or by two or more (complex index by)
			if(is_array($field)) {
				// check if the index keys are in the resultset
				if($key_check_flag || $field == array_intersect($field, array_keys($result))) {
					// set the flag to not bother checking index keys again
					$key_check_flag = TRUE;
					if($concat) {
						$results[$result[$field[0]] . '-' . $result[$field[1]]] = $result;
					} else {
						// hacky to get a double index
						// could be done with eval but lesser of two evals eh ;oP
						$results[$result[$field[0]]][$result[$field[1]]] = $result;
					}
				} else {
					// fall back to normal result return
					$results[] = $result;
				}
			} else {
				// check if the index key are in the resultset
				if($key_check_flag || array_key_exists($field, $result)) {
					// set the flag to not bother checking index keys again
					$key_check_flag = TRUE;
					$results[$result[$field]] = $result;
				} else {
					// fall back to normal result return
					$results[] = $result;
				}
			}
		if($field_names && !count($this->index_by_field_names)) {
			$this->index_by_field_names = array_keys($result);
		}
		endwhile;
		// return the array
		return $results;
	}
}
