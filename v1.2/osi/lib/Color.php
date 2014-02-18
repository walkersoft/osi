<?php
/**
 * Color Data Type.
 *
 * Class definition for a color object.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Datatypes
 */
 
class Color
{
	/**
	 * The ID number of the color record.
	 *
	 * @var int
	 */
	private $colorID;
	
	/**
	 * The <code>Manufacturer</code> object in which the color is assigned.
	 *
	 * @var Manufacturer
	 */
	private $manufacturer;
	
	/**
	 * The color's name.
	 *
	 * @var string
	 */
	private $name;
	
	/**
	 * The color's manufacturer specific identifier.
	 *
	 * @var 
	 */
	private $reference;
	
	/**
	 * The sealant object this color is considered to be a match.
	 *
	 * @var Sealant
	 */
	private $osiMatch;
	
	/**
	 * The sealant object this color is considered to be a near-match.
	 *
	 * @var Sealant
	 */
	private $osiMustCoat;
	
	/**
	 * The light reflectance value of the color.
	 *
	 * @var int
	 */
	private $lrv;
	
	/**
	 * The timestamp of when the record was last manipulated.
	 *
	 * @var timestamp
	 */
	private $lastAccessed;
	
	/**
	 * The <code>UserID</code> number of the user who last interacted with the color record.
	 *
	 * @var int
	 */
	private $lastUser;
	
	/**
	 * Constructor function.
	 *
	 * Creates a color object from information read in.
	 *
	 * @param mixed An array or object of data about a color.
	 * @param object A database object or instance of a <code>Manufacturer</code> object that is assigned to the color.
	 */
	public function __construct($data,$man) {
		if(is_array($data)) {
			if(is_object($man)) {
				if(is_object($data[3]) && is_object($data[4])) {
					$this->setColorID($data[0]);
					$this->setManufacturer($man);
					$this->setName($data[1]);
					$this->setReference($data[2]);
					$this->setOSIMatch($data[3]);
					$this->setOSIMustCoat($data[4]);
					$this->setLRV($data[5]);
					$this->setLastAccessedTime($data[6]);
					$this->setLastAccessedUser($data[7]);
				} else {
					print("Color: Sealant data is not in the correct format.\n");
				}
			} else {
				print("Color: Manufacturer data is not in the correct format.\n");
			}
		} else if(is_object($data)) {			
			if(is_object($man)) {
				if(is_object($data->OSIMatch) && is_object($data->OSIMustCoat)) {
					$this->setColorID($data->ColorID);
					$this->setManufacturer($man);
					$this->setName($data->Name);
					$this->setReference($data->Reference);
					$this->setOSIMatch($data->OSIMatch);
					$this->setOSIMustCoat($data->OSIMustCoat);
					$this->setLRV($data->LRV);
					$this->setLastAccessedTime($data->LastAccessed);
					$this->setLastAccessedUser($data->LastAccessedUser);
				} else {
					print("Color: Sealant data is not in the correct format.\n");
				}
			} else {
				print("Color: Color data is not in the correct format.\n");
			}
		}
	}
	
	/**
	 * Gets the ID number of the color assigned or retrieved from the database.
	 *
	 * @return int The <code>colorID</code> of the color record.
	 */
	public function getColorID() {
		return $this->colorID;
	}
	
	/**
	 * Gets the manufacturer object assigned to the color.
	 *
	 * @return Manufacturer The <code>Manufacturer</code> object that the color is associated with.
	 */
	public function getManufacturer() {
		return $this->manufacturer;
	}
	
	/**
	 * Gets the name of the color.
	 *
	 * @return string The <code>name</code> of the color.
	 */
	public function getName() {
		return $this->name;
	}
	
	/**
	 * Gets the manufacturer specific color identifier.
	 *
	 * @return string The <code>reference</code> of the color.
	 */
	public function getReference() {
		return $this->reference;
	}
	
	/**
	 * Gets the sealant object of the color that is a match.
	 *
	 * @return Sealant The <code>Sealant</code> object that is a color considered to be a match to the color record.
	 */
	public function getOSIMatch() {
		return $this->osiMatch;
	}
	
	/**
	 * Gets the sealant object of the color is a near-match.
	 *
	 * @return Sealant The <code>Sealant</code> object this is a color considered to be a near match to the color record.
	 */
	public function getOSIMustCoat() {
		return $this->osiMustCoat;
	}
	
	/**
	 * Gets the LRV.
	 *
	 * @return int The light reflectance value (LRV) of the color.
	 */
	public function getLRV() {
		return $this->lrv;
	}
	
	/**
	 * Gets the time when the record was last accessed.
	 *
	 * @return timestamp The time when the record was last accessed.
	 */
	public function getLastAccessedTime() {
		return $this->lastAccessed;
	}
	
	/**
	 * Gets the ID number of the user that last accessed the color.
	 *
	 * @return int The <code>userID</code> of the <code>User</code> that last manipulated the record.
	 */
	public function getLastAccessedUser() {
		return $this->lastUser;
	}
	
	/**
	 * Sets the <code>colorID</code> of the color.
	 *
	 * @param int The ID number of the color assined or retrieved from the database.
	 */
	public function setColorID($int) {
		$this->colorID = $int;
	}
	
	/**
	 * Sets the <code>manufacturer</code> of the color.
	 *
	 * @param Manufacturer The <code>Manufacturer</code> object associated with the color.
	 */
	public function setManufacturer($obj) {
		$this->manufacturer = $obj;
	}
	
	/**
	 * Sets the <code>name</code> of the color.
	 *
	 * @param string The color's name.
	 */
	public function setName($str) {
		$this->name = $str;
	}
	
	/**
	 * Sets the <code>reference</code> of the color.
	 *
	 * @param string The color's manufacturer specific reference or ID number.
	 */
	public function setReference($str) {
		$this->reference = $str;
	}
	
	/**
	 * Sets the <code>osiMatch</code> of the color.
	 *
	 * @param mixed The color's Sealant object that is a match.
	 */
	public function setOSIMatch($obj) {
		if($obj instanceof Sealant) {
			$this->osiMatch = $obj;
		} else {
			$this->osiMatch = new Sealant($obj);
		}
	}
	
	/**
	 * Sets the <code>osiMustCoat</code> of the color.
	 *
	 * @param mixed The color's Sealant object that is a near match.
	 */
	public function setOSIMustCoat($obj) {
		if($obj instanceof Sealant) {
			$this->osiMustCoat = $obj;
		} else {
			$this->osiMustCoat = new Sealant($obj);
		}
	}
	
	/**
	 * Sets the <code>lrv</code> of the color.
	 *
	 * @param int The color's LRV index.
	 */
	public function setLRV($int) {
		$this->lrv = $int;
	}
	
	/**
	 * Sets the <code>lastAccessed</code> time.
	 *
	 * @param timestamp The timestamp of when the color was last accessed.
	 */
	public function setLastAccessedTime($str) {
		$this->lastAccessed = $str;
	}
	
	/**
	 * Sets the <code>lastUser</code> id number.
	 *
	 * @param int The ID number of the last user who interacted with the color.
	 */
	public function setLastAccessedUser($int) {
		$this->lastUser = $int;
	}
	
	/**
	 * Generates SQL code to delete the color record from the database.
	 *
	 * Generates the needed SQL code to delete the color record based on the color's ID number.
	 *
	 * @return string The SQL code to send to the database.
	 */
	public function generateDeleteSQL() {
		$q = "DELETE FROM OSIColors WHERE ColorID = ".$this->getColorID();
		return $q;
	}
	
	/**
	 * Dumps information about the color object.
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