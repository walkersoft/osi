<?php
/**
 * Manufacturer Data Type.
 *
 * Class definition for a manufacturer object.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Datatypes
 */
 
class Manufacturer
{
	/**
	 * The manufacturer's ID number.
	 *
	 * @var int
	 */
	private $manufacturerID;
	
	/**
	 * The name of the manufacturer.
	 *
	 * @var string
	 */
	private $manufacturerName;
	
	/**
	 * Constructor function.
	 *
	 * Creates a manufacturer object from data read in.
	 *
	 * @param mixed An array or object with manufacturer data.
	 */
	public function __construct($data) {
		if(is_array($data)) {
			$this->setManufacturerID($data[0]);
			$this->setManufacturerName($data[1]);
		} else if(is_object($data)) {
			$this->setManufacturerID($data->ManufacturerID);
			$this->setManufacturerName($data->Name);
		} else {
			print("Manufacturer information is not in the correct format.");
		}
	}
	
	/**
	 * Returns the manufacturer's ID number.
	 *
	 * @return int The <code>manufacturerID</code> assigned or retrieved from the database.
	 */
	public function getManufacturerID() {
		return $this->manufacturerID;
	}
	
	/**
	 * Returns the manufacturer's name.
	 *
	 * @return string The <code>manufacturerName</code> assigned to the manufacturer.
	 */
	public function getManufacturerName() {
		return $this->manufacturerName;
	}
	
	/**
	 * Sets the manufacturer's ID number.
	 *
	 * @param int The id number of the permission mask.
	 */
	public function setManufacturerID($int) {
		$this->manufacturerID = $int;
	}
	
	/**
	 * Sets the manufacturer's name.
	 *
	 * @param string The name of the manufacturer.
	 */
	public function setManufacturerName($str) {
		$this->manufacturerName = $str;
	}
	
	/**
	 * Generates SQL code to update the manufacturer record.
	 *
	 * Creates the needed SQL code to update the manufacturer based on it's current values.
	 * 
	 * @return string The SQL code to send to the database.
	 */
	public function generateUpdateSQL() {
		$q = "UPDATE OSIManufacturers SET Name = '".$this->getManufacturerName()."' WHERE ManufacturerID = ".$this->getManufacturerID();
		return $q;
	}
	
	/**
	 * Generates SQL code to delete the manufacturer record.
	 *
	 * Creates the needed SQL code to delete the manufacturer based on the <code>manufacturerID</code>.
	 *
	 * @return string The SQL code to send to the database.
	 */
	public function generateDeleteSQL() {
		$q = "DELETE FROM OSIManufacturers WHERE ManufacturerID = ".$this->getManufacturerID();
		return $q;
	}
	
	/**
	 * Dumps information about the manufacturer object.
	 *
	 * Dumps object information to the browser.  Useful for troubleshooting the object.
	 *
	 * @return void
	 */
	public function dump() {
		print_r($this);
	}
}

?>