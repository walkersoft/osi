<?php
/**
 * User Data Type.
 *
 * Class definition for a user object.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Datatypes
 */
 
class User
{
	/** 
	 * User ID that is assigned or was retrieved from the database.
	 *
	 * @var int
	 */
	private $userID;
	
	/**
	 * Username the user must login with.
	 *
	 * @var string
	 */
	private $username;
	
	/**
	 * The salted and hashed password to authenticate the user with.
	 *
	 * @var string
	 */
	private $fingerprint;
	
	/**
	 * The user's account status.
	 *
	 * @var boolean
	 */
	private $status;
	
	/**
	 * Permissions mask associated with the user.
	 *
	 * @var Permissions
	 */
	private $permissions;
	
	/**
	 * Constructor function.
	 *
	 * Creates a new user object with data provided.
	 *
	 * @return void
	 * @param mixed An array or object of user information to be read in.
	 * @param Permissions An instance of a Permissions object to associate with the user.
	 */
	public function __construct($data,$perms) {
		if(is_array($data)) {
			if($perms instanceof Permissions) {
				$this->setUserID($data[0]);
				$this->setUsername($data[1]);
				$this->setFingerprint($data[2]);
				$this->setStatus($data[3]);
				$this->setPermissions($perms);
			} else {
				print("Permissions mask is not in the correct format.");
			}
		} else if(is_object($data)) {
			if($perms instanceof Permissions) {
				$this->setUserID($data->UserID);
				$this->setUsername($data->Username);
				$this->setFingerprint($data->Fingerprint);
				$this->setStatus($data->Status);
				$this->setPermissions($perms);
			} else {
				print("User: Permissions mask is not in the correct format.");
			}
		} else {
			print("User: User information is not in the correct format.");
		}
	}
	
	/**
	 * Sets the user ID number.
	 *
	 * @return void
	 * @param int The user id from the database.
	 */
	public function setUserID($int) {
		$this->userID = $int;
	}
	
	/**
	 * Sets the username.
	 *
	 * @return void
	 * @param string The user's username, for logging into the system with.
	 */
	public function setUsername($str) {
		$this->username = $str;
	}
	
	/**
	 * Sets the user's fingerprint.
	 *
	 * The fingerprint is a salted and hashed version of the user's password.  Used for authenticating the user in future visits.
	 *
	 * @return void
	 * @param string The user's fingerprint stored in the database or a recently created fingerprint.
	 */
	public function setFingerprint($str) {
		$this->fingerprint = $str;
	}
	
	/**
	 * Sets the user's account status.
	 *
	 * @return void
	 * @param boolean The account status. TRUE for active, FALSE for disabled.
	 */
	public function setStatus($bool) {
		$this->status = (bool) $bool;
	}
	
	/**
	 * Sets the permissions object.
	 *
	 * @return void
	 * @param Permissions The permissions object to assign to the user.
	 */
	public function setPermissions($obj) {
		$this->permissions = $obj;
	}
	
	/**
	 * Returns the user ID.
	 *
	 * @return int The user's ID number from the database.
	 */
	public function getUserID() {
		return $this->userID;
	}
	
	/**
	 * Returns the username the user must login to the system with.
	 *
	 * @return string The username assigned to the user.
	 */
	public function getUsername() {
		return $this->username;
	}
	
	/**
	 * Returns the user's fingerprint.
	 *
	 * The fingerprint consists of two parts, the randomly generated salt, 
	 * and the hash that was produced with the user's password was encrypted 
	 * with the salt.
	 *
	 * @return string The concatenated salt and encrypted password hash.
	 */
	public function getFingerprint() {
		return $this->fingerprint;
	}
	
	/**
	 * Returns the user's account status.
	 *
	 * @return boolean The account status.  TRUE for active, FALSE for disabled.
	 */
	public function getStatus() {
		return $this->status;
	}
	
	/**
	 * Gets the user's permissions object.
	 *
	 * @return Permissions The permissions object associated to the user object.
	 */
	public function getPermissions() {
		return $this->permissions;
	}
	
	/**
	 * Creates a fingerprint of the user's password.
	 *
	 * Creates a 128-character hexadecimal values containing a concatinated salt and
	 * password hash.  A salt is created by generating a very larger integer value and 
	 * then hashing it.  The password itself is also hash seperately.  The two individual 
	 * values are then concatenated and hashed once more.  This newly created hash value 
	 * is concatenated to the original salt to create the fingerprint.  Once generated it 
	 * is sent to the <code>setFingerprint()</code> function.
	 *
	 * @return void
	 * @param string The password entered by the user.
	 */
	public function encryptPassword($password) {
		$salt = hash("SHA256",(microtime()*time()));
		$encrypted = hash("SHA256",$salt.hash("SHA256",$password));
		$this->setFingerprint($salt.$encrypted);
	}
	
	/**
	 * Challenges a user's password for authenticity.
	 *
	 * Takes the value from <code>getFingerprint()</code> and divides it into the salt 
	 * and the user's encrypted password. Takes the password given and hashes it once 
	 * then hashes it with the extracted salt to create a challenge.  The challenge and 
	 * the user's encrypted password are compared.  If the two match then the password 
	 * is considered a match.
	 *
	 * @return boolean Returns the results of the password check.  TRUE if the password matches, FALSE if it doesn't.
	 * @param string The password to be encrypted.
	 */	
	public function challengePassword($password) {
		$check = false;
		$salt = substr($this->getFingerprint(),0,64);
		$challenge = substr($this->getFingerprint(),64,64);
		$encrypted = hash("SHA256",$salt.hash("SHA256",$password));
		if(strcmp($challenge,$encrypted) == 0) {
			$check = true;
		}
		return $check;
	}
	
	/**
	 * Creates a new password fingerprint and accompanying SQL code to send to the database.
	 *
	 * Takes the new password given by the user and encrypts it using the <code>challengePassword()</code>
	 * function.  SQL code used to update the password is then generated.
	 *
	 * @return string The SQL code to send to the database to update the user's password.
	 * @param string The password to be encrypted.
	 */
	public function resetPassword($password) {
		$this->encryptPassword($password);
		$q = "UPDATE OSIUsers SET Fingerprint = '".$this->getFingerprint()."' WHERE UserID = ".$this->getUserID();
		return $q;
	}
	
	/**
	 * Generates SQL code to update the user account.
	 *
	 * Creates SQL code to update the user account based on their <code>userID</code> using the existing
	 * user information. The function does not create code to update the user's password.  The 
	 * <code>resetPassword()</code> function must be used to update a user account with a new password.
	 *
	 * @return string The SQL code to send to the database to update a user's account information.
	 */
	public function generateUpdateSQL() {
		$q = "UPDATE OSIUsers SET Status = '".$this->getStatus()."', MaskID = '".$this->getPermissions()->getMaskID()."' WHERE UserID = '".$this->getUserID()."'";
		return $q;
	}
	
	/**
	 * Generates SQL code to delete the user account.
	 *
	 * Creates SQL code to delete the user account based on the <code>userID</code>.
	 * @return string The SQL code to send to the database to delete the user account.
	 */
	public function generateDeleteSQL() {
		$q = "DELETE FROM OSIUsers WHERE UserID = ".$this->getUserID();
		return $q;
	}
	
	/**
	 * Creates HTML code to display the user's navigation menu.
	 *
	 * Generates the necessary HTML code to display the user menu based on the user's 
	 * permissions.
	 * 
	 * @return string The HTML code to send to the browser that will display a user's menu.
	 */
	public function generateUserNavigation() {
		$nav = "";
		$nav .= '<ul id="nav">';
		$menu = array();
		$links = array();
		if($this->getPermissions()->canBrowseColors()) {
			$links[0][] = array("category" => "Sealant Browser","href" => "?a=colorSearch","title" => "Search for Colors");
			$links[0][] = array("category" => "Sealant Browser","href" => "?a=advancedColorSearch","title" => "Advanced Search");
			$links[0][] = array("category" => "Sealant Browser","href" => "?a=browseManufacturerColors","title" => "Browse Colors by Manufacturer");
		}
		
		if($this->getPermissions()->canManageColors()) {
			$links[1][] = array("category" => "Catalog Administration","href" => "?a=newColor","title" => "Add New Color");
			$links[1][] = array("category" => "Catalog Administration","href" => "?a=browseManufacturers","title" => "Manage Manufacturers");
			$links[1][] = array("category" => "Catalog Administration","href" => "?a=colorSearch&amp;editMode","title" => "Manage Colors");
			$links[1][] = array("category" => "Catalog Administration","href" => "?a=uploadColors","title" => "Upload Colors File");
		}
		
		if($this->getPermissions()->canManageInventory()) {
			$links[1][] = array("category" => "Catalog Administration","href" => "?a=manageInventory","title" => "Manage Sealant Options");
		}
		
		if($this->getPermissions()->canManageUsers()) {
			$links[2][] = array("category" => "Account Administration","href" => "?a=browseUsers", "title" => "Manage User Accounts");
			$links[2][] = array("category" => "Account Administration","href" => "?a=browseMasks", "title" => "Manage Permission Masks");
		}
		
		if($this->getPermissions()->canManageDatabase()) {
			$links[3][] = array("category" => "Database Management","href" => "?a=backupDB","title" => "Database Backup Utility");
			$links[3][] = array("category" => "Database Management","href" => "?a=restoreDB","title" => "Import/Restore Database");
			$links[3][] = array("category" => "Database Management","href" => "?a=reconnectDB","title" => "Reconfigure Database Connection");			
		}
		
		$links[4][] = array("category" => "User Menu","href" => "?a=default","title" => "Main Page");
		$links[4][] = array("category" => "User Menu","href" => "?a=changePassword&amp;UserID=".$_SESSION['user']['attrib']->getUserID(),"title" => "Change Account Password");
		$links[4][] = array("category" => "User Menu","href" => "?a=changelog","title" => "OSI Version History");
		$links[4][] = array("category" => "User Menu","href" => "?a=logout","title" => "Logout");
		
		$headers = "";
		$changeCat = false;
		foreach($links as $link) {
			foreach($link as $l) {
				if(strpos($headers,$l['category']) === false) {
					$nav .= '<li><a href="#">'.$l['category'].'</a><ul>';
					$headers .= "|".$l['category'];
				}
				$nav .= '<li><a href="'.$_SERVER['PHP_SELF'].$l['href'].'">'.$l['title'].'</a></li>';
			}
			$nav .= '</ul></li>';
		}
		
		$nav .= '</ul>';
		return $nav;
	}
	
	/**
	 * Dumps information about the user object.
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