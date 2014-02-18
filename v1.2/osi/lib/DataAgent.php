<?php
/**
 * Data Agent Module.
 *
 * The data agent handles many functions related to the processing of data within files.
 * This module also takes care of file encryption related to backup archives.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Modules
 */

class DataAgent
{
	/**
	 * A listing to populate and manipulate sealant objects.
	 *
	 * @var array
	 */
	private $sealantList;
	
	/**
	 * Constructor function.
	 *
	 * Creates a new data agent object
	 *
	 * @return void
	 */
	public function __construct() {
		$this->sealantList = false;
	}
	
	/**
	 * Creates an array of <code>Sealant</code> objects from a file.
	 *
	 * Parses a .csv file will sealant information and then creates an array of 
	 * sealant objects with stocking flags set.
	 *
	 * @param string Name of the file to process including path.
	 * @return array Array of <code>Sealant</code> objects.
	 */
	public function createMasterSealantList($file) {		
		$local = false;
		$vendor = false;
		$sealants = array();
		if(file_exists($file)) {
			$csv = fopen($file,"r");
		}
		$ranonce = false;
		while($caulk = fgetcsv($csv)) {
			if(!$ranonce) {
				$ranonce = true;
				continue;
			}
			$sealants[$caulk[2]] = new Sealant(array($caulk[2],$local,$vendor));
		}
		rewind($csv);
		$ranonce = false;
		while($caulk = fgetcsv($csv)) {
			if(!$ranonce) {
				$ranonce = true;
				continue;
			}
			if($caulk[0] != "") {
				$sealants[$caulk[0]]->setLocalStock(true);
			}
			if($caulk[1] != "") {
				$sealants[$caulk[1]]->setVendorStock(true);
			}
		}
		return $sealants;
	}
	/**
	 * Import a .csv file with sealants and create an array with updated <code>Sealant</code> objects.
	 *
	 * Processes a .csv file with sealant information and compares it will the current list.  The two lists 
	 * are compared and three individual arrays are created.  One for sealants to insert, one for sealants to 
	 * update, and one for sealants to delete.
	 *
	 * @param array Uploaded file object from form input.
	 * @param array An array of the current set of sealant object.
	 * @return array Multi-dimensional array with three arrays.  One for inserts, one for updates, and one for deletes.
	 * @throws FileException
	 * @throws DataValidationException
	 */
	public function importMasterSealantList($file,$current) {
		//$file = the .csv file to import from
		//$current = array of Sealant objects already stored in the database, indexed with SealantID
		if(!file_exists($file['tmp_name'])) {
			throw new FileException(2,"File Not Found","The file $file does not exist.");
		} else {
			//Create some arrays to use
			$master = array();
			$delete = array();
			$update = array();
			$insert = array();
			$this->sealantList = array();
			$csvTest = "/^.*\.(csv|CSV)$/"; //regex to test for valid file type .csv or .CSV
			$osiTest = "/([0-9]{3})/";  //regex to test for valid sealant id
			$headerTest = "/(LOCAL|VENDOR|ALL)/";  //regex to test for valid row header title
			if(!preg_match($csvTest,$file['name'])) {
				throw new FileException(3,"Incorrect Filetype","Filetype is not correct, it must be a valid .CSV file.");
			} else {
				$array = file($file['tmp_name'],FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				//Explode all lines to seperate .CSV values
				//print_r($array);
				for($i = 0; $i < count($array); $i++) {
					$this->sealantList[] = explode(",",$array[$i]);
				}
				if(!(preg_match($headerTest,$this->sealantList[0][0]) && preg_match($headerTest,$this->sealantList[0][1]) && preg_match($headerTest,$this->sealantList[0][2]))) {
					throw new DataValidationException(3,"Bad File Structure","File headers are not correct, file is not valid. Please submit a valid .CSV file in the correct structure.");
				} else {
					//Shift off the header row and then create the master list of all sealants
					//and set vendor/local stocks to true if corresponding entries are found.
					array_shift($this->sealantList);
					foreach($this->sealantList as $s) {
						if(preg_match($osiTest,trim($s[2]))) {
							$master[$s[2]] = new Sealant(array($s[2],false,false));
						}
					}
					reset($this->sealantList);
					foreach($this->sealantList as $s) {
						if(preg_match($osiTest,trim($s[1]))) {
							$master[$s[1]]->setVendorStock(true);
						}
						if(preg_match($osiTest,trim($s[0]))) {
							$master[$s[0]]->setLocalStock(true);
						}
					}
					//Look for new sealants, entry will exist in $master, but not $current
					foreach($master as $m) {
						if(!isset($current[$m->getSealantID()])) {
							$insert[$m->getSealantID()] = $m;
						}
					}
					//Look for deleted sealants, entry will exist in $current, but not $master
					foreach($current as $c) {
						if(!isset($master[$c->getSealantID()])) {
							$delete[$c->getSealantID()] = $c;
						}
					}
					//Look for changed sealants, stock settings in $master are different from $current
					//Remove entries from $master that appear in $delete or $insert beforehand
					foreach($master as &$m) {
						if(isset($delete[$m->getSealantID()]) || isset($insert[$m->getSealantID()])) {
							$m = null;
						}
					}
					foreach($master as $m) {
						if($m != null) {
							if(isset($current[$m->getSealantID()])) {
								if($m->getVendorStock() !== $current[$m->getSealantID()]->getVendorStock() || $m->getLocalStock() !== $current[$m->getSealantID()]->getLocalStock()) {
									$update[] = $m;
								}
							}
						}
					}
				}
			}
		}
		return array("deletes" => $delete,"inserts" => $insert,"updates" => $update);
	}
	
	/**
	 * Takes three result sets from the database and creates the OSIMasterSealantList.csv file.
	 *
	 * Receives three individual result sets of sealant records, one for local stock, one for vendor
	 * stock, and one for manufacturer stock.  Then the three are used to create the OSIMasterSealantList.csv
	 * file for later downloading.
	 *
	 * @param MySQL_Result Result set of database sealant records that are locally stocked.
	 * @param MySQL_Result Result set of database sealant records that are stocked by a vendor.
	 * @param MySQL_Result Result set of database sealant records that are stocked by the manufacturer.
	 * @return void
	 */
	public function createMasterSealantFile($localStock,$vendorStock,$manufacturerStock) {
		$f = fopen(DATA."OSIMasterSealantList.csv","w");
		$csi = array();
		while($r = $localStock->fetch_object()) {
			$csi[] = $r->SealantID;
		}
		$vendor = array();
		while($r = $vendorStock->fetch_object()) {
			$vendor[] = $r->SealantID;
		}
		$osi = array();
		while($r = $manufacturerStock->fetch_object()) {
			$osi[] = $r->SealantID;
		}
		fwrite($f,"LOCAL,VENDOR,ALL".PHP_EOL);
		for($i = 0; $i < count($osi); $i++) {
			$master = array();
			if(isset($csi[$i]) && $csi[$i] != null) {
				$master[0] = $csi[$i];
			} else {
				$master[0] = null;
			}
			if(isset($vendor[$i]) && $vendor[$i] != null) {
				$master[1] = $vendor[$i];
			} else {
				$master[1] = null;
			}
			$master[2] = $osi[$i];
			$s = implode(",",$master);
			$s .= PHP_EOL;
			fwrite($f,$s);
		}
		fclose($f);
	}
	
	/**
	 * Processes a colors file and creates an array of objects.
	 *
	 * Takes a colors file and process it checking for valid structure and valid row entries.
	 * Bad rows are skipped, all other rows are put into an array.
	 *
	 * @param array Uploaded file object from form input.
	 * @return array An array of color rows that validated.
	 * @throws FileException
	 */
	public function processColorsFile($file) {
		$csvTest = "/^.*\.(csv|CSV)$/"; //regex to test for valid file type .csv or .CSV
		$osiTest = "/^\d{3}$/";  //regex to test for valid sealant id
		$lrvTest = "/^\d{1,2}$/"; //regex to test for valid lrv
		$headerTest = "/(MANUFACTURER|COLOR NAME|REFERENCE|MATCH|MUST COAT|LRV)/";  //regex to test for valid row header title
		$master = array();
		$colors = array();
		if(file_exists($file['tmp_name'])) {
			if(preg_match($csvTest,$file['name'])) {
				$array = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
				//$array = file($file['tmp_name'],FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				//Load $master array with information
				for($i = 0; $i < count($array); $i++) {
					$master[$i] = explode(",",$array[$i]);
				}
				$ranonce = false;
				foreach($master as $m) {
					$fail = false;
					if(!$ranonce) {
						if(preg_match($headerTest,$m[0]) && preg_match($headerTest,$m[1]) && preg_match($headerTest,$m[2]) && preg_match($headerTest,$m[3]) && preg_match($headerTest,$m[4]) && preg_match($headerTest,$m[5])) {
							$ranonce = true;
						} else {
							throw new FileException(3,"Invalid File Structure","Column headers are not correct, format invalid. Please submit a valid .CSV file in the correct structure.");
							$fail = true;
							break;
						}
						continue;
					}
					//Count fields, if less than six as empty elements
					while(count($m) < 6) {
						$m[count($m)] = "";
					}
					//Verify Data Fields
					//Check for manufacturer
					if($m[0] == null || $m[0] == "") {
						//print("Manufacturer not present.\n");
						$fail = true;
					}
					//Check for color name
					if($m[1] == null || $m[1] == "") {
						//print("Color name not present.\n");
						$fail = true;
					}
					//Check if match AND must coat are missing
					//then check if either (if present) is in bad format
					if($m[3] == null || $m[3] == "") {
						if($m[4] == null || $m[4] == "") {
							//print("No sealant information present.");
							$fail = true;
						} 
					} else if($m[3] != null || $m[3] != "") {
						if(!preg_match($osiTest,trim(trim($m[3]," ' \"\t\n\r\0\x0B\x93\x94")," ' \"\t\n\r\0\x0B\x93\x94"))) {
							if(!preg_match($osiTest,trim(trim($m[4]," ' \"\t\n\r\0\x0B\x93\x94")," ' \"\t\n\r\0\x0B\x93\x94"))) {
								//print("Bad id number for must coat.");
								$fail = true;
							}
						}
					} else if($m[4] != null || $m[4] != "") {						
						if(!preg_match($osiTest,$m[4])) {
							//print("Bad id number for must coat.");
							$fail = true;
						}
					}
					if($m[3] == null || $m[3] == "") $m[3] = "000";
					if($m[4] == null || $m[4] == "") $m[4] = "000";
					if($m[5] == null || $m[5] == "") $m[5] = "0";
					//Check LRV
					if(!preg_match($lrvTest,$m[5])) {
						//print("Bad LRV value.");
						$fail = true;
					}
					if(!$fail) {
						$colors[] = $m;
					}
				}
			} else {
				throw new FileException(3,"Invalid Filetype","Colors file must be in .csv format");
			}
		} else {
			throw new FileException(3,"Bad Upload", "Uploaded file was not found on server.");
		}
		return $colors;
	}
	
	/**
	 * Creates the database.ini connection file.
	 *
	 * Encrypts the database connection settings and then writes them to database.ini in the INI file format.
	 *
	 * @param string Database server hostname.
	 * @param string Database account username.
	 * @param string Database account password.
	 * @param string Database for the software to use.
	 * @param string Recovery password the will be required to make future changes.
	 */
	public function writeDatabaseConfig($host,$user,$pass,$db,$recovery) {
		$array = array("dbhost" => "$host","dbuser" => "$user","dbpass" => "$pass","dbused" => "$db");
		foreach($array as &$a) {
			$a = $this->encryptValue($a);
			$handle = fopen(DATA."database.ini","wb");
			fwrite($handle,"[DATABASE]".PHP_EOL);
			foreach($array as $k => $v) {
				fwrite($handle,"$k = $v".PHP_EOL);
			}
			fwrite($handle,"catalyst = ".$this->encryptRecoveryPassword($recovery).PHP_EOL);
			fclose($handle);
		}
	}
	
	/**
	 * Creates the backup.ini file to be included with a backup archive.
	 *
	 * @param string Directory where backup files are being stored.
	 * @throws FileException
	 */
	public function createBackupConfig($dir) {
		$f = fopen($dir.DIRECTORY_SEPARATOR."backup.ini","wb");
		if($f) {
			fwrite($f,"[SETTINGS]".PHP_EOL);
				$time = new DateTime();
				$time = $time->format("M j, Y g:iA");
				fwrite($f,"created = $time".PHP_EOL);
				fwrite($f,"createdby = ".$_SESSION['user']['attrib']->getUsername().PHP_EOL);
			if(isset($_SESSION['backup']['encrypted']) && $_SESSION['backup']['encrypted'] == true) {
				fwrite($f,"encrypted = true".PHP_EOL);
				$encrypted = $this->encryptRecoveryPassword($_SESSION['backup']['cryptkey']);
				fwrite($f,"catalyst = ".$encrypted.PHP_EOL);
				fwrite($f,"drip = ".bin2hex($_SESSION['backup']['iv']));
			} else {				
				fwrite($f,"encrypted = false".PHP_EOL);
			}
			fclose($f);
		} else {
			throw new FileException(3,"Config File","Couldn't create encryption settings file.");
		}
	}
	
	/**
	 * Packs backup files into an archive for storage.
	 *
	 * @param string Filename for the archive.
	 * @param string Directory where the backup files are located.
	 * @return boolean TRUE on creation of a sucessful archive, FALSE on error.
	 * @throws OSIException
	 */
	public function createBackupArchive($label,$dir) {
		$passed = false;
		$zip = new ZipArchive;
		$files = array("OSIPermissions.csv","OSIUsers.csv","OSISealants.csv","OSIManufacturers.csv","OSIColors.csv","OSISearchColors.csv","backup.ini");
		if(preg_match("/^\w+$/",$label)) {
			$label = BACKUPS.$label.".OSIBKF";
			$_SESSION['backup']['dl'] = $label;
			$zip->open($label,ZIPARCHIVE::CREATE);
			foreach($files as $f) {
				$d = $dir.DIRECTORY_SEPARATOR.$f;
				$zip->addFile($d,$f);
			}
			$zip->close();
			foreach($files as $f) {
				$f = $dir.DIRECTORY_SEPARATOR.$f;
				unlink($f);
			}
			$passed = true;
		} else {
			throw new OSIException(3,"Invalid backup label","Only alphanumeric characters and underscores are allowed.");
		}
		return $passed;
	}
	
	/*public function readBackupArchive($label) {
		$passed = false;
		$zip = new ZipArchive;
		$zip->open(BACKUPS.$label.".OSIBKF");
		$files = array("OSIPermissions.csv","OSIUsers.csv","OSISealants.csv","OSIManufacturers.csv","OSIColors.csv","OSISearchColors.csv");
		$zip->extractTo("bkf/");
		$zip->close();
		$enc = parse_ini_file("bkf/encrypt.ini");
		foreach($files as $f) {
			$decrypt = $this->decryptFile("bkf/".$f,"cedar1*",$enc['drip']);
			$d = fopen("bkf".DIRECTORY_SEPARATOR.$f,"wb");
			fwrite($d,$decrypt);
			fclose($d);
		}
	}*/
	
	/**
	 * Encrypts the entire contents of a file.
	 *
	 * @param string Name of the file, including path, to encrypt.
	 * @param string Key to supplement the encryption process with.
	 * @param string Initialation vector to supplement the encryption process with.
	 * @return string The encrypted text generated from the file contents.
	 */
	public function encryptFile($file,$key,$iv) {
		$salt = pack("H*",substr(hash("SHA1",$key),0,32));
		$crypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256,$salt,file_get_contents($file),"ctr",$iv);
		return $crypt;
	}
	
	/**
	 * Decrypts the entire contents of a file.
	 *
	 * @param string Name of the file, including path, to decrypt.
	 * @param string Key to supplement the decryption process with.
	 * @param string Initialation vector to supplement the decryption process with.
	 * @return string The decrypted text as it was when it was encrypted originally.
	 */
	public function decryptFile($file,$key,$iv) {
		$mod = mcrypt_module_open(MCRYPT_RIJNDAEL_256,'','ctr','');
		$salt = pack("H*",substr(hash("SHA1",$key),0,32));
		$decrypt = mcrypt_decrypt(MCRYPT_RIJNDAEL_256,$salt,file_get_contents($file),"ctr",pack("H*",$iv));
		return $decrypt;
	}
	
	/**
	 * Encrypts a peice of raw text.
	 *
	 * Encrypts raw data using a random salt and IV.  Then concatenates the salt, 
	 * iv, and data together.  The binary value is then converted to hexadecimal
	 * before being returned.
	 *
	 * @param string The data to be encrypted.
	 * @return string Hexadecimal value of the salt, iv, and encrypted text combined.
	 */
	public function encryptValue($encryptable) {
		$mod = mcrypt_module_open(MCRYPT_RIJNDAEL_256,'','ctr','');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($mod),MCRYPT_RAND);
		$salt = pack("H*",substr(hash("SHA1",$encryptable),0,32));
		$crypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256,$salt,$encryptable,"ctr",$iv);
		return bin2hex($salt.$iv.$crypt);
	}
	
	/**
	 * Decrypts a peice of encrypted text.
	 *
	 * Takes a hexadecimal values previously encrypted with <code>encryptValue()</code>
	 * and extracts the salt, iv, and encrypted data.  The data is decrypted to it's 
	 * original form.
	 *
	 * @param string Hexadecimal value to be decrypted.
	 * @return string Raw data before encryption.
	 */
	public function decryptValue($decryptable) {
		$mod = mcrypt_module_open(MCRYPT_RIJNDAEL_256,'','ctr','');
		$salt = pack("H*",substr($decryptable,0,32));
		$iv = pack("H*",substr($decryptable,32,64));
		$decrypt = pack("H*",substr($decryptable,96));
		return mcrypt_decrypt(MCRYPT_RIJNDAEL_256,$salt,$decrypt,"ctr",$iv);
	}
	
	/**
	 * Creates a hash of the database recovery password.
	 *
	 * @param string The recovery password to encrypt.
	 * @return string The hash value of password's fingerprint.
	 */
	public function encryptRecoveryPassword($password) {
		$salt = hash("SHA256",(microtime()*time()));
		$encrypted = hash("SHA256",$salt.hash("SHA256",$password));
		$fingerprint = $salt.$encrypted;
		return $fingerprint;
	}
	
	/**
	 * Challenges a recovery password hash to see if it is valid.
	 *
	 * @param string The hash value of the encrypted fingerprint.
	 * @param string The incoming password that is being challenged.
	 * @return boolean TRUE if the <code>password</code> matches <code>fingerprint</code>, or FALSE otherwise.
	 */
	public function challengeRecoveryPassword($fingerprint,$password) {
		$check = false;
		$salt = substr($fingerprint,0,64);
		$challenge = substr($fingerprint,64,64);
		$encrypted = hash("SHA256",$salt.hash("SHA256",$password));
		if(strcmp($challenge,$encrypted) == 0) {
			$check = true;
		}
		return $check;
	}
	
	/**
	 * Creates a backup file.
	 *
	 * Receives records from a database table and then creates a backup file from them.
	 *
	 * @param MySQL_Result A set of records queried from a MySQL table.
	 * @param string Name of the table that is being processed.
	 * @param string The path to the directory where the file is being created.
	 * @return void
	 * @throws FileException
	 * @throws DataValidationException
	 */
	public function createBackupFile($data,$table,$dir) {
		$h = array("OSIPermissions" => "MASK ID,MASK NAME,MANAGE USERS,MANAGE COLORS,MANAGE INVENTORY,MANAGE OPTIONS,MANAGE DATABASE,BROWSE COLORS", "OSIUsers" => "USER ID,USERNAME,FINGERPRINT,STATUS,MASK ID", "OSISealants" => "SEALANT ID,LOCAL STOCK,VENDOR STOCK", "OSIManufacturers" => "MANUFACTURER ID,MANUFACTURER NAME", "OSIColors" => "COLOR ID,MANUFACTURER ID,COLOR NAME,COLOR REFERENCE,OSI MATCH,OSI MUST COAT,LRV,LAST ACCESSED,LAST USER", "OSISearchColors" => "COLOR ID,COLOR NAME,COLOR REFERENCE");
		if(isset($h[$table])) {
			$fn = $table.".csv";
			$zero = "/^0.*$/";
			$f = fopen($dir.DIRECTORY_SEPARATOR.$fn,"wb");
			if($f) {
				fwrite($f,$h[$table].PHP_EOL);
				while($r = $data->fetch_object()) {
					$s = "";
					switch($table) {
						case "OSIPermissions":
							$s = array($r->MaskID,$r->MaskName,$r->ManageUsers,$r->ManageColors,$r->ManageInventory,$r->ManageOptions,$r->ManageDatabase,$r->BrowseColors);
							break;
						
						case "OSIUsers":
							$s = array($r->UserID,$r->Username,$r->Fingerprint,$r->Status,$r->MaskID);
							break;
						
						case "OSISealants":
							$s = array($r->SealantID,$r->LocalStock,$r->VendorStock);
							break;
						
						case "OSIManufacturers":
							$s = array($r->ManufacturerID,$r->Name);
							break;
							
						case "OSIColors":
							$s = array($r->ColorID,$r->ManufacturerID,$r->Name,$r->Reference,$r->OSIMatch,$r->OSIMustCoat,$r->LRV,$r->LastAccessed,$r->LastAccessedUser);
							break;
						
						case "OSISearchColors":
							$s = array($r->ColorID,$r->Name,$r->Reference);
							break;
					}
					if(is_array($s)) {
						foreach($s as &$q) {
							if(preg_match($zero,$q)) $q = "'".$q;
							$q = "\"".$q."\"";
						}
						$s = implode(",",$s);
						fwrite($f,$s.PHP_EOL);
					}
				}
				fclose($f);
			} else {
				throw new FileException(3,"File Error","Unable to create file.");
			}
		} else {
			throw new DataValidationException(3,"Bad Table Name","Bad table name specified, file was not created.");
		}
	}
	
	/**
	 * Validates a backup archive.
	 *
	 * Opens up a backup archive and checks for the presence of files that are expected to be there. 
	 * Also opens up the backup.ini file within the archive and checks it's structure.
	 *
	 * @param ZipArchive A previously created backup archive.
	 * @return boolean TRUE if the archive is valid or FALSE otherwise.
	 * @throws FileException
	 */
	public function validateBackupArchive($archive) {
		$valid = false;
		$bkTest = "/^.*\.(osibkf|OSIBKF)$/";
		if(file_exists(BACKUPS.$archive)) {
			if(preg_match($bkTest,BACKUPS.$archive)) {
				$zip = new ZipArchive;
				if($zip->open(BACKUPS.$archive) === true) {
					//Look for files
					if($zip->getFromName("OSIPermissions.csv") && $zip->getFromName("OSIUsers.csv") && $zip->getFromName("OSISealants.csv") && $zip->getFromName("OSIManufacturers.csv") && $zip->getFromName("OSIColors.csv") && $zip->getFromName("OSISearchColors.csv") && $zip->getFromName("backup.ini")) {
						$bkf = $zip->getFromName("backup.ini");
						$f = fopen("backup.ini","w+");
						fwrite($f,$bkf);
						fclose($f);
						$bkf = parse_ini_file("backup.ini");
						unlink("backup.ini");
						if(isset($bkf['created']) && isset($bkf['createdby']) && isset($bkf['encrypted'])) {
							if($bkf['encrypted'] == true || $bkf['encrypted'] == "true") {
								if(isset($bkf['catalyst']) && isset($bkf['drip'])) {
									$valid = true;
								}
							} else if($bkf['encrypted'] == false || $bkf['encrypted'] == "false") {
								$valid = true;
							}
						}
					}
				}
			} else {
				throw new FileException(3,"Invalid Filetype","Backup files are only of the .OSIBKF file type.");
			}
		}
		return $valid;
	}	
}
?>