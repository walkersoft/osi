<?php
/**
 * Permission Mask Data Type
 *
 * Class definition for a permissions object.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Datatypes
 */
 
class Permissions
{
	/**
	 * The MaskID that is assigned or was retrieved from the database.
	 *
	 * @var int
	 */
	private $maskID;
	
	/**
	 * The name assigned to the permissions mask.
	 *
	 * @var string
	 */
	private $maskName;
	
	
	/**
	 * Specifies if this mask allows editing of user accounts.
	 *
	 * @var boolean
	 */
	private $manageUsers;
	
	/**
	 * Specifies is this mask allows editing of color records.
	 *
	 * @var boolean
	 */
	private $manageColors;
	
	/**
	 * Specifies if this mask allows editing of sealant inventory records.
	 *
	 * @var boolean
	 */
	private $manageInventory;
	
	/**
	 * Specifies if this mask allows editing of system options.
	 *
	 * @var boolean
	 */
	private $manageOptions;
	
	/**
	 * Specifies if this mask allows management of the database.
	 *
	 * @var boolean
	 */
	private $manageDatabase;
	
	/**
	 * Specifies if this mask allows browsing of color records.
	 *
	 * @var boolean
	 */
	private $browseColors;
	
	/**
	 * A count of the users wearing this permission mask.
	 *
	 * @var int
	 */
	private $memberCount;
	
	/**
	 * Constructor function.
	 * 
	 * Creates a new permission mask with information provided.
	 *
	 * @return void
	 * @param mixed An array or object of permission data to be read in.
	 */
	public function __construct($data) {
		if(is_array($data)) {
			$this->setMaskName($data[0]);
			$this->setPermissions($data);
		} else if(is_object($data) || $data instanceof Permissions) {
			$this->setMaskID($data->MaskID);
			$this->setMaskName($data->MaskName);
			$this->setPermissions($data);
		}
	}
	
	/**
	 * Sets the permissions for the mask.
	 *
	 * Takes an array, database object, or an instance of a <code>Permissions</code> object with settings to assign to the mask.
	 *
	 * @return void
	 * @param mixed An array or object of mask settings.
	 */
	public function setPermissions($mixed) {
		if(is_array($mixed)) {
			array_shift($mixed);
			$this->manageUsers = (bool) $mixed[0];
			$this->manageColors = (bool) $mixed[1];
			$this->manageInventory = (bool) $mixed[2];
			$this->manageOptions = (bool) $mixed[3];
			$this->manageDatabase = (bool) $mixed[4];
			$this->browseColors = (bool) $mixed[5];
		} else if(is_object($mixed)) {
			$this->manageUsers = (bool) $mixed->ManageUsers;
			$this->manageColors = (bool) $mixed->ManageColors;
			$this->manageInventory = (bool) $mixed->ManageInventory;
			$this->manageOptions = (bool) $mixed->ManageOptions;
			$this->manageDatabase = (bool) $mixed->ManageDatabase;
			$this->browseColors = (bool) $mixed->BrowseColors;
		}
	}
	
	/**
	 * Sets the ID number of the permission mask.
	 *
	 * @return void
	 * @param int The mask ID number.
	 */
	public function setMaskID($int) {
		$this->maskID = $int;
	}
	
	/**
	 * Sets the name of the permission mask.
	 *
	 * @return void
	 * @param string The name of the permission mask.
	 */
	public function setMaskName($str) {
		$this->maskName = $str;
	}
	
	/**
	 * Returns the ID number of the permission mask.
	 *
	 * @return int The ID number assigned or retrieved from the database.
	 */
	public function getMaskID() {
		return $this->maskID;
	}
	
	/**
	 * Returns the mask's display name.
	 * 
	 * @return string The name assigned to the mask.
	 */
	public function getMaskName() {
		return $this->maskName;
	}
	
	/**
	 * Gets the member count.
	 *
	 * @return int The number of users associated with the mask.
	 */
	public function getMemberCount() {
		return $this->memberCount;
	}
	
	/**
	 * Sets the member count.
	 *
	 * @param int The number of users associated with the mask.
	 */
	public function setMemberCount($int) {
		$this->memberCount = $int;
	}
	
	/**
	 * Gets whether or not this mask allows the editing of user accounts and permission masks.
	 *
	 * @return boolean TRUE if this allows editing of user accounts and permission masks or FALSE if not.
	 */
	public function canManageUsers() {
		if($this->manageUsers == false) {
			$this->manageUsers = 0;
		}
		return $this->manageUsers;
	}
	
	/**
	 * Gets whether or not this mask allows the editing of color and manufacturer records.
	 *
	 * @return boolean TRUE if this allows editing of color and manufacturer records or FALSE if not.
	 */
	public function canManageColors() {
		if($this->manageColors == false) {
			$this->manageColors = 0;
		}
		return $this->manageColors;
	}
	
	/**
	 * Gets whether or not this mask allows the editing of sealant records.
	 *
	 * @return boolean TRUE if this allows editing of sealant records or FALSE if not.
	 */
	public function canManageInventory() {
		if($this->manageInventory == false) {
			$this->manageInventory = 0;
		}
		return $this->manageInventory;
	}
	
	/**
	 * Gets whether or not this mask allows the editing of program options.
	 *
	 * @return boolean TRUE if this allows editing of program options or FALSE if not.
	 */
	public function canManageOptions() {
		if($this->manageOptions == false) {
			$this->manageOptions = 0;
		}
		return $this->manageOptions;
	}
	
	/**
	 * Gets whether or not this mask allows management of the database.
	 *
	 * @return boolean TRUE if this allows management of the database or FALSE if not.
	 */
	public function canManageDatabase() {
		if($this->manageDatabase == false) {
			$this->manageDatabase = 0;
		}
		return $this->manageDatabase;
	}
	
	/**
	 * Gets whether or not this mask allows the browsing of color records.
	 *
	 * @return boolean TRUE if this allows browsing of color records or FALSE if not.
	 */
	public function canBrowseColors() {
		if($this->browseColors == false) {
			$this->browseColors = 0;
		}
		return $this->browseColors;
	}
	
	/**
	 * Generates SQL code to update the permission mask.
	 *
	 * Generates the needed SQL code to update a permission mask with the assigned values.
	 *
	 * @return string The SQL code to send to the database.
	 */
	public function generateUpdateSQL() {
		$q = "UPDATE OSIPermissions SET MaskName = '".$this->getMaskName()."', ManageUsers = ".$this->canManageUsers().", ManageColors = ".$this->canManageColors().", ManageInventory = ".$this->canManageInventory().", ManageOptions = ".$this->canManageOptions().", ManageDatabase = ".$this->canManageDatabase().", BrowseColors = ".$this->canBrowseColors()." WHERE MaskID = ".$this->getMaskID();
		return $q;
	}
	
	/**
	 * Generates the SQL code to delete the permission mask.
	 * 
	 * Generates the needed SQL code to delete a permission mask based on the <code>maskID</code>.
	 *
	 * @return string The SQL code to send to the database.
	 */
	public function generateDeleteSQL() {
		$q = "DELETE FROM OSIPermissions WHERE MaskID = ".$this->getMaskID();
		return $q;
	}
	
	/**
	 * Dumps information about the permissions mask object.
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