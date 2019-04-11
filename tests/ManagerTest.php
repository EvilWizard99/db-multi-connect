<?php 

/**
 * Corresponding Test Class for \EWC\DB\Manager
 * 
 * @version 1.0.0
 * @author Russell Nash <evil.wizard95@googlemail.com>
 * @copyright 2019 Evil Wizard Creation.
 */
class ManagerTest extends PHPUnit_Framework_TestCase{
	
	/**
	 * Just check if the Manager has no syntax error 
	 */
	public function testIsThereAnySyntaxError() {
		define(APP_ROOT, dirname(__FILE__));
		$this->assertTrue(is_object(\EWC\Config\Manager::getInstance()));
	}
  
}