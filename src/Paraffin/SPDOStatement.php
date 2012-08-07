<?php
namespace Paraffin;

/**
 * Statement class that sets its own fetch mode to the given class.
 * Used by the PDO object in the Paraffin class.
 */

class SPDOStatement extends \PDOStatement {

	private $class;

	/**
	 * Constructor
	 *
	 * Set the class returned by this statement object.
	 * @param mixed $class Class definition
	 */
	protected function __construct ($class=StdClass) {
		$this->class = $class;
		if ($class)
			$this->setFetchMode(PDO::FETCH_CLASS, $this->class);
	}
}
