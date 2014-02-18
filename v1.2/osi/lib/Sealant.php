<?php
/**
 * Sealant Data Type.
 *
 * Class definition for a sealant object.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Datatypes
 */
 
class Sealant
{
	/**
	 * The sealant's ID number.
	 *
	 * @var int
	 */
	private $sealantID;
	
	/**
	 * Specifies if the sealant is stocked locally by CSI.
	 *
	 * @var boolean
	 */
	private $localStock;
	
	/**
	 * Specifies if the sealant is stocked by a vendor of CSI.
	 *
	 * @var boolean
	 */
	private $vendorStock;
	
	/**
	 * Constructor function.
	 *
	 * Creates a sealant object based on data read in.
	 *
	 * @param mixed An array or object with sealant information.
	 */
	public function __construct($data) {
		if(is_array($data)) {
			$this->setSealantID($data[0]);
			$this->setLocalStock($data[1]);
			$this->setVendorStock($data[2]);
		} else if(is_object($data)) {
			$this->setSealantID($data->SealantID);
			$this->setLocalStock($data->LocalStock);
			$this->setVendorStock($data->VendorStock);
		} else {
			print("Sealant: Sealant information is not in the correct format.");
		}
	}
	
	/**
	 * Gets the ID number of the sealant.
	 *
	 * @return int The <code>sealantID</code> assigned to it or retrieved from the database.
	 */
	public function getSealantID() {
		return $this->sealantID;
	}
	
	/**
	 * Gets whether or not the sealant is stocked locally.
	 * 
	 * @return boolean TRUE if sealant is stocked locally or FALSE if not.
	 */
	public function getLocalStock() {
		return $this->localStock;
	}
	
	/**
	 * Gets whether or not the sealant is stocked by a vendor company.
	 *
	 * @return boolean TRUE if sealant is stocked by a vendor or FALSE if not.
	 */
	public function getVendorStock() {
		return $this->vendorStock;
	}
	
	/**
	 * Set the sealant's ID.
	 *
	 * @param string The OSI identifier of the sealant.
	 */
	public function setSealantID($str) {
		$this->sealantID = $str;
	}
	
	/**
	 * Set whether or not CSI stocks the sealant.
	 *
	 * @param boolean TRUE if stocked by CSI or FALSE if not.
	 */
	public function setLocalStock($bool) {
		$this->localStock = (bool) $bool;
	}
	
	/**
	 * Set whether or not a CSI vendor stocks the sealant.
	 *
	 * @param boolean TRUE if stocked by a vendor or FALSE if not.
	 */
	public function setVendorStock($bool) {
		$this->vendorStock = (bool) $bool;
	}
	
	/**
	 * Generates SQL code to update the sealant record.
	 *
	 * Creates the necessary code to update a sealant record in the database.
	 *
	 * @return string The SQL code to send to the database.
	 */
	public function generateUpdateSQL() {
		$q = "UPDATE OSISealants SET LocalStock = '".$this->getLocalStock()."', VendorStock = '".$this->getVendorStock()."' WHERE SealantID = '".$this->getSealantID()."'";
		return $q;
	}
	
	/**
	 * Generates SQL code to delete the sealant record.
	 *
	 * Creates the necessary code to delete the sealant record from the database.
	 *
	 * @return string The SQL code to send to the database.
	 */
	public function generateDeleteSQL() {
		$q = "DELETE FROM OSISealants WHERE SealantID = '".$this->getSealantID()."'";
		return $q;
	}
	
	/**
	 * Dumps information about the sealant object.
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