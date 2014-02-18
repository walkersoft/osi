<?php
/**
 * Control Panel Module.
 *
 * The control panel manages various configuration files and makes their values
 * open to other portions of the software.  The modules also receive form input
 * from the web portal and can test the data within before sending the information
 * to the core for processing.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Modules
 */
 
class CPanel
{
	/**
	 * A string with the entire contents of the forms data file.
	 *
	 * @var string
	 */
	private $forms;
	
	/**
	 * A string with the entire contents of the views data file.
	 *
	 * @var string
	 */
	private $views;
	
	/**
	 * An array with configuration values from config.ini.
	 *
	 * @var array
	 */
	public $ini;
	
	/**
	 * An array with configuration values from database.ini.
	 *
	 * @var array
	 */
	public $db;
	
	/**
	 * Constructor function.
	 *
	 * Starts the control panel module and calls the <code>readFiles()</code> function.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->readFiles();
		$this->ini = array();
	}
	
	/**
	 * Reads the contents of forms and views data files.
	 *
	 * @return void
	 */
	public function readFiles() {
		$this->forms = file_get_contents(DATA."forms.dat.html");
		$this->views = file_get_contents(DATA."views.dat.html");
	}
	
	/**
	 * Parse the configuration and database INI files and creates arrays from their values.
	 *
	 * @return void
	 */
	public function readConfiguration() {
		$this->ini['cfg'] = parse_ini_file(DATA."config.ini");
		$this->ini['db'] = parse_ini_file(DATA."database.ini");
	}
	
	/**
	 * Extracts form code.
	 *
	 * Extracts the form HTML code based on it's label.
	 *
	 * @param string The form's tag label.
	 * @return string The HTML code for the form.
	 */
	public function parseForm($label) {
		return $this->parseSegment("Form:".$label,$this->forms);
	}
	
	/**
	 * Extracts view code.
	 *
	 * Extracts the view HTML code based on it's label.
	 *
	 * @param string The view's tag label.
	 * @return string The HTML code for the view.
	 */
	public function parseView($label) {
		return $this->parseSegment("Section:".$label,$this->views);
	}
	
	/**
	 * Extracts row code.
	 *
	 * Extracts the row HTML code based on it's label.
	 *
	 * @param string The row's tag label.
	 * @return string The HTML code for the row.
	 */
	public function parseRowEntry($label) {
		return $this->parseSegment("Row:".$label,$this->views);
	}
	
	/**
	 * Extracts HTML code.
	 *
	 * Extracts HTML code based on it's label and source.
	 *
	 * @param string The code's tag label.
	 * @param string The string of code to search through.
	 * @return string The HTML code based on the label.
	 */
	public function parseSegment($label,$source) {
		$segment = "";
		$start = strpos($source,"[$label]");
		$end = strpos($source,"[/$label]");
		$start += strlen("[$label]");
		if($start !== false && $end !== false) {
			$segment = substr($source,$start,$end - $start);
		} else {
			$segment = "$label - Segment Doesn't Exist";
		}
		return $segment;
	}
	
	/**
	 * Interprets form input from the web interface.
	 *
	 * Processes a form sent to the software by a user and performs basic data tests on it.  Throws exceptions whenever an error occurs.
	 *
	 * @param string The label of the form that is being processed.
	 * @throws BadFormInputException
	 * @throws DataValidationException
	 * @throws FileException
	 * @return array An array containing the form input.
	 */
	public function processFormInput($form) {
		$data = array(); //Set this variable to false during an error
		$form = "Form:".$form;
		switch($form) {
			
			case "Form:CreateUser":
				if(strcmp($_POST["pw1"],$_POST["pw2"]) == 0) {
					$data[0] = null;
					$data[1] = ($_POST["username"] != "" ? $_POST["username"] : false);
					$data[2] = ($_POST["pw1"] != "" ? $_POST["pw1"] : false);
					$data[3] = ($_POST["status"] == "active" ? true : false);
					$data[4] = $_POST["permissions"];
					if(!$data[1]) {
						throw new BadFormInputException(3,"","Username cannot be blank.");
						$data = false;
					}
					if(!preg_match("/^[\w ]{3,}$/",$data[1])) {
					throw new DataValidationException(3,"Bad Username","The username can only contain alphanumeric character, spaces, underscores, and must be three or more characters.");
						$data = false;
					}
					if(!$data[2]) {
						throw new BadFormInputException(3,"","The user account must have a password.");
						$data = false;
					}
				} else {
					throw new DataValidationException(3,"Non-Identical Data","The account password and confirmation do not match.");
					$data = false;
				}
				break;
				
			case "Form:EditUser":
				$data[0] = $_POST["userid"];
				$data[1] = ($_POST["status"] == "active" ? true : false);
				$data[2] = $_POST["permissions"];
				break;
			
			case "Form:CreateManufacturer":
				$data[0] = null;
				$data[1] = ($_POST["manufacturer"] != "" ? $_POST["manufacturer"] : false);
				if(!$data[1]) {
					throw new BadFormInputException(3,"","Manufacturer name cannot be blank.");
					$data = false;
				}
				break;
				
			case "Form:EditManufacturer":	
				$data[0] = $_POST["manufacturerid"];
				$data[1] = ($_POST["manufacturer"] != "" ? $_POST["manufacturer"] : false);
				if(!$data[1]) {
					throw new BadFormInputException(3,"","Manufacturer name cannot be blank");
					$data = false;
				}
				break;
			
			case "Form:CreatePermissionMask":
				$data[0] = ($_POST["maskName"] != "" ? $_POST["maskName"] : false);
				$data[1] = ($_POST["manageUsers"] == "allow" ? true : false);
				$data[2] = ($_POST["manageColors"] == "allow" ? true : false);
				$data[3] = ($_POST["manageInventory"] == "allow" ? true : false);
				$data[4] = ($_POST["manageOptions"] == "allow" ? true : false);
				$data[5] = ($_POST["manageDatabase"] == "allow" ? true : false);
				$data[6] = ($_POST["browseColors"] == "allow" ? true : false);
				if(!$data[0]) {
					throw new BadFormInputException(3,"","Mask name cannot be blank.");
					$data = false;
				}
				break;
			
			case "Form:EditPermissionMask":
				$data[0] = ($_POST["maskName"] != "" ? $_POST["maskName"] : false);
				$data[1] = ($_POST["manageUsers"] == "allow" ? true : false);
				$data[2] = ($_POST["manageColors"] == "allow" ? true : false);
				$data[3] = ($_POST["manageInventory"] == "allow" ? true : false);
				$data[4] = ($_POST["manageOptions"] == "allow" ? true : false);
				$data[5] = ($_POST["manageDatabase"] == "allow" ? true : false);
				$data[6] = ($_POST["browseColors"] == "allow" ? true : false);
				$data[7] = $_POST["maskid"];
				if(!$data[0]) {
					throw new BadFormInputException(3,"","Mask name cannot be blank.");
					$data = false;
				}
				break;
				
			case "Form:CreateColor":
				$data[0] = null;
				$data[6] = null;
				$data[7] = null;
				$data[1] = ($_POST["colorName"] != "" ? $_POST["colorName"] : false);
				$data[2] = $_POST["reference"];
				$data[3] = ($_POST["osiMatch"] != "0" ? $_POST["osiMatch"] : "000");
				$data[4] = ($_POST["osiMustCoat"] != "0" ? $_POST["osiMustCoat"] : "000");
				$data[5] = ($_POST["lrv"] != "" ? $_POST["lrv"] : "0");
				$data[8] = ($_POST["manufacturer"] != "0" ? $_POST["manufacturer"] : false);
				if(!$data[1]) {
					throw new BadFormInputException(3,"","You must specify a color name.");
					$data = false;
				}
				if(!$data[8]) {
					throw new BadFormInputException(3,"","You must specify a manufacturer.");
					$data = false;
				}
				break;
				
			case "Form:EditColor":
				$data[6] = null;
				$data[7] = null;
				$data[0] = $_POST["colorid"];
				$data[1] = ($_POST["colorName"] != "" ? $_POST["colorName"] : false);
				$data[2] = $_POST["reference"];
				$data[3] = ($_POST["osiMatch"] != "0" ? $_POST["osiMatch"] : "000");
				$data[4] = ($_POST["osiMustCoat"] != "0" ? $_POST["osiMustCoat"] : "000");
				$data[5] = ($_POST["lrv"] != "" ? $_POST["lrv"] : "0");
				$data[8] = ($_POST["manufacturer"] != "" ? $_POST["manufacturer"] : false);
				if(!$data[1]) {
					throw new BadFormInputException(3,"","Color name cannot be blank.");
					$data = false;
				}
				if(!$data[8]) {
					throw new BadFormInputException(3,"","You must specify a manufacturer.");
					$data = false;
				}
				break;
				
			case "Form:ManageInventory":
				if($_FILES["uploadInventory"]["error"] == UPLOAD_ERR_OK) {				
					$data[3] = $_FILES["uploadInventory"];
				} else {	
					$data[0] = ($_POST["csiStock"] != "" ? $_POST["csiStock"] : false);
					$data[1] = ($_POST["vendorStock"] != "" ? $_POST["vendorStock"] : false);
					$data[2] = ($_POST["osiStock"] != "" ? $_POST["osiStock"] : false);
					if(!$data[0]) {
						throw new BadFormInputException(3,"","CSI stock items are empty.");
						$data = false;
					}
					if(!$data[1]) {
						throw new BadFormInputException(3,"","Vendor stock items are empty.");
						$data = false;
					}
					if(!$data[2]) {
						throw new BadFormInputException(3,"","OSI stock items are empty.");
						$data = false;
					}
				}
				break;
				
			case "Form:UploadColors":
				if($_FILES["colorsFile"]["error"] == UPLOAD_ERR_OK) {
					$data[0] = $_FILES["colorsFile"];
				} else {	
					throw new FileException(3,"Bad Upload","No color file specified or the file was not properly uploaded to the server.");
					$data = false;
				}
				break;
				
			case "Form:ColorSearch":
				$data[0] = ($_POST["colorSearch"] != "" ? $_POST["colorSearch"] : false);
				if(!$data[0]) {
					//throw new BadFormInputException(3,"","");
					print("ERROR: Search criteria was not specified.");
					$data = false;
				}
				break;
				
			case "Form:AdvancedColorSearch":
				$data[0] = (strcmp(strtoupper($_POST["colorSearch"]),"ALL COLORS") == 0 ? "" : $_POST["colorSearch"]);
				$data[1] = $_POST["manufacturer"];
				$data[2] = $_POST["osiMatch"];
				$data[3] = $_POST["osiMustCoat"];
				$data[4] = $_POST["lrvCriteria"];
				$data[5] = $_POST["lrvTarget"];
				break;
				
			case "Form:InstallDatabase":
				if(strcmp($_POST["recoveryPW1"],$_POST["recoveryPW2"]) === 0) {
					$data[0] = ($_POST["dbHost"] != "" ? $_POST["dbHost"] : false);
					$data[1] = ($_POST["dbUser"] != "" ? $_POST["dbUser"] : false);
					$data[2] = $_POST["dbPass"];
					$data[3] = ($_POST["recoveryPW1"] != "" ? $_POST["recoveryPW1"] : false);
					$data[4] = $_POST["dbType"];
					$data[5] = $_POST["dbMode"];
					$data[6] = ($_POST["dbName"] != "" ? $_POST["dbName"] : false);
					if(!$data[0]) {
						throw new BadFormInputException(3,"","You must specify a database hostname.");
						$data = false;
					}
					if(!$data[1]) {
						throw new BadFormInputException(3,"","You must specify a username for the database host.");
						$data = false;
					}
					if(!$data[3]) {
						throw new BadFormInputException(3,"","You must specify a recovery password.");
						$data = false;
					}
					if(!$data[6]) {
						throw new BadFormInputException(3,"","You must specify the name of the database to use.");
						$data = false;
					}
				} else {
					throw new DataValidationException(3,"Non-Identical Data","The recovery password and confirmation do not match.");
					$data = false;
				}
				break;
				
			case "Form:ReconnectDatabase":
				if(strcmp($_POST["newRecoveryPW1"],$_POST["newRecoveryPW2"]) === 0) {
					$data[0] = ($_POST["dbHost"] != "" ? $_POST["dbHost"] : false);
					$data[1] = ($_POST["dbUser"] != "" ? $_POST["dbUser"] : false);
					$data[2] = $_POST["dbPass"];
					$data[4] = ($_POST["newRecoveryPW1"] != "" ? $_POST["newRecoveryPW1"] : false);
					$data[3] = ($_POST["recoveryPW"] != "" ? $_POST["recoveryPW"] : false);
					$data[5] = $_POST["dbMode"];
					$data[6] = ($_POST["dbName"] != "" ? $_POST["dbName"] : false);
					if(!$data[0]) {
						throw new BadFormInputException(3,"","You must specify a database hostname.");
						$data = false;
					}
					if(!$data[1]) {
						throw new BadFormInputException(3,"","You must specify a username for the database host.");
						$data = false;
					}
					if(!$data[3]) {
						throw new BadFormInputException(3,"","You must enter your recovery password to reconfigure the database connection information.");
						$data = false;
					}
					if(!$data[4]) {
						throw new BadFormInputException(3,"","You must specify a recovery password.");
						$data = false;
					}
					if(!$data[6]) {
						throw new BadFormInputException(3,"","You must specify the name of the database to use.");
						$data = false;
					}
				} else {
						throw new DataValidationException(3,"Non-Identical Data","Your recovery password and confirmation do not match.");
					$data = false;
				}
				break;
				
			case "Form:BackupDatabase":
				$data[0] = ($_POST["backupLabel"] != "" ? $_POST["backupLabel"] : false);
				$data[1] = ($_POST["encrypted"] == "Y" ? true : false);
				$data[2] = $_POST["encryptKey"];
				if(!$data[0]) {
					throw new BadFormInputException(3,"","You must specify a backup label to give the backup archive.");
					$data = false;
				}
				if(!preg_match("/^[\w]{1,}$/",$data[0])) {
					throw new DataValidationException(3,"Bad Label","The file label can only consist of alphanumeric characters and underscores.");
				}
				break;
				
			case "Form:RecoverDatabase":
				$data[0] = ($_POST["backupFile"] != "" ? $_POST["backupFile"] : false);
				$data[1] = $_POST["decryptKey"];
				$data[2] = ($_POST["recoveryPW"] != "" ? $_POST["recoveryPW"] : false);
				if(!$data[0]) {
					throw new BadFormInputException(3,"","No backup file was specified.");
					$data = false;
				}
				if(!$data[2]) {
					throw new BadFormInputException(3,"","You must enter the database recovery password to initiate the recovery process.");
					$data = false;
				}
				break;
				
			case "Form:ImportDatabase":
				if($_FILES["backupFile"]["error"] == UPLOAD_ERR_OK) {
					$data[0] = $_FILES["backupFile"];
				}
				$data[1] = $_POST["decryptKey"];
				if($_FILES["backupFile"]["error"] != UPLOAD_ERR_OK) {
					throw new FileException(3,"Bad Upload","No backup file specified or the file was not properly uploaded to the server.");
					$data = false;
				}
				break;
				
			case "Form:AdminAccountSetup":
				if(strcmp($_POST["pw1"],$_POST["pw2"]) === 0) {
					$data[0] = ($_POST["username"] != "" ? $_POST["username"] : false);
					$data[1] = ($_POST["pw1"] != "" ? $_POST["pw1"] : false);
					if(!$data[0]) {
						throw new BadFormInputException(3,"","Username cannot be blank.");
						$data = false;
					}
					if(!$data[1]) {
						throw new BadFormInputException(3,"","Password cannot be blank.");
						$data = false;
					}
				} else {
						throw new DataValidationException(3,"Non-Identical Data","Password and confirmation do not match.");
					$data = false;
				}
				break;
			
			case "Form:UserLogin":
				$data[0] = $_POST["loginID"];
				$data[1] = $_POST["loginPass"];
				if(!preg_match("/^[\w ]{3,}$/",$data[0])) {
					throw new DataValidationException(3,"Bad Username","Username can only contain alphanumeric characters, spaces, underscores, and must be three or more characters.");
					$data = false;
				}
				break;
				
			case "Form:ResetPassword":
				if(strcmp($_POST["pw1"],$_POST["pw2"]) === 0) {
					$data[0] = $_POST["userid"];
					$data[1] = ($_POST["pw1"] != "" ? $_POST["pw1"] : false);
					if(!$data[1]) {
						throw new BadFormInputException(3,"","Password cannot be blank.");
						$data = false;
					}
				} else {
					throw new DataValidationException(3,"Non-Identical Data","Your password and confirmation do not match.");
					$data = false;
				}
				break;
				
			default:
				print("ERROR: Invalid form specified for processing");
				break;
		}
		return $data;
	}
	
}

?>