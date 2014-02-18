<?php
/**
 * Database Module.
 *
 * The database modules manages the connection between the MySQLi API and the MySQL
 * database server.  It provides functions to send queries to the database and process
 * batches of objects to insert, delete, and update multiple records.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Modules
 */
 
class Database
{
	/**
	 * A mysqli database resource object.
	 *
	 * @var resource
	 */
	private $dbi;
	
	/**
	 * A mysqli_stmt prepared statement object
	 *
	 * @var resource
	 */
	private $stmt;
	
	/**
	 * A mysqli_stmt prepared statement object
	 *
	 * @var resource
	 */
	private $stmt1;
	
	/**
	 * Constructor function.
	 * 
	 * Creates a mysqli connection object and assigns it to <code>dbi</code>
	 */
	public function __construct($host,$user,$pass) {
		$this->dbi = new mysqli($host,$user,$pass);
	}
	
	/**
	 * Returns the database connection object.
	 *
	 * @return Resouce The database resource object.
	 */
	public function getDBI() {
		return $this->dbi;
	}
	
	/**
	 * Selects a database on the MySQL server to use.
	 *
	 * @param string Name of the database to use.
	 * @return boolean TRUE if the database was selected, FALSE otherwise.
	 */
	public function setDatabase($db) {
		$connected = false;
		if($this->dbi->select_db($db)) {
			$connected = true;
		}
		return $connected;
	}
	
	/**
	 * Get the last MySQL error number and message as a string.
	 *
	 * @return string The error number and message as a string.
	 */
	public function getError() {
		if($this->dbi->errno != 0) {
			return "MySQL says: ".$this->dbi->errno." - ".$this->dbi->error;
		}
	}
	
	/**
	 * Prepares statements and inserts data type objects into the database.
	 *
	 * @param string The type of data type that is being processed.
	 * @param array Array of objects that are of the <code>type</code> data type.
	 */
	public function executeObjects($type,$objects) {
		switch($type) {
			case "permission":
				$this->stmt = $this->dbi->prepare("INSERT INTO OSIPermissions (MaskName, ManageUsers, ManageColors, ManageInventory, ManageOptions, ManageDatabase, BrowseColors) VALUES(?,?,?,?,?,?,?)");
				$this->stmt->bind_param("siiiiii",$name,$users,$colors,$inventory,$options,$database,$browse);
				break;
			case "user":
				$this->stmt = $this->dbi->prepare("INSERT INTO OSIUsers (Username, Fingerprint, Status, MaskID) VALUES(?,?,?,?)");
				$this->stmt->bind_param("ssii",$username,$fp,$status,$mask);
				break;
			case "sealant":
				$this->stmt = $this->dbi->prepare("INSERT INTO OSISealants (SealantID, LocalStock, VendorStock) VALUES(?,?,?)");
				$this->stmt->bind_param("sii",$id,$local,$vendor);
				break;
			case "manufacturer":
				$this->stmt = $this->dbi->prepare("INSERT INTO OSIManufacturers (Name) VALUES (?)");
				$this->stmt->bind_param("s",$name);
				break;
			case "color":
				$this->stmt = $this->dbi->prepare("INSERT INTO OSIColors (Name, ManufacturerID, Reference, OSIMatch, OSIMustCoat, LRV, LastAccessedUser) VALUES(?,?,?,?,?,?,?)");
				$this->stmt->bind_param("sisssii",$name,$manufacturer,$reference,$match,$mustcoat,$lrv,$lastuser);
				$this->stmt1 = $this->dbi->prepare("INSERT INTO OSISearchColors (ColorID,Name, Reference) VALUES(?,?,?)");
				$this->stmt1->bind_param("iss",$id,$name,$reference);
				break;
			default:
				echo "Specified invalid record type for preparation.";
				break;
			
		}
		foreach($objects as $o) {
			switch($type) {
				case "permission":
					$name = $o->getMaskName();
					$users = $o->canManageUsers();
					$colors = $o->canManageColors();
					$inventory = $o->canManageInventory();
					$options = $o->canManageOptions();
					$database = $o->canManageDatabase();
					$browse = $o->canBrowseColors();
					if(!($this->stmt->execute())) {
					}
					break;
					
				case "user":
					$username = $o->getUsername();
					$fp = $o->getFingerprint();
					$status = $o->getStatus();
					$mask = $o->getPermissions()->getMaskID();
					if(!($this->stmt->execute())) {
					}
					break;
					
				case "sealant":
					$id = $o->getSealantID();
					$local = $o->getLocalStock();
					$vendor = $o->getVendorStock();
					if(!($this->stmt->execute())) {
					}
					break;
					
				case "manufacturer":
					$name = $o->getManufacturerName();
					if(!($this->stmt->execute())) {
					}
					break;
					
				case "color":
					$name = $o->getName();
					$manufacturer = $o->getManufacturer()->getManufacturerID();
					$reference = $o->getReference();
					$match = $o->getOSIMatch()->getSealantID();
					$mustcoat = $o->getOSIMustCoat()->getSealantID();
					$lrv = $o->getLRV();
					$lastuser = $o->getLastAccessedUser();
					if(!($this->stmt->execute())) {
					}
					$id = $this->dbi->insert_id;
					if(!($this->stmt1->execute())) {
					}
					break;
					
				default:
					echo "Specified invalid record type for execution.";
					break;
			}
		}
		$this->stmt->close();
		if(isset($this->stmt1)) $this->stmt1->close();
	}
	
	/**
	 * Sorts an array of objects before sending them to <code>executeObjects()</code>.
	 *
	 * Takes an array with mixed data type objects and sorts them down into arrays of 
	 * objects that are the same data type.  Each array is then sent to the <code>executeObjects()</code> function.
	 *
	 * @param array An array of data type objects.
	 */
	public function insertRecords($objects) {
		if(is_array($objects)) {
			$permissions = array();
			$users = array();
			$sealants = array();
			$manufacturers = array();
			$colors = array();
			foreach($objects as $o) {
				if($o instanceof Permissions) $permissions[] = $o;
				if($o instanceof User) $users[] = $o;
				if($o instanceof Sealant) $sealants[] = $o;
				if($o instanceof Manufacturer) $manufacturers[] = $o;
				if($o instanceof Color) $colors[] = $o;
			}
			$set = array("permission" => $permissions, "user" => $users, "sealant" => $sealants, "manufacturer" => $manufacturers, "color" => $colors);
			//var_dump($set);
			foreach($set as $key => $subset) {
				if(count($subset) > 0) {
					$this->executeObjects($key,$subset);
				}
			}
		} else {
			print("ERROR: insertRecords() only accepts array input.");
		}
	}
	
	/**
	 * Takes arrays of color data type objects to run DELETE, INSERT, and UPDATE clauses on.
	 *
	 * Takes three different arrays of color objects and processes each.  One array are colors to
	 * delete from the database, another is colors that need to be inserted into the database, and 
	 * the third are colors that are targeted to be updated.
	 *
	 * @param array Array of color objects to delete.
	 * @param array Array of color objects to insert.
	 * @param array Array of color objects to update.
	 */
	public function updateColors($delete,$insert,$update) {
		//Delete records
		if(count($delete) > 0) {
			$stmt = $this->dbi->prepare("DELETE FROM OSIColors WHERE ColorID = ?");
			$stmt->bind_param("i",$id);
			$stmt1 = $this->dbi->prepare("DELETE FROM OSISearchColors WHERE ColorID = ?");
			$stmt1->bind_param("i",$id);
			foreach($delete as $d) {
				$id = $d->getColorID();
				$stmt->execute();
				$stmt1->execute();
			}
			$stmt->close();
			$stmt1->close();
		}		
		
		//Insert records
		$this->insertRecords($insert);
		
		//Update records
		if(count($update) > 0) {
			$stmt = $this->dbi->prepare("UPDATE OSIColors SET Name = ?, Reference = ?, OSIMatch = ?, OSIMustCoat = ?, LRV = ?, ManufacturerID = ?, LastAccessedUser = ? WHERE ColorID = ?");
			$stmt->bind_param("ssssiiii",$name,$reference,$match,$mustcoat,$lrv,$manufacturerid,$lastuser,$colorid);
			$stmt1 = $this->dbi->prepare("UPDATE OSISearchColors SET Name = ?, Reference = ? WHERE ColorID = ?");
			$stmt1->bind_param("ssi",$name,$reference,$colorid);
			foreach($update as $u) {
				$name = $u->getName();
				$reference = $u->getReference();
				$match = $u->getOSIMatch()->getSealantID();
				$mustcoat = $u->getOSIMustCoat()->getSealantID();
				$lrv = $u->getLRV();
				$manufacturerid = $u->getManufacturer()->getManufacturerID();
				$lastuser = $u->getLastAccessedUser();
				$colorid = $u->getColorID();
				$stmt->execute();
				$stmt1->execute();
			}
			$stmt->close();
			$stmt1->close();
		}
	}
	
	/**
	 * Takes arrays of sealant data type objects to run DELETE, INSERT, and UPDATE clauses on.
	 *
	 * Takes three different arrays of sealant objects and processes each.  One array are sealants to
	 * delete from the database, another is sealants that need to be inserted into the database, and 
	 * the third are sealants that are targeted to be updated.
	 *
	 * @param array Array of sealant objects to delete.
	 * @param array Array of sealant objects to insert.
	 * @param array Array of sealant objects to update.
	 */
	public function updateSealants($delete,$insert,$update) {
		//Delete records
		$stmt = $this->dbi->prepare("DELETE FROM OSISealants WHERE SealantID = ?");
		$stmt->bind_param("s",$id);
		foreach($delete as $d) {
			$id = $d->getSealantID();
			$stmt->execute();
		}
		$stmt->close();
		
		//Insert records (too easy)
		$this->insertRecords($insert);
		
		//Update records
		$stmt = $this->dbi->prepare("UPDATE OSISealants SET LocalStock = ?, VendorStock = ? WHERE SealantID = ?");
		$stmt->bind_param("iis",$local,$vendor,$id);
		foreach($update as $u) {
			$local = $u->getLocalStock();
			$vendor = $u->getVendorStock();
			$id = $u->getSealantID();
			$stmt->execute();
		}
		$stmt->close();
	}
	
	/**
	 * Executes a MySQL query.
	 *
	 * @param string The SQL statement to send to the server.
	 * @return MySQL_Result A result set (if applicable) that is returned from the MySQL server.
	 */
	public function query($statement) {
		$result = $this->dbi->query($statement);
		return $result;
	}
	
	/**
	 * Installs a blank database on the MySQL server.
	 *
	 * @param boolean TRUE if a database already exists, FALSE if it doesn't exist.
	 * @param string Name of the database to use.  Only used if <code>dbExists</code> is FALSE.
	 * @return boolean TRUE if database is ready to use, FALSE if it is not.
	 */
	public function installDatabase($dbExists = true,$dbName = "") {
		$ready = false;
		//Drop existing database (if present) and install over with new database
		if(!$dbExists) { // Drop the whole database (or at least attempt to)
			if($dbName != "") {
				$this->query("DROP DATABASE IF EXISTS $dbName");
				$this->query("CREATE DATABASE $dbName");
				$this->query("USE $dbname");
				if($this->dbi->errno == 0) {
					$ready = true;
				}
			}
		} else { // DB exists, so just drop tables instead
			$this->query("DROP TABLE IF EXISTS OSISearchColors");
			$this->query("DROP TABLE IF EXISTS OSIColors");
			$this->query("DROP TABLE IF EXISTS OSISealants");
			$this->query("DROP TABLE IF EXISTS OSIManufacturers");
			$this->query("DROP TABLE IF EXISTS OSIUsers");
			$this->query("DROP TABLE IF EXISTS OSIPermissions");
			if($this->dbi->errno == 0) {
				$ready = true;
			}
		}
		
		if($ready) {
			$this->query("CREATE TABLE OSIUsers (
				UserID SMALLINT(4) NOT NULL AUTO_INCREMENT,
				Username VARCHAR(60) NOT NULL UNIQUE,
				Fingerprint CHAR(128) NOT NULL,
				Status BIT(1) NOT NULL,
				MaskID TINYINT(2) NOT NULL,
				PRIMARY KEY (UserID)
			) ENGINE=InnoDB");
			
			$this->query("CREATE TABLE OSIPermissions (
				MaskID TINYINT(2) NOT NULL AUTO_INCREMENT,
				MaskName VARCHAR(30) NOT NULL UNIQUE,
				ManageUsers BIT(1) NOT NULL,
				ManageColors BIT(1) NOT NULL,
				ManageInventory BIT(1) NOT NULL,
				ManageOptions BIT(1) NOT NULL,
				ManageDatabase BIT(1) NOT NULL,
				BrowseColors BIT(1) NOT NULL,
				PRIMARY KEY (MaskID)
			) ENGINE=InnoDB");
			
			$this->query("CREATE TABLE OSISealants
			(
				SealantID CHAR(4) NOT NULL UNIQUE,
				LocalStock BIT(1) NOT NULL,
				VendorStock BIT(1) NOT NULL,
				PRIMARY KEY (SealantID)
			) ENGINE=InnoDB");

			$this->query("CREATE TABLE OSIManufacturers (
				ManufacturerID SMALLINT(4) NOT NULL AUTO_INCREMENT,
				Name VARCHAR(60) NOT NULL UNIQUE,
				PRIMARY KEY (ManufacturerID)
			) ENGINE=InnoDB");

			$this->query("CREATE TABLE OSIColors (
				ColorID INT(11) NOT NULL AUTO_INCREMENT,
				ManufacturerID SMALLINT(4) NOT NULL,
				Name VARCHAR(60) NOT NULL,
				Reference VARCHAR(20) NOT NULL,
				OSIMatch CHAR(4) NOT NULL,
				OSIMustCoat CHAR(4) NOT NULL,
				LRV TINYINT(2) NOT NULL,
				LastAccessed TIMESTAMP NOT NULL,
				LastAccessedUser SMALLINT(4) NOT NULL,
				PRIMARY KEY (ColorID)
			) ENGINE=InnoDB");

			$this->query("CREATE TABLE OSISearchColors (
				ColorID INT(11) NOT NULL AUTO_INCREMENT,
				Name VARCHAR(60) NOT NULL,
				Reference VARCHAR(20) NOT NULL,
				PRIMARY KEY (ColorID)
			) ENGINE=MyISAM");
		}

		$this->query("ALTER TABLE OSIUsers ADD CONSTRAINT PermissionsMaskFK FOREIGN KEY (MaskID) REFERENCES OSIPermissions (MaskID)");
		$this->query("ALTER TABLE OSIColors ADD CONSTRAINT ManufacturersFK FOREIGN KEY (ManufacturerID) REFERENCES OSIManufacturers (ManufacturerID)");
		$this->query("ALTER TABLE OSIColors ADD CONSTRAINT MatchFK FOREIGN KEY (OSIMatch) REFERENCES OSISealants (SealantID)");
		$this->query("ALTER TABLE OSIColors ADD CONSTRAINT MustCoatFK FOREIGN KEY (OSIMustCoat) REFERENCES OSISealants (SealantID)");
		$this->query("ALTER TABLE OSIColors ADD CONSTRAINT LastAccessUsersFK FOREIGN KEY (LastAccessedUser) REFERENCES OSIUsers (UserID)");
		$this->query("ALTER TABLE OSISearchColors ADD FULLTEXT(Name)");
		$this->query("ALTER TABLE OSISearchColors ADD FULLTEXT(Reference)");
		$this->query("ALTER TABLE OSISearchColors ADD FULLTEXT(Name,Reference)");
		
		return $ready;
	}
	
	/**
	 * Imports database records from backup files and then deletes the files once complete.
	 *
	 * @param string Directory location of the backup files to process.
	 */
	public function importDatabase($location) {
		//Looks in $location for .csv backup files and imports each one to the database
		//Objects are not created, statements are prepared and raw data is trimmed and imported
		if($this->installDatabase()) {
			set_time_limit(300);
			$files = array("OSIPermissions","OSIUsers","OSISealants","OSIManufacturers","OSIColors","OSISearchColors");
			foreach($files as $file) {
				switch($file) {
					case "OSIPermissions":
						$this->stmt = $this->dbi->prepare("INSERT INTO OSIPermissions (MaskID, MaskName, ManageUsers, ManageColors, ManageInventory, ManageOptions, ManageDatabase, BrowseColors) VALUES(?,?,?,?,?,?,?,?)");
						$this->stmt->bind_param("isiiiiii",$id,$name,$users,$colors,$inventory,$options,$database,$browse);
						break;
					case "OSIUsers":
						$this->stmt = $this->dbi->prepare("INSERT INTO OSIUsers (UserID, Username, Fingerprint, Status, MaskID) VALUES(?,?,?,?,?)");
						$this->stmt->bind_param("issii",$id,$username,$fp,$status,$mask);
						break;
					case "OSISealants":
						$this->stmt = $this->dbi->prepare("INSERT INTO OSISealants (SealantID, LocalStock, VendorStock) VALUES(?,?,?)");
						$this->stmt->bind_param("sii",$id,$local,$vendor);
						break;
					case "OSIManufacturers":
						$this->stmt = $this->dbi->prepare("INSERT INTO OSIManufacturers (ManufacturerID, Name) VALUES (?,?)");
						$this->stmt->bind_param("is",$id,$name);
						break;
					case "OSIColors":
						$this->stmt = $this->dbi->prepare("INSERT INTO OSIColors (ColorID, ManufacturerID, Name, Reference, OSIMatch, OSIMustCoat, LRV, LastAccessedUser) VALUES(?,?,?,?,?,?,?,?)");
						$this->stmt->bind_param("iissssii",$id,$manufacturer,$name,$reference,$match,$mustcoat,$lrv,$lastuser);
						break;
						
					case "OSISearchColors";
						$this->stmt = $this->dbi->prepare("INSERT INTO OSISearchColors (ColorID, Name, Reference) VALUES(?,?,?)");
						$this->stmt->bind_param("iss",$id,$name,$reference);
						break;
				}
				$f = fopen($location.$file.".csv","r");
				$ranonce = false;
				while($row = fgetcsv($f)) {
					if(!$ranonce) {
						$ranonce = true;
						continue;
					}
					foreach($row as &$r) {
						$r = trim(trim($r," ' \"\t\n\r\0\x0B\x93\x94")," ' \"\t\n\r\0\x0B\x93\x94");
					}
					switch($file) {
						case "OSIPermissions":
							$id = $row[0];
							$name = $row[1];
							$users = $row[2];
							$colors = $row[3];
							$inventory = $row[4];
							$options = $row[5];
							$database = $row[6];
							$browse = $row[7];
							$this->stmt->execute();
							break;
							
						case "OSIUsers":
							$id = $row[0];
							$username = $row[1];
							$fp = $row[2];
							$status = $row[3];
							$mask = $row[4];
							$this->stmt->execute();
							break;
							
						case "OSISealants":
							$id = $row[0];
							$local = $row[1];
							$vendor = $row[2];
							$this->stmt->execute();
							break;
							
						case "OSIManufacturers":
							$id = $row[0];
							$name = $row[1];
							$this->stmt->execute();
							break;
							
						case "OSIColors":
							$id = $row[0];
							$manufacturer = $row[1];
							$name = $row[2];
							$reference = $row[3];
							$match = $row[4];
							$mustcoat = $row[5];
							$lrv = $row[6];
							$lastuser = $row[8];
							$this->stmt->execute();
							break;
							
						case "OSISearchColors":
							$id = $row[0];
							$name = $row[1];
							$reference = $row[2];
							$this->stmt->execute();
							break;
					}
				}
				$this->stmt->close();
				fclose($f);
				if(file_exists($location.$file.".csv")) unlink($location.$file.".csv");
				if(file_exists($location."backup.ini")) unlink($location."backup.ini");
			}
		}
	}
}

?>
