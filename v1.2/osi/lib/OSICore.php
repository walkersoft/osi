<?php
/**
 * OSI Core Module.
 *
 * This module performs all the programs functions and makes use of the programs
 * API library to get it's tasks done.  It is the most extensive (and confusing)
 * of all the modules.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Modules
 */

class OSICore
{
	/**
	 * Database module.
	 *
	 * @var Database
	 */
	private $dbi;

	/**
	 * Control Panel module.
	 *
	 * @var CPanel
	 */
	private $cp;

	/**
	 * Data Agent module.
	 *
	 * @var DataAgent
	 */
	private $data;

	/**
	 * Viewer Agent module.
	 *
	 * @var ViewAgent
	 */
	private $viewer;

	/**
	 * Multi-dimensional array with valid program actions.
	 *
	 * @var array
	 */
	private $actions;

	/**
	 * Constructor function.
	 *
	 * Creates a new <code>OSICore</code>.
	 */
	public function __construct() {
		$this->cp = new CPanel();
		$this->data = new DataAgent();
		$this->viewer = new ViewAgent();
		$this->setActions();
	}

	/**
	 * Instantiates the database module and starts a connection to the database.
	 *
	 * @return boolean TRUE if database is connect, FALSE otherwise.
	 * @throws OSIException
	 */
	public function connectDBI() {
		$connected = false;
		$this->cp->readConfiguration();
		$conn = new Database($this->data->decryptValue($this->cp->ini['db']['dbhost']),$this->data->decryptValue($this->cp->ini['db']['dbuser']),$this->data->decryptValue($this->cp->ini['db']['dbpass']));
		if($conn) {
			$this->dbi = $conn;
			if($this->setDatabase($this->data->decryptValue($this->cp->ini['db']['dbused']))) {
				$connected = true;
			} else {
				throw new OSIException(1,"Database Not Found","A connection to the database was established, but the database specified could not be found on the server.");
			}
		} else {
			throw new OSIException(1,"Database Connection Failure","A connection to the database could not be established.");
			$connected = false;
		}
		return $connected;
	}

	/**
	 * Tests a connection to the database using the database module.
	 *
	 * @param string Hostname of the MySQL server.
	 * @param string Login account username.
	 * @param string Login account password.
	 * @param string Database to select and use.
	 * @return boolean TRUE if test was successful, FALSE otherwise.
	 * @throws DatabaseException
	 */
	public function testDBI($host,$user,$pass,$db) {
		$connected = false;
		$test = new Database($host,$user,$pass);
		if(!$test->getDBI()->connect_error) {
			if($test->setDatabase($db)) {
				$connected = true;
			} else {
				throw new DatabaseException(2,"Database Not Found","A connection to the server was established but was unable to connect to the database '$db'.");
			}
		} else {
			throw new DatabaseException(2,"Connection Failure","Unable to establish a connection to the database server.");
		}
		return $connected;
	}

	/**
	 * Selects a database to use.
	 *
	 * @param string Database to use.
	 * @return boolean TRUE if database was selected, FALSE otherwise.
	 */
	public function setDatabase($db) {
		return $this->dbi->setDatabase($db);
	}

	/**
	 * Get database connection object.
	 *
	 * @return Resource The MySQL connection object.
	 */
	public function getDatabase() {
		return $this->dbi->getDBI();
	}

	/**
	 * Updates the sealant inventory table from a file.
	 *
	 * @param string Path to the .csv file with sealant information.
	 */
	public function setSealants($file) {
		$this->dbi->insertRecords($this->data->createMasterSealantList($file));
	}

	/**
	 * Sends an array of data type objects to the database module to be inserted.
	 *
	 * @param array Array of object of any data type.
	 */
	public function insertRecords($array) {
		$this->dbi->insertRecords($array);
	}

	/**
	 * Sends an SQL query to the database module.
	 *
	 * @param string The SQL statement to send.
	 * @return MySQL_Result A result set (if applicable) from the server.
	 */
	public function query($stmt) {
		$result = $this->dbi->query($stmt);
		return $result;
	}

	/**
	 * Creates HTML code to show a user that an action was successful.
	 *
	 * @param string Message to display to the user.
	 * @param string Title for the message box.
	 * @return string HTML code to send to the browser.
	 */
	public function createSuccessMessage($msg,$title = "Success!") {
		$bgc = "#33cc00";
		$m = '<div style="border: solid 2px black; width: 500px; margin-left: auto; margin-right: auto;"><div style="background: '.$bgc.';"><span style="font-size: 16px; font-weight: bold;">'.$title.'</span></div><div style="background:#eeeeee;">'.$msg.'</div></div><br />';
		return $m;
	}

	/**
	 * Creates HTML selection of manufacturers.
	 *
	 * @param string The type of selection to create.
	 * @param boolean Specify if changing the selection fires javascript code.
	 * @param int The id number of a manufacturer to preselect in the selection.
	 * @return string The HTML code to send to the browser.
	 */
	public function generateManufacturerList($type,$jsSubmit = false,$preselect = false) {
		$list = $this->dbi->query("SELECT * FROM OSIManufacturers ORDER BY Name");
		if($jsSubmit) {
			$output = '<select name="manufacturer" onchange="document.forms[\'manufacturerSelector\'].submit()">';
		} else {
			$output = '<select name="manufacturer">';
		}
		if($type == "createColor") {
			$output .= '<option value="0">-- Manufacturer --</option>';
		} else if($type == "searchColor") {
			$output .= '<option value="0">-- All Manufacturers --</option>';
		}
		while($o = $list->fetch_object()) {
			if((int) $preselect == (int) $o->ManufacturerID) {
				$output .= '<option value="'.$o->ManufacturerID.'" selected="selected">'.$o->Name.'</option>';
			} else {
				$output .= '<option value="'.$o->ManufacturerID.'">'.$o->Name.'</option>';
			}
		}
		$output .= '<input type="hidden" name="lastManufacturer" value="0" />';
		$output .= '</select>';
		return $output;
	}

	/**
	 * Creates HTML selection of sealants.
	 *
	 * @param string The type of selection to create.
	 * @param string The name of the selection to create.
	 * @param int The id number of a sealant to preselect in the selection.
	 * @return string The HTML code to send to the browser.
	 */
	public function generateSealantList($listType,$matchType,$preselect = false) {
		$list = $this->dbi->query("SELECT SealantID FROM OSISealants ORDER BY SealantID ASC");
		$output = "";
		if($matchType == "match") {
			$output = '<select name="osiMatch">';
		} else if($matchType == "mustCoat") {
			$output = '<select name="osiMustCoat">';
		}
		if($listType == "colorCreate") {
			$output .= '<option value="0">-- Sealant --</option>';
		} else if($listType == "colorSearch") {
			$output .= '<option value="0">-- Any Sealant --</option>';
		}
		while($o = $list->fetch_object()) {
			if($preselect) {
				if((int) $preselect == (int) $o->SealantID) {
					$output .= '<option value="'.$o->SealantID.'" selected="selected">'.$o->SealantID.'</option>';
				} else {
				$output .= '<option value="'.$o->SealantID.'">'.$o->SealantID.'</option>';
				}
			} else {
				$output .= '<option value="'.$o->SealantID.'">'.$o->SealantID.'</option>';
			}
		}
		$output .= '</select>';
		return $output;
	}

	/**
	 * Creates HTML selection of permission masks.
	 *
	 * @return string HTML code to send to the browser.
	 */
	public function generatePermissionsList() {
		$list = $this->dbi->query("SELECT MaskID,MaskName FROM OSIPermissions ORDER BY MaskID ASC");
		$output = '<select name="permissions">';
		while($o = $list->fetch_object()) {
			$output .= '<option value="'.$o->MaskID.'">'.$o->MaskName.'</option>';
		}
		$output .= '</select>';
		return $output;
	}

	/**
	 * Selects all sealants from the database.
	 *
	 * @return array Array of every sealant records stored in the database as Sealant objects.
	 */
	public function getAllSealants() {
		$sealants = array();
		$result = $this->dbi->query("SELECT * FROM OSISealants ORDER BY SealantID");
		while($r = $result->fetch_object()) {
			$sealants[$r->SealantID] = new Sealant($r);
		}
		return $sealants;
	}

	/**
	 * Selects all manufacturers from the database.
	 *
	 * @param string Sorting order of manufacturer id numbers.
	 * @return array Array of every manufacturer records stored in the database as Manufacturer objects.
	 */
	 public function getAllManufacturers($direction = "asc") {
		$manufacturers = array();
		$q = "SELECT * FROM OSIManufacturers ORDER BY ManufacturerID";
		if($direction == "asc") {
			$q .= " ASC";
		} else {
			$q .= " DESC";
		}
		$result = $this->dbi->query($q);
		while($r = $result->fetch_object()) {
			$manufacturers[$r->ManufacturerID] = new Manufacturer($r);
		}
		return $manufacturers;
	}

	/**
	 * Creates arrays of actions and assigns them to the <code>actions</code> array.
	 *
	 * @return void
	 */
	public function setActions() {
		$this->actions['install'] = "install|adminAccount|newDatabase|importDB";
		$this->actions['user'] = "newUser|editUser|createUser|deleteUser|updateUser|browseUsers";
		$this->actions['manufacturer'] = "newManufacturer|editManufacturer|createManufacturer|deleteManufacturer|updateManufacturer|browseManufacturers";
		$this->actions['permissions'] = "newMask|editMask|createMask|deleteMask|updateMask|browseMasks";
		$this->actions['sealants'] = "manageInventory|updateInventory|importInventory|restoreInventory";
		$this->actions['colors'] = "newColor|createColor|editColor|updateColor|exportColors|editColors|deleteColor|importColors|sortColors|uploadColors";
		$this->actions['colorView'] = "advancedSearch|advancedColorSearch|colorSearch|browseManufacturerColors|viewColors";
		$this->actions['database'] = "configureDatabase|installDatabase|connectDatabase|recoverDatabase|reconnectDB|restoreDB|backupDB|populateDB";
		$this->actions['unchecked'] = "resetPassword|changePassword|login|authenticate|logout|exportInventory|purgeTempFiles|changelog|default";
	}

	/**
	 * Reads an action and determines a path of execution to send the software into.
	 *
	 * @param string The action to execute.)
	 */
	public function doAction($action) {
		if(strstr($this->actions['user'],$action)) {
			$this->doUserAction($action);
		} else if(strstr($this->actions['install'],$action)) {
			$this->doInstallAction($action);
		} else if(strstr($this->actions['manufacturer'],$action)) {
			$this->doManufacturerAction($action);
		} else if(strstr($this->actions['permissions'],$action)) {
			$this->doPermissionsAction($action);
		} else if(strstr($this->actions['sealants'],$action)) {
			$this->doSealantAction($action);
		} else if(strstr($this->actions['colors'],$action)) {
			$this->doColorAction($action);
		} else if(strstr($this->actions['colorView'],$action)) {
			$this->doColorViewAction($action);
		} else if(strstr($this->actions['database'],$action)) {
			$this->doDatabaseAction($action);
		} else if(strstr($this->actions['unchecked'],$action)) {
			$this->doLimitedCheckAction($action);
		}
	}

	/**
	 * Performs an installation action.
	 *
	 * @param string A valid installation action.
	 * @throws OSIException
	 * @throws DataValidationException
	 * @throws BadFormInputException
	 * @throws DatabaseException
	 */
	public function doInstallAction($action) {
		switch($action) {
			case "adminAccount":
				if(isset($_SESSION['installer'])) {
					$form = $this->cp->parseForm("AdminAccountSetup");
					$form = str_replace("[FormAction]",$_SERVER['PHP_SELF']."?a=install",$form);
					print($form);
					$_SESSION['installer']['stage'] = 4;
				} else {
					throw new OSIException(2,"Incorrect Execution Mode","This action can only be performed during installation.");
				}
				break;

			case "install":
				if(!isset($_SESSION['installer'])) {
					if(file_exists(DATA."install.lock.php")) {
						throw new OSIException(2,"Lock Detected","The installation routine has already been run and locked. Installation cannot be run again.");
					} else {
						$_SESSION['installer']['stage'] = 0;
						$this->doAction("install");
						break;
					}
				} else {
					if($_SESSION['installer']['stage'] == 0) {
						print('<div style="width: 700px; margin-left: auto; margin-right: auto;"><p>Setup will now install and configure the OSI Color Reference Manager database. This process will require some information from you during installation.  Please have your MySQL information handy.  This installer will perform the following: </p>
						<ul style="list-style: disc";>
						<li>Collect your MySQL database connection information.</li>
						<li>Create an administrator account.</li>
						<li>Install a fresh database or import a database of your choosing.</li>
						</ul>
						<p><a href="'.$_SERVER['PHP_SELF'].'?a=install">Click here to continue</a></p></div>');
						$_SESSION['installer']['stage'] = 1;
						break;
					}
					if($_SESSION['installer']['stage'] == 1) {
						$this->doAction("newDatabase");
						break;
					}
					if($_SESSION['installer']['stage'] == 2) {
						try {
							$info = $this->cp->processFormInput("InstallDatabase");
						} catch (BadFormInputException $e) {
							print($e);
						} catch (DataValidationException $e) {
							print($e);
						}
						$db = false;
						$connected = false;
						if(isset($info) && $info) {
							try {
								$db = new mysqli($info[0],$info[1],$info[2]);
								if($db->connect_error) {
									throw new DatabaseException(1,"Connection Failure","Failed to establish a database connection with the settings provided. Double check your connection settings and try again.");
								} else {
									try {
										if(!$db->select_db($info[6])) {
											throw new DatabaseException(1,"Non-Existant Database","A connection to the database server was established, but the database <span style=\"font-weight: bold;\">".$info[6]."</span> could not be found.  Double check your connection settings and try again.");
										}
									} catch (DatabaseException $e) {
										print($e);
										$_SESSION['installer']['stage'] = 1;
										$this->doAction("install");
										break;
									}
								}
							} catch (DatabaseException $e) {
								print($e);
								$_SESSION['installer']['stage'] = 1;
								$this->doAction("install");
								break;
							}

							$this->data->writeDatabaseConfig($info[0],$info[1],$info[2],$info[6],$info[3]);
							if($info[4] == "import") {
								$_SESSION['installer']['import']['stage'] = 0;
								print($this->createSuccessMessage("Successfully connected to the database.  Settings have been encrypted and stored in <span style=\"font-weight: bold;\">".DATA."database.ini<span>"));
								$this->doAction("importDB");
								break;
							} else {
								$_SESSION['installer']['stage'] = 3;
								print($this->createSuccessMessage("Successfully connected to the database.  Settings have been encrypted and stored in <span style=\"font-weight: bold;\">".DATA."database.ini<span>"));
								$this->doAction("install");
								break;
							}
							break;
						} else {
							$_SESSION['installer']['stage'] = 1;
							$this->doAction("install");
							break;
						}
					}
					if($_SESSION['installer']['stage'] == 3) {
						$this->doAction("adminAccount");
						break;
					}
					if($_SESSION['installer']['stage'] == 4) {
						try {
							$info = $this->cp->processFormInput("AdminAccountSetup");
						} catch (BadFormInputException $e) {
							print($e);
							$_SESSION['installer']['stage'] = 3;
							$this->doAction("install");
							break;
						} catch (DataValidationException $e) {
							print($e);
							$_SESSION['installer']['stage'] = 3;
							$this->doAction("install");
							break;
						}
						if(isset($info) && $info) {
							$_SESSION['installer']['admin'] = array();
							$_SESSION['installer']['admin'][0] = new Permissions(array("System Administrators",1,1,1,1,1,1));
							$_SESSION['installer']['admin'][0]->setMaskID(1);
							$_SESSION['installer']['admin'][1] = new User(array(null,$info[0],$info[1],true),$_SESSION['installer']['admin'][0]);
							$_SESSION['installer']['admin'][1]->encryptPassword($info[1]);
							$_SESSION['installer']['stage'] = 5;
							$this->doAction("install");
							break;
						} else {
							$_SESSION['installer']['stage'] = 3;
							$_SESSION['installer']['statusMsg'] = "Unable to create admin account.  Please try again.";
							$this->doAction("install");
							break;
						}
					}
					if($_SESSION['installer']['stage'] == 5) {
						if($this->connectDBI()) {
							$this->dbi->installDatabase(true,false);
							//var_dump($_SESSION['installer']['admin']);
							$this->dbi->insertRecords($_SESSION['installer']['admin']);
							$_SESSION['installer']['stage'] = 6;
							$this->doAction("install");
						} else {
							$_SESSION['installer']['stage'] = 3;
							$_SESSION['installer']['statusMsg'] = "Unable to create admin account.  Please try again.";
						}
						break;
					}
					if($_SESSION['installer']['stage'] == 6) {
						$fh = fopen(DATA."install.lock.php","w");
						fclose($fh);
						unset($_SESSION['installer']);
						print($this->createSuccessMessage("Installation has completed successfully.  Your database is setup and ready for use.  To prevent this installer from being run unintentionally a file called 'install.lock.php' has been created in the directory this scripts was executed in.  You will need to delete this file to run the installer in the future.  Please <a href=\"".$_SERVER['PHP_SELF']."?a=login\">login</a> with your new account to access program features.","Installation Complete"));
					}
				}
				break;

			case "importDB":
				if(!isset($_SESSION['installer']['import'])) {
					throw new OSIException(2,"Incorrect Execution Mode","This feature can only be accessed during installation.");
					break;
				} else {
					if($_SESSION['installer']['import']['stage'] == 0) {
						$form = $this->cp->parseForm("ImportDatabase");
						$form = str_replace("[FormAction]",$_SERVER['PHP_SELF']."?a=importDB",$form);
						print($form);
						$_SESSION['installer']['import']['stage'] = 1;
						break;
					}
					if($_SESSION['installer']['import']['stage'] == 1) {
						try {
							$info = $this->cp->processFormInput("ImportDatabase");
						} catch (BadFormInputException $e) {
							print($e);
							$_SESSION['installer']['import']['stage'] = 0;
							$this->doAction("importDB");
							break;
						} catch (DataValidationException $e) {
							print($e);
							$_SESSION['installer']['import']['stage'] = 0;
							$this->doAction("importDB");
							break;
						} catch (FileException $e) {
							print($e);
							$_SESSION['installer']['import']['stage'] = 0;
							$this->doAction("importDB");
							break;
						}
						if(isset($info) && $info) {
							move_uploaded_file($info[0]['tmp_name'],BACKUPS.$info[0]['name']);
							if($this->data->validateBackupArchive($info[0]['name'])) {
								$zip = new ZipArchive;
								$zip->open(BACKUPS.$info[0]['name']);
								$zip->extractTo(TMPDIR);
								$zip->close();
								$_SESSION['installer']['import']['cryptkey'] = $info[1];
								$_SESSION['installer']['import']['stage'] = 2;
								$this->doAction("importDB");
								break;
							} else {
								throw new DataValidationException(3,"Invalid Archive","Backup archive cannot be validated successful.");
								$_SESSION['installer']['import']['stage'] = 0;
								$this->doAction("importDB");
								break;
							}
							break;
						} else {
							$_SESSION['installer']['import']['stage'] = 0;
							$this->doAction("importDB");
							break;
						}
						break;
					}
					if($_SESSION['installer']['import']['stage'] == 2) {
						$ini = parse_ini_file(TMPDIR."backup.ini");
						if($ini['encrypted'] == true || $ini['encrypted'] == "true") {
							if($this->data->challengeRecoveryPassword($ini['catalyst'],$_SESSION['installer']['import']['cryptkey'])) {
								$files = array("OSIPermissions.csv","OSIUsers.csv","OSISealants.csv","OSIManufacturers.csv","OSIColors.csv","OSISearchColors.csv");
								foreach($files as $f) {
									$decrypted = $this->data->decryptFile(TMPDIR.$f,$_SESSION['installer']['import']['cryptkey'],$ini['drip']);
									$d = fopen(TMPDIR.$f,"wb");
									fwrite($d,$decrypted);
									fclose($d);
								}
								$_SESSION['installer']['import']['stage'] = 3;
								$this->doAction("importDB");
								break;
							} else {
								try {
									throw new DataValidationException(3,"Decryption Error","The decryption key specified is incorrect.");
								} catch (DataValidationException $e) {
									print($e);
									$_SESSION['installer']['import']['stage'] = 0;
									$this->doAction("importDB");
								}
								break;
							}
						} else {
							$_SESSION['installer']['import']['stage'] = 3;
							$this->doAction("importDB");
							break;
						}
					}
					if($_SESSION['installer']['import']['stage'] == 3) {
						$this->dbi->importDatabase(TMPDIR);
						$this->doAction("exportInventory");
						$fh = fopen(DATA."install.lock.php","w");
						fclose($fh);
						unset($_SESSION['installer']);
						$this->doAction("exportInventory");
						print($this->createSuccessMessage("Installation has completed successfully.  Your database is setup and ready for use.  To prevent this installer from being run unintentionally a file called 'install.lock.php' has been created in the directory this scripts was executed in.  You will need to delete this file to run the installer in the future.  Please <a href=\"".$_SERVER['PHP_SELF']."?a=login\">login</a> with your previously existing account to access program features.","Installation Complete"));
					}
					break;
				}
				break;

			case "newDatabase":
				$form = $this->cp->parseForm("InstallDatabase");
				$form = str_replace("[FormAction]",$_SERVER['PHP_SELF']."?a=install",$form);
				print($form);
				$_SESSION['installer']['stage'] = 2;
				break;
		}
	}

	/**
	 * Performs a database action.
	 *
	 * @param string A valid database action.
	 * @throws OSIException
	 * @throws DataValidationException
	 * @throws PermissionsException
	 * @throws BadFormInputException
	 * @throws FileException
	 */
	public function doDatabaseAction($action) {
		if(isset($_SESSION['user']['attrib']) && $_SESSION['user']['attrib']->getPermissions()->canManageDatabase()) {
			switch($action) {
				case "reconnectDatabase":
					$form = $this->cp->parseForm("ReconnectDatabase");
					$form = str_replace("[FormAction]",$_SERVER['PHP_SELF']."?a=reconnectDB",$form);
					print($form);
					$_SESSION['reconnect']['stage'] = 2;
					break;

				case "configureDatabase":
					try {
						$info = $this->cp->processFormInput("InstallDatabase");
					} catch (BadFormInputException $e) {
						print($e);
					} catch (DataValidationException $e) {
						print($e);
					}
					if(isset($info) && $info) {
						if($info[5] == "new") {
							$this->data->writeDatabaseConfig($info[0],$info[1],$info[2],$info[6],$info[3]);
							//$this->doAction("installDatabase");
						} else if ($info[5] == "existing") {
						}
					}
					break;

				case "reconnectDB":
					if(!isset($_SESSION['reconnect'])) {
						$_SESSION['reconnect']['stage'] = 0;
						$this->doAction("reconnectDB");
						break;
					} else {
						if($_SESSION['reconnect']['stage'] == 0) {
							if(!file_exists(DATA."database.ini")) {
								throw new FileException(1,"","Database connection file not found.  A data connection cannot be established.  This may have occurred if the installer did not complete properly.");
								unset($_SESSION['reconnect']);
								$this->doAction("default");
								break;
							} else {
								$this->cp->readConfiguration();
								$_SESSION['reconnect']['catalyst'] = $this->cp->ini['db']['catalyst'];
								$_SESSION['reconnect']['stage'] = 1;
								$this->doAction("reconnectDB");
								break;
							}
						}
						if($_SESSION['reconnect']['stage'] == 1) {
							$form = $this->cp->parseForm("ReconnectDatabase");
							$form = str_replace("[FormAction]",$_SERVER['PHP_SELF']."?a=reconnectDB&amp;submitted",$form);
							print($form);
							$_SESSION['reconnect']['stage'] = 2;
							break;
						}
						if($_SESSION['reconnect']['stage'] == 2) {
							if(isset($_GET['submitted'])) {
								try {
									$info = $this->cp->processFormInput("ReconnectDatabase");
								} catch (BadFormInputException $e) {
									print($e);
									$_SESSION['reconnect']['stage'] = 1;
									$this->doAction("reconnectDB");
									break;
								} catch (DataValidationException $e) {
									print($e);
									$_SESSION['reconnect']['stage'] = 1;
									$this->doAction("reconnectDB");
									break;
								}
								if(isset($info) && $info) {
									if($this->data->challengeRecoveryPassword($_SESSION['reconnect']['catalyst'],$info[3])) {
										try {
											if($this->testDBI($info[0],$info[1],$info[2],$info[6])) {
												$this->data->writeDatabaseConfig($info[0],$info[1],$info[2],$info[6],$info[4]);
												print($this->createSuccessMessage("Database connection file created successfully."));
												unset($_SESSION['reconnect']);
												$this->doAction("default");
											} else {
												try {
													throw new DatabaseException(3,"Connection Error","Unable to connect to the database, please check your connection settings.");
												} catch (DatabaseException $e) {
													print($e);
													$_SESSION['reconnect']['stage'] = 1;
													$this->doAction("reconnectDB");
													break;
												}
											}
										} catch (DatabaseException $e) {
											print($e);
											$_SESSION['reconnect']['stage'] = 1;
											$this->doAction("reconnectDB");
											break;
										}
									} else {
										try {
											throw new DataValidationException(3,"Recovery Error","The recovery password is incorrect, please try again.");
										} catch (DataValidationException $e) {
											$_SESSION['reconnect']['stage'] = 1;
											$this->doAction("reconnectDB");
											break;
										}
									}
								} else {
									try {
										throw new BadFormInputException(3,"Form Error","The settings form was not properly filled out.");
									} catch (BadFormInputException $e) {
										$_SESSION['reconnect']['stage'] = 1;
										$this->doAction("reconnectDB");
										break;
									}
								}
							} else {
								$_SESSION['reconnect']['stage'] = 1;
								$this->doAction("reconnectDB");
								break;
							}
						}
					}
					break;

				case "backupDB":
					if(!isset($_SESSION['backup'])) {
						$_SESSION['backup']['stage'] = 0;
						$this->doAction("backupDB");
						break;
					} else {
						$files = array("OSIPermissions.csv","OSIUsers.csv","OSISealants.csv","OSIManufacturers.csv","OSIColors.csv","OSISearchColors.csv");
						if($_SESSION['backup']['stage'] == 0) {
							$form = $this->cp->parseForm("BackupDatabase");
							$form = str_replace("[FormAction]",$_SERVER['PHP_SELF']."?a=backupDB&amp;submitted",$form);
							print($form);
							$_SESSION['backup']['stage'] = 1;
							break;
						}

						if($_SESSION['backup']['stage'] == 1) {
							if(isset($_GET['submitted'])) {
								try {
									$info = $this->cp->processFormInput("BackupDatabase");
								} catch (FileException $e) {
									print($e);
									$_SESSION['backup']['stage'] = 0;
									$this->doAction("backupDB");
									break;
								} catch (DataValidationException $e) {
									print($e);
									$_SESSION['backup']['stage'] = 0;
									$this->doAction("backupDB");
									break;
								} catch (BadFormInputException $e) {
									print($e);
									$_SESSION['backup']['stage'] = 0;
									$this->doAction("backupDB");
									break;
								} catch (OSIException $e) {
									print($e);
									$_SESSION['backup']['stage'] = 0;
									$this->doAction("backupDB");
									break;
								}
								if(isset($info) && $info) {
									$_SESSION['backup']['tmpdir'] = TMPDIR . substr(hash("SHA1",microtime()),0,16);
									mkdir($_SESSION['backup']['tmpdir']);
									$_SESSION['backup']['label'] = $info[0];
									$_SESSION['backup']['encrypted'] = $info[1];
									$_SESSION['backup']['cryptkey'] = $info[2];
									$this->data->createBackupFile($this->dbi->query("SELECT * FROM OSIPermissions ORDER BY MaskID ASC"),"OSIPermissions",$_SESSION['backup']['tmpdir']);
									$this->data->createBackupFile($this->dbi->query("SELECT * FROM OSIUsers ORDER BY UserID ASC"),"OSIUsers",$_SESSION['backup']['tmpdir']);
									$this->data->createBackupFile($this->dbi->query("SELECT * FROM OSISealants ORDER BY SealantID ASC"),"OSISealants",$_SESSION['backup']['tmpdir']);
									$this->data->createBackupFile($this->dbi->query("SELECT * FROM OSIManufacturers ORDER BY ManufacturerID ASC"),"OSIManufacturers",$_SESSION['backup']['tmpdir']);
									$this->data->createBackupFile($this->dbi->query("SELECT * FROM OSIColors ORDER BY ColorID ASC"),"OSIColors",$_SESSION['backup']['tmpdir']);
									$this->data->createBackupFile($this->dbi->query("SELECT * FROM OSISearchColors ORDER BY ColorID ASC"),"OSISearchColors",$_SESSION['backup']['tmpdir']);
									if($_SESSION['backup']['encrypted']) {
										$_SESSION['backup']['stage'] = 2;
									} else {
										$_SESSION['backup']['stage'] = 3;
									}
									$this->doAction("backupDB");
									break;
								} else {
									$_SESSION['backup']['stage'] = 0;
									$this->doAction("backupDB");
									break;
								}
							} else {
								$_SESSION['backup']['stage'] = 0;
								$this->doAction("backupDB");
								break;
							}
						}

						if($_SESSION['backup']['stage'] == 2) {
							$mod = mcrypt_module_open(MCRYPT_RIJNDAEL_256,'','ctr','');
							$_SESSION['backup']['iv'] = mcrypt_create_iv(mcrypt_enc_get_iv_size($mod),MCRYPT_RAND);
							foreach($files as $f) {
								$crypt = $this->data->encryptFile($_SESSION['backup']['tmpdir'].DIRECTORY_SEPARATOR.$f,$_SESSION['backup']['cryptkey'],$_SESSION['backup']['iv']);
								$c = fopen($_SESSION['backup']['tmpdir'].DIRECTORY_SEPARATOR.$f,"wb");
								fwrite($c,$crypt);
								fclose($c);
							}
							$_SESSION['backup']['stage'] = 3;
							$this->doAction("backupDB");
							break;
						}
						if($_SESSION['backup']['stage'] == 3) {
							try {
								$this->data->createBackupConfig($_SESSION['backup']['tmpdir']);
								if($this->data->createBackupArchive($_SESSION['backup']['label'],$_SESSION['backup']['tmpdir'])) {
									$_SESSION['backup']['stage'] = 4;
									$this->doAction("backupDB");
									break;
								}
							} catch (OSIException $e) {
								print($e);
								//$d = opendir($_SESSION['backup']['tmpdir']);
								$files = scandir($_SESSION['backup']['tmpdir']);
								foreach($files as $f) {
									if(is_dir($f)) continue;
									unlink($_SESSION['backup']['tmpdir'].DIRECTORY_SEPARATOR.$f);
								}
								rmdir($_SESSION['backup']['tmpdir']);
								$_SESSION['backup']['stage'] = 0;
								$this->doAction("backupDB");
								break;
							}
						}
						if($_SESSION['backup']['stage'] == 4) {
							print($this->createSuccessMessage("Backup file created.  To download this file <a href=\"".$_SESSION['backup']['dl']."\">click here.</a>"));
							rmdir($_SESSION['backup']['tmpdir']);
							$_SESSION['backup'] = null;
							unset($_SESSION['backup']);
							$this->doAction("backupDB");
							break;
						}
					}
					break;

				case "restoreDB":
					if(!isset($_SESSION['restore']['stage'])) {
						$_SESSION['restore']['stage'] = 0;
						$this->doAction("restoreDB");
						break;
					}
					if($_SESSION['restore']['stage'] == 0) {
						$form = $this->cp->parseForm("ImportDatabase");
						$form = str_replace("[FormAction]",$_SERVER['PHP_SELF']."?a=restoreDB&amp;submitted",$form);
						$form = str_replace("[Location]",$_SERVER['PHP_SELF']."?a=restoreDB&submitted",$form);
						print($form);
						$_SESSION['restore']['stage'] = 1;
						break;
					}
					if($_SESSION['restore']['stage'] == 1) {
						if(isset($_GET['submitted'])) {
							try {
								$info = $this->cp->processFormInput("ImportDatabase");
							} catch (FileException $e) {
								print($e);
								$_SESSION['restore']['stage'] = 0;
								$this->doAction("restoreDB");
								break;
							}
							if(isset($info) && $info) {
								move_uploaded_file($info[0]['tmp_name'],BACKUPS.$info[0]['name']);
								try {
									if($this->data->validateBackupArchive($info[0]['name'])) {
										$zip = new ZipArchive;
										$zip->open(BACKUPS.$info[0]['name']);
										$zip->extractTo(TMPDIR);
										$zip->close();
										$_SESSION['restore']['cryptkey'] = $info[1];
										$_SESSION['restore']['stage'] = 2;
										$this->doAction("restoreDB");
									} else {
										throw new DataValidationException(3,"Bad Archive","The file specified could not be verified as a valid backup archive file.");
									}
								} catch (DataValidationException $e) {
									print($e);
									$_SESSION['restore']['stage'] = 0;
									unlink(BACKUPS.$info[0]['name']);
									$this->doAction("restoreDB");
									break;
								} catch (FileException $e) {
									print($e);
									$_SESSION['restore']['stage'] = 0;
									unlink(BACKUPS.$info[0]['name']);
									$this->doAction("restoreDB");
									break;
								}
								break;
							} else {
								$_SESSION['restore']['stage'] = 0;
								$this->doAction("restoreDB");
								break;
							}
						} else {
							$_SESSION['restore']['stage'] = 0;
							$this->doAction("restoreDB");
							break;
						}
					}
					if($_SESSION['restore']['stage'] == 2) {
						$ini = parse_ini_file(TMPDIR."backup.ini");
						if($ini['encrypted'] == true || $ini['encrypted'] == "true") {
							if($this->data->challengeRecoveryPassword($ini['catalyst'],$_SESSION['restore']['cryptkey'])) {
								$files = array("OSIPermissions.csv","OSIUsers.csv","OSISealants.csv","OSIManufacturers.csv","OSIColors.csv","OSISearchColors.csv");
								foreach($files as $f) {
									$decrypted = $this->data->decryptFile(TMPDIR.$f,$_SESSION['restore']['cryptkey'],$ini['drip']);
									$d = fopen(TMPDIR.$f,"wb");
									fwrite($d,$decrypted);
									fclose($d);
								}
								$_SESSION['restore']['stage'] = 3;
								$this->doAction("restoreDB");
								break;
							} else {
								try {
									throw new DataValidationException(3,"Decryption Error","The decryption key is incorrect, please try again.");
								} catch (DataValidationException $e) {
									print($e);
									$_SESSION['restore']['stage'] = 0;
									$this->doAction("restoreDB");
									break;
								}
								break;
							}
						} else {
							$_SESSION['restore']['stage'] = 3;
							$this->doAction("restoreDB");
							break;
						}
					}
					if($_SESSION['restore']['stage'] == 3) {
						$this->dbi->importDatabase(TMPDIR);
						unset($_SESSION['restore']);
						$this->doAction("exportInventory");
						print($this->createSuccessMessage("The database archive was imported successfully."));
					}
					break;
			}
		} else {
			throw new PermissionsException();
		}
	}

	/**
	 * Performs an administrative color action.
	 *
	 * @param string A valid administrative color action.
	 * @throws OSIException
	 * @throws DataValidationException
	 * @throws PermissionsException
	 * @throws BadFormInputException
	 * @throws FileException
	 */
	public function doColorAction($action) {
		if(isset($_SESSION['user']['attrib']) && $_SESSION['user']['attrib']->getPermissions()->canManageColors()) {
			switch($action) {
				case "newColor":
					if(isset($_SESSION['lastStatus']) && $_SESSION['lastStatus'] != "") {
						print($_SESSION['lastStatus']);
						$_SESSION['lastStatus'] = "";
					}
					$form = $this->cp->parseForm("CreateColor");
					$formAction = "?a=createColor";
					$form = str_replace("[FormAction]",$formAction,$form);
					$form = str_replace("[ManufacturerList]",$this->generateManufacturerList("createColor"),$form);
					$form = str_replace("[OSIMatch]",$this->generateSealantList("colorCreate","match"),$form);
					$form = str_replace("[OSIMustCoat]",$this->generateSealantList("colorCreate","mustCoat"),$form);
					print($form);
					break;

				case "createColor":
					try {
						$info = $this->cp->processFormInput("CreateColor");
					} catch (BadFormInputException $e) {
						print($e);
					}
					if(isset($info) && $info) {
						$mans = $this->getAllManufacturers();
						$sealants = $this->getAllSealants();
						$man = $mans[$info[8]];
						//Check for duplicate entry
						$dup = $this->dbi->query("SELECT ColorID FROM OSIColors WHERE Name = '".$this->dbi->getDBI()->real_escape_string($info[1])."' and ManufacturerID = '".$this->dbi->getDBI()->real_escape_string($man->getManufacturerID())."'");
						if($this->dbi->getDBI()->affected_rows == 0) {
							//$match = $this->dbi->query("SELECT * FROM OSISealants WHERE SealantID = '".$info[3]."'");
							//$mustcoat = $this->dbi->query("SELECT * FROM OSISealants WHERE SealantID = '".$info[4]."'");
							$info[7] = $_SESSION['user']['attrib']->getUserID();
							$info[3] = $sealants[$info[3]];
							$info[4] = $sealants[$info[4]];
							$color = new Color($info,$man);
							$this->dbi->insertRecords(array($color));
							print($this->createSuccessMessage("Successfully created color: ".$color->getManufacturer()->getManufacturerName()." - ".$color->getName()));
						} else {
							//DUPLICATE ENTRY CODE HERE
							if(isset($info) && $info) {
								$dup = $dup->fetch_object();
								$c = $this->dbi->query("SELECT * FROM OSIColors WHERE ColorID = ".$this->dbi->getDBI()->real_escape_string($dup->ColorID));
								$c = $c->fetch_object();
								$man = $mans[$c->ManufacturerID];
								$info[3] = $sealants[$info[3]];
								$info[4] = $sealants[$info[4]];
								$info[7] = $_SESSION['user']['attrib']->getUserID();
								$info[0] = $c->ColorID;
								$color = new Color($info,$man);
								$this->dbi->updateColors(array(),array(),array($color));
								print($this->createSuccessMessage("Successfully updated color: ".$color->getManufacturer()->getManufacturerName()." - ".$color->getName()));
							}
						}
						$this->doAction("newColor");
					} else {
						$this->doAction("newColor");
					}
					break;

				case "editColors":
					//Table Edit View
					$users = array();
					$permissions = array();
					$manufacturers = array();
					$sealants = array();
					$c = false;
					$u = $this->dbi->query("SELECT * FROM OSIUsers");
					$p = $this->dbi->query("SELECT * FROM OSIPermissions");
					$m = $this->dbi->query("SELECT * FROM OSIManufacturers");
					$s = $this->dbi->query("SELECT * FROM OSISealants");
					while($row = $p->fetch_object()) {
						$permissions[$row->MaskID] = new Permissions($row);
					}
					while($row = $u->fetch_object()) {
						$users[$row->UserID] = new User($row,$permissions[$row->MaskID]);
					}
					while($row = $m->fetch_object()) {
						$manufacturers[$row->ManufacturerID] = new Manufacturer($row);
					}
					while($row = $s->fetch_object()) {
						$sealants[$row->SealantID] = new Sealant($row);
					}
					do {
						if(isset($_SESSION['searchClauses'])) {
							$_SESSION['searchMode'] = "advanced";
							$query = $_SESSION['searchQuery']->buildSearchQuery($_SESSION['searchClauses']);
						} else {
							$query = $_SESSION['searchQuery']->buildSearchQuery();
						}
						if($query) {
							$c = $this->dbi->query($query);
						}
					} while($this->dbi->getDBI()->affected_rows == 0 && $query != false);
					if($c && $this->dbi->getDBI()->affected_rows != 0) { //Process colors

						$searchMode = $_SESSION['searchQuery']->getSearchMode();
						$_SESSION['searchQuery']->resetMode();
						$colors = array();
						while($row = $c->fetch_object()) {
							$color = array();
							$color[0] = $row->ColorID;
							$color[1] = $row->Name;
							$color[2] = $row->Reference;
							$color[3] = $sealants[$row->OSIMatch];
							$color[4] = $sealants[$row->OSIMustCoat];
							$color[5] = $row->LRV;
							$color[6] = $row->LastAccessed;
							$color[7] = $users[$row->UserID]->getUsername();
							$colors[$row->ColorID] = new Color($color,$manufacturers[$row->ManufacturerID]);
						}

						// SORT COLORS HERE!!!!
						// Send to view agent function with tokens and return results
						$colors = $this->viewer->sortColors($colors,$_SESSION['searchQuery']->getTokens(),$searchMode);

						$view = $this->cp->parseView("ColorTableEdit");
						$row = $this->cp->parseRowEntry("ColorEditTableEntry");
						$view = str_replace("[FormAction]","?a=colorSearch",$view);
						if(isset($_SESSION['searchMode']) && $_SESSION['searchMode'] == "advanced") {
							$view = str_replace("[SearchRequest]","\"Advanced Search\"",$view);
						} else {
							if(isset($_POST["colorSearch"])) {
								$view = str_replace("[SearchRequest]",$_POST["colorSearch"],$view);
							} else if(isset($_SESSION['lastQuery']) && $_SESSION['lastQuery'] != "") {
								$view = str_replace("[SearchRequest]",$_SESSION['lastQuery'],$view);
							}
						}
						print($this->viewer->showColors($colors,$view,$row,"tableEdit"));
						$_SESSION['lastQuery'] = $_SESSION['searchQuery']->getSearchString();
					} else { //No results or empty query
						$view = $this->cp->parseView("EditColorNotFound");
						$view = str_replace("[FormAction]","?a=colorSearch",$view);
						$view = str_replace("[SearchRequest]","\"No Results Found\"",$view);
						$view = str_replace("[Msg:ColorNotFound]","<p>No search results were found.</p>",$view);
						print($view);
						$_SESSION['lastQuery'] = "";
					}
					break;

				case "editColor":
					$form = $this->cp->parseForm("EditColor");
					$color = array();
					$c = $this->dbi->query("SELECT * FROM OSIColors WHERE ColorID = ".$this->dbi->getDBI()->real_escape_string($_GET['ColorID']));
					$c = $c->fetch_object();
					try {
						if($c == null) {
							throw new OSIException(3,"Invalid Color Lookup","The ID number of the color you are trying to edit does not exist.");
						}
					} catch (OSIException $e) {
						print($e);
						$this->doAction("editColors");
						break;
					}
					$man = $this->dbi->query("SELECT * FROM OSIManufacturers WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($c->ManufacturerID));
					$match = $this->dbi->query("SELECT * FROM OSISealants WHERE SealantID = ".$this->dbi->getDBI()->real_escape_string($c->OSIMatch));
					$mustcoat = $this->dbi->query("SELECT * FROM OSISealants WHERE SealantID = ".$this->dbi->getDBI()->real_escape_string($c->OSIMustCoat));
					$color[0] = $c->ColorID;
					$color[1] = $c->Name;
					$color[2] = $c->Reference;
					$color[3] = new Sealant($match->fetch_object());
					$color[4] = new Sealant($mustcoat->fetch_object());
					$color[5] = $c->LRV;
					$color[6] = null;
					$color[7] = null;
					$color = new Color($color,new Manufacturer($man->fetch_object()));
					$form = str_replace("[FormAction]","?a=updateColor",$form);
					$form = str_replace("[ColorID]",$color->getColorID(),$form);
					$form = str_replace("[ColorName]",$color->getName(),$form);
					$form = str_replace("[ColorReference]",$color->getReference(),$form);
					$form = str_replace("[ManufacturerList]",$this->generateManufacturerList("createColor",false,$color->getManufacturer()->getManufacturerID()),$form);
					$form = str_replace("[OSIMatch]",$this->generateSealantList("colorCreate","match",$color->getOSIMatch()->getSealantID()),$form);
					$form = str_replace("[OSIMustCoat]",$this->generateSealantList("colorCreate","mustCoat",$color->getOSIMustCoat()->getSealantID()),$form);
					$form = str_replace("[LRV]",$color->getLRV(),$form);
					print($form);
					break;

				case "updateColor":
					try {
						$info = $this->cp->processFormInput("EditColor");
					} catch (BadFormInputException $e) {
						print($e);
					} catch (DataValidationException $e) {
						print($e);
					}
					if(isset($info) && $info) {
						$c = $this->dbi->query("SELECT * FROM OSIColors WHERE ColorID = ".$this->dbi->getDBI()->real_escape_string($info[0]));
						$c = $c->fetch_object();
						try {
							if($c == null) {
								throw new OSIException(3,"Invalid Color Lookup","The ID number of the color you are trying to edit does not exist.");
							}
						} catch (OSIException $e) {
							print($e);
							$this->doAction("editColors");
							break;
						}
						$man = $this->dbi->query("SELECT * FROM OSIManufacturers WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($info[8]));
						$match = $this->dbi->query("SELECT * FROM OSISealants WHERE SealantID = ".$this->dbi->getDBI()->real_escape_string($info[3]));
						$mustcoat = $this->dbi->query("SELECT * FROM OSISealants WHERE SealantID = ".$this->dbi->getDBI()->real_escape_string($info[4]));
						$info[3] = new Sealant($match->fetch_object());
						$info[4] = new Sealant($mustcoat->fetch_object());
						$info[7] = $_SESSION['user']['attrib']->getUserID();
						$color = new Color($info,new Manufacturer($man->fetch_object()));
						$this->dbi->updateColors(array(),array(),array($color));
						$_POST["colorSearch"] = $_SESSION["lastQuery"];
						$_POST['editMode'] = "on";
						$_GET['ColorID'] = $_POST['colorid'];
						print($this->createSuccessMessage("Updated color ".$color->getName()." successfully."));
						$this->doAction("editColor");
					} else {
						$_GET['ColorID'] = $_POST['colorid'];
						$this->doAction("editColor");
					}
					break;

				case "deleteColor":
					$c = $this->dbi->query("SELECT * FROM OSIColors WHERE ColorID = ".$this->dbi->getDBI()->real_escape_string($_GET['ColorID']));
					$c = $c->fetch_object();
					try {
						if($c == null) {
							throw new OSIException(3,"Invalid Color Lookup","The ID number of the color you are trying to delete does not exist.");
						}
					} catch (OSIException $e) {
						print($e);
						$this->doAction("editColors");
						break;
					}
					$man = $this->dbi->query("SELECT * FROM OSIManufacturers WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($c->ManufacturerID));
					$match = $this->dbi->query("SELECT * FROM OSISealants WHERE SealantID = ".$this->dbi->getDBI()->real_escape_string($c->OSIMatch));
					$mustcoat = $this->dbi->query("SELECT * FROM OSISealants WHERE SealantID = ".$this->dbi->getDBI()->real_escape_string($c->OSIMustCoat));
					$c->OSIMatch = new Sealant($match->fetch_object());
					$c->OSIMustCoat = new Sealant($mustcoat->fetch_object());
					$color = new Color($c,new Manufacturer($man->fetch_object()));
					$this->dbi->updateColors(array($color),array(),array());
					if($this->dbi->query("SELECT * FROM OSIColors WHERE ColorID = ".$this->dbi->getDBI()->real_escape_string($_GET['ColorID']))) {
						$_SESSION['lastStatus'] = "Deleted color '".$color->getName()."'";
					} else {
						$_SESSION['lastStatus'] = "Unable to delete color.";
					}
					$_POST['editMode'] = "on";
					$this->doAction("editColors");
					break;

				case "importColors":
					set_time_limit(300);
					try {
						$info = $this->cp->processFormInput("UploadColors");
					} catch (OSIException $e) {
						print($e);
						$this->doAction("uploadColors");
						break;
					} catch (BadFormInputException $e) {
						print($e);
						$this->doAction("uploadColors");
						break;
					} catch (DataValidationException $e) {
						print($e);
						$this->doAction("uploadColors");
						break;
					} catch (FileException $e) {
						print($e);
						$this->doAction("uploadColors");
						break;
					}
					if(isset($info) && $info) {
						try {
							$array = $this->data->processColorsFile($info[0]);
						}  catch (OSIException $e) {
							print($e);
							$this->doAction("uploadColors");
							break;
						} catch (BadFormInputException $e) {
							print($e);
							$this->doAction("uploadColors");
							break;
						} catch (DataValidationException $e) {
							print($e);
							$this->doAction("uploadColors");
							break;
						} catch (FileException $e) {
							print($e);
							$this->doAction("uploadColors");
							break;
						}
						$sealants = $this->getAllSealants();
						$mans = $this->getAllManufacturers();
						if(count($mans) == 0) {
							$mans[1] = new Manufacturer(array(null,""));
						}
						$parsed = 0;
						$inserted = 0;
						$updated = 0;
						$newManufacturers = 0;
						$skipped = 0;
						$data = array('exist' => array(), 'noexist' => array(), 'newMan' => array(), 'update' => array(), 'insert' => array());
						if(is_array($array) && count($array) > 0) {
							foreach($array as $a) {
								foreach($a as &$v) {
									$v = trim(trim($v," ' \"\t\n\r\0\x0B\x93\x94")," ' \"\t\n\r\0\x0B\x93\x94");
								}
								$found = false;
								/* BUG FIND:
									Need to cycle through manufacturers instead of attempting to location by ID number.  ID number in database is higher than loop iterator max value therefore is doesn't actually get that high.
								*/
								for($i = 1; $i < (count($mans) + 1); $i++) {
									if(isset($mans[$i])) {
										if(isset($sealants[$a[3]]) && isset($sealants[$a[4]])) {
											if($mans[$i]->getManufacturerName() == $a[0]) {
												//Manufacturer found
												$data['exist'][] = new Color(array(null,$a[1],$a[2],$sealants[$a[3]],$sealants[$a[4]],$a[5],null,$_SESSION['user']['attrib']->getUserID()),$mans[$i]);
												$parsed++;
												$found = true;
											} else if($i == count($mans) && $found == false) {
												//couldn't find manufacturer
												$data['noexist'][] = new Color(array(null,$a[1],$a[2],$sealants[$a[3]],$sealants[$a[4]],$a[5],null,$_SESSION['user']['attrib']->getUserID()),new Manufacturer(array(null,$a[0])));
												$data['newMan'][] = trim($a[0]);
												$parsed++;
											}
										} else {
											//Invalid sealant ID specified
											$parsed++;
											$skipped++;
											break;
										}
									}
								}
							}
							$newmans = array_unique($data['newMan']);
							$data['newMan'] = array();
							foreach($newmans as $n) {
								$data['newMan'][] = new Manufacturer(array(null,$n));
								$newManufacturers++;
							}
						}

						//Send manufacturers to the DB and then refresh the manufacturers list.
						$this->dbi->insertRecords($data['newMan']);
						$mans = $this->getAllManufacturers();
						foreach($data['noexist'] as $d) {
							foreach($mans as $m) {
								if($m->getManufacturerName() == $d->getManufacturer()->getManufacturerName()) {
									$d->getManufacturer()->setManufacturerID($m->getManufacturerID());
									$data['exist'][] = $d;
								}
							}
						}

						//Select all colors in $data['exist'] and store positive results in a seperate array
						$stmt = $this->dbi->getDBI()->prepare("SELECT ColorID, ManufacturerID, Name, Reference, OSIMatch, OSIMustCoat, LRV FROM OSIColors WHERE Name = ? AND ManufacturerID = ?");
						$stmt->bind_param("si",$name,$manid);
						foreach($data['exist'] as $d) {
							$name = $d->getName();
							$manid = $d->getManufacturer()->getManufacturerID();
							$stmt->execute();
							$stmt->bind_result($id,$manid,$name,$reference,$match,$mustcoat,$lrv);
							$stmt->store_result();
							if($stmt->num_rows == 1) {
								$changed = false;
								$stmt->fetch();
								//Compare values
								if($d->getManufacturer()->getManufacturerID() != $manid || $d->getName() != $name || $d->getReference() != $reference || $d->getOSIMatch()->getSealantID() != $match || $d->getOSIMustCoat()->getSealantID() != $mustcoat || $d->getLRV() != $lrv) {
									$d->setColorID($id);
									$data['update'][] = $d;
									$updated++;
								}
							} else {
								$data['insert'][] = $d;
								$inserted++;
							}
						}

						//print_r($data);
						$report = '<div style="width: 700px; border: solid 2px black; margin-left: auto; margin-right: auto;"><div style="font-weight: bold; background: #33cc00">Report</div><div style="background: #eeeeee;"><ul class="report">';
						if($parsed > 0) {
							if($parsed == 1) {
								$report .= "<li>$parsed row was parsed from the colors file.</li>";
							} else {
								$report .= "<li>$parsed rows were parsed from the colors file.</li>";
							}
						}
						if($newManufacturers > 0) {
							if($newManufacturers == 1) {
								$report .= "<li>$newManufacturers new manufacturer was added to the database.</li>";
							} else {
								$report .= "<li>$newManufacturers new manufacturers were added to the database.</li>";
							}
						}
						if($updated > 0) {
							if($updated == 1) {
								$report .= "<li>$updated color was updated with new information.</li>";
							} else {
								$report .= "<li>$updated colors were updated with new information.</li>";
							}
						}
						if($inserted > 0) {
							if($inserted == 1) {
								$report .= "<li>$inserted new color was added to the database.</li>";
							} else {
								$report .= "<li>$inserted new colors were added to the database.</li>";
							}
						}
						if($skipped > 0) {
							if($skipped == 1) {
								$report .= "<li>$skipped row was skipped because it containted an invalid Sealant ID.</li>";
							} else {
								$report .= "<li>$skipped rows were skipped because they containted an invalid Sealant ID.</li>";
							}
						}
						if($newManufacturers == 0 && $updated == 0 && $inserted == 0) {
							$report .= "<li>Operation completed without database modifications. No color changes were detected.</li>";
						} else {
							$ignored = $parsed - $skipped - $updated - $inserted;
							if($ignored > 0) {
								if($ignored == 1) {
									$report .= "<li>$ignored row was ignored because no new changes were detected.</li>";
								} else {
									$report .= "<li>$ignored rows were ignored because no new changes were detected.</li>";
								}
							}
						}
						$report .= "</ul></div></div>";
						$this->dbi->updateColors(array(),$data['insert'],$data['update']);
						print($report);
					}
					$this->doAction("uploadColors");
					break;

				case "uploadColors":
					$form = $this->cp->parseForm("UploadColors");
					$form = str_replace("[FormAction]",$_SERVER['PHP_SELF']."?a=importColors",$form);
					print($form);
					break;
			}
		} else {
			throw new PermissionsException();
		}
	}

	/**
	 * Performs a color viewing action.
	 *
	 * @param string A valid color viewing action.
	 * @throws PermissionsException
	 */
	public function doColorViewAction($action) {
		if(isset($_SESSION['user']['attrib']) && $_SESSION['user']['attrib']->getPermissions()->canBrowseColors()) {
			switch($action) {
				case "viewColors":
					//Table Browse View
					$users = array();
					$permissions = array();
					$manufacturers = array();
					$sealants = array();
					$c = false;
					$u = $this->dbi->query("SELECT * FROM OSIUsers");
					$p = $this->dbi->query("SELECT * FROM OSIPermissions");
					$m = $this->dbi->query("SELECT * FROM OSIManufacturers");
					$s = $this->dbi->query("SELECT * FROM OSISealants");
					while($row = $p->fetch_object()) {
						$permissions[$row->MaskID] = new Permissions($row);
					}
					while($row = $u->fetch_object()) {
						$users[$row->UserID] = new User($row,$permissions[$row->MaskID]);
					}
					while($row = $m->fetch_object()) {
						$manufacturers[$row->ManufacturerID] = new Manufacturer($row);
					}
					while($row = $s->fetch_object()) {
						$sealants[$row->SealantID] = new Sealant($row);
					}
					do {
						if(isset($_SESSION['searchClauses'])) {
							$_SESSION['searchMode'] = "advanced";
							$query = $_SESSION['searchQuery']->buildSearchQuery($_SESSION['searchClauses']);
						} else {
							$query = $_SESSION['searchQuery']->buildSearchQuery();
						}
						if($query) {
							$c = $this->dbi->query($query);
						}
					} while($this->dbi->getDBI()->affected_rows == 0 && $query != false);
					if($c && $this->dbi->getDBI()->affected_rows != 0) { //Process colors
						$searchMode = $_SESSION['searchQuery']->getSearchMode();
						$_SESSION['searchQuery']->resetMode();
						$colors = array();
						while($row = $c->fetch_object()) {
							$color = array();
							$color[0] = $row->ColorID;
							$color[1] = $row->Name;
							$color[2] = $row->Reference;
							$color[3] = $sealants[$row->OSIMatch];
							$color[4] = $sealants[$row->OSIMustCoat];
							$color[5] = $row->LRV;
							$color[6] = $row->LastAccessed;
							$color[7] = $users[$row->UserID]->getUsername();
							$colors[$row->ColorID] = new Color($color,$manufacturers[$row->ManufacturerID]);
						}

						// SORT COLORS HERE!!!!
						// Send to view agent function with tokens and return results
						$colors = $this->viewer->sortColors($colors,$_SESSION['searchQuery']->getTokens(),$searchMode);

						$view = $this->cp->parseView("ColorTableBrowse");
						$row = $this->cp->parseRowEntry("ColorViewTableEntry");
						$view = str_replace("[FormAction]","?a=colorSearch",$view);
						if(isset($_SESSION['searchMode']) && $_SESSION['searchMode'] == "advanced") {
							$view = str_replace("[SearchRequest]","\"Advanced Search\"",$view);
						} else {
							if(isset($_POST["colorSearch"])) {
								$view = str_replace("[SearchRequest]",$_POST["colorSearch"],$view);
							} else if(isset($_SESSION['lastQuery']) && $_SESSION['lastQuery'] != "") {
								$view = str_replace("[SearchRequest]",$_SESSION['lastQuery'],$view);
								$_SESSION['lastQuery'] = $_SESSION['searchQuery']->getSearchString();
							}
						}
						if(isset($_GET['exportResults'])) {
							if($_GET['exportResults'] == "html") {
								$export = DATA."Export.html";
								$export = file_get_contents($export);
								$output = $this->viewer->showColors($colors,$view,$row);
								$view = $this->cp->parseView("ColorTableHTMLExport");
								$foutput = $this->viewer->showColors($colors,$view,$row);
								$etime = date("F j, Y g:i:sA");
								$ftime = date("mdyhis");
								$fname = "OSIExport_".$ftime.".html";
								$file = fopen(TMPDIR.$fname,"wb");
								$export = str_replace("[Username]",$_SESSION['user']['attrib']->getUsername(),$export);
								$export = str_replace("[CreateTime]",$etime,$export);
								$export = str_replace("[SystemVersion]",OSI_VERSION,$export);
								$export = str_replace("[SearchResults]",$foutput,$export);
								$export = str_replace("[TableSorter]",file_get_contents(INCLUDES."tableSort.min.js"),$export);
								fwrite($file,$export);
								fclose($file);
								$output = str_replace("[ExportOptions]","<span class=\"export\">Export Results: <a href=\"".$_SERVER['PHP_SELF']."?a=colorSearch&amp;exportResults=html\">HTML</a> | <a href=\"".$_SERVER['PHP_SELF']."?a=colorSearch&amp;exportResults=csv\">CSV</a></span>",$output);
								print($this->createSuccessMessage("Your export file $fname has been created, you may download this file by <a href=\"".TMPDIR.$fname."\" target=\"_blank\">clicking here</a>."));
								print($output);
								$this->doAction("purgeTempFiles");
							} else if($_GET['exportResults'] == "csv") {
								$etime = date("F j, Y g:i:sA");
								$ftime = date("mdyhis");
								$fname = "OSIExport_".$ftime.".csv";
								$file = fopen(TMPDIR.$fname,"wb");
								fwrite($file,"OSI Colors Export".PHP_EOL);
								fwrite($file,"\"Created for user: ".$_SESSION['user']['attrib']->getUsername()." on ".$etime."\"".PHP_EOL);
								fwrite($file,PHP_EOL);
								fwrite($file,"COLOR NAME,MANUFACTURER,REFERENCE,MATCH,MUST COAT,LRV".PHP_EOL);
								foreach($colors as $c) {
									$export = array();
									$export[0] = $c->getName();
									$export[1] = $c->getManufacturer()->getManufacturerName();
									$export[2] = $c->getReference();
									$export[3] = $c->getOSIMatch()->getSealantID();
									$export[4] = $c->getOSIMustCoat()->getSealantID();
									$export[5] = $c->getLRV();
									foreach($export as &$e) {
										if(preg_match("/^[0].{0,}$/",$e)) {
											$e = "'$e";
										}
									}
									$export = implode(",",$export);
									fwrite($file,$export.PHP_EOL);
								}
								fclose($file);
								$output = $this->viewer->showColors($colors,$view,$row);
								$output = str_replace("[ExportOptions]","<span class=\"export\">Export Results: <a href=\"".$_SERVER['PHP_SELF']."?a=colorSearch&amp;exportResults=html\">HTML</a> | <a href=\"".$_SERVER['PHP_SELF']."?a=colorSearch&amp;exportResults=csv\">CSV</a></span>",$output);
								print($this->createSuccessMessage("Your export file $fname has been created, you may download this file by <a href=\"".TMPDIR.$fname."\" target=\"_blank\">clicking here</a>."));
								print($output);
								$this->doAction("purgeTempFiles");
							}
						} else {
							$output = $this->viewer->showColors($colors,$view,$row);
							$output = str_replace("[ExportOptions]","<span class=\"export\">Export Results: <a href=\"".$_SERVER['PHP_SELF']."?a=colorSearch&amp;exportResults=html\">HTML</a> | <a href=\"".$_SERVER['PHP_SELF']."?a=colorSearch&amp;exportResults=csv\">CSV</a></span>",$output);
							print($output);
						}
						$_SESSION['lastQuery'] = $_SESSION['searchQuery']->getSearchString();
					} else { //No results or empty query
						$view = $this->cp->parseView("ColorNotFound");
						$view = str_replace("[FormAction]","?a=colorSearch",$view);
						$view = str_replace("[SearchRequest]","\"No Results Found\"",$view);
						$view = str_replace("[Msg:ColorNotFound]","<p>No search results were found.</p>",$view);
						print($view);
						$_SESSION['lastQuery'] = "";
					}
					break;

				case "browseManufacturerColors":
					$u = $this->dbi->query("SELECT * FROM OSIUsers WHERE UserID = ".$this->dbi->getDBI()->real_escape_string($_SESSION['user']['attrib']->getUserID()));
					$u = $u->fetch_object();
					$p = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID = ".$this->dbi->getDBI()->real_escape_string($u->MaskID));
					$p = new Permissions($p->fetch_object());
					$u = new User($u,$p);
					$newMan = 0;
					if(!isset($_POST['manufacturer']) || $_POST['manufacturer'] == "") {
						$_POST['manufacturer'] = 0;
					}
					if(isset($_GET['manID']) && $_GET['manID'] != 0) {
						$_POST['manufacturer'] = $_GET['manID'];
					}
					$newMan = $_POST['manufacturer'];
					$colors = array();
					$records = $this->dbi->query("SELECT * FROM OSIColors WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($newMan)." ORDER BY Name ASC");
					if($this->dbi->getDBI()->affected_rows > 0) {
						$sealants = $this->getAllSealants();
						if(count($sealants) > 0) {
							$manufacturer = $this->dbi->query("SELECT * FROM OSIManufacturers WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($_POST['manufacturer']));
							$manufacturer = new Manufacturer($manufacturer->fetch_object());
							while($r = $records->fetch_object()) {
								//print("Match: ".$r->OSIMatch." MustCoat: ".$r->OSIMustCoat);
								$color = array();
								$color[0] = $r->ColorID;
								$color[1] = $r->Name;
								$color[2] = $r->Reference;
								$color[3] = $sealants[$r->OSIMatch];
								$color[4] = $sealants[$r->OSIMustCoat];
								$color[5] = $r->LRV;
								$color[6] = $r->LastAccessed;
								$color[7] = $u->getUsername();
								$colors[$r->ColorID] = new Color($color,$manufacturer);
							}
							$view = $this->cp->parseView("ColorCategoryBrowse");
							$row = $this->cp->parseRowEntry("ColorViewBrowseEntry");
							$erow = $this->cp->parseRowEntry("ColorViewTableEntry");
							$view = str_replace("[ManufacturerName]",$manufacturer->getManufacturerName(),$view);
							$view = str_replace("[FormAction]","?a=browseManufacturerColors",$view);
							$view = str_replace("[ManufacturerList]",$this->generateManufacturerList("createColor",true),$view);
							//print($this->viewer->showColors($colors,$view,$row,"categoryView"));

							// START MANUFACTURER BROWSER EXPORT CODE
							if(isset($_GET['exportResults'])) {
								if($_GET['exportResults'] == "html") {
									$export = DATA."Export.html";
									//$tableSorter = file_get_contents(INCLUDES."tableSort.js");
									$export = file_get_contents($export);
									$output = $this->viewer->showColors($colors,$view,$row,"categoryView");
									$view = $this->cp->parseView("ColorTableHTMLExport");
									$foutput = $this->viewer->showColors($colors,$view,$erow,"tableView");
									$etime = date("F j, Y g:i:sA");
									$ftime = date("mdyhis");
									$fname = "OSIExport_".$ftime.".html";
									$file = fopen(TMPDIR.$fname,"wb");
									$export = str_replace("[Username]",$_SESSION['user']['attrib']->getUsername(),$export);
									$export = str_replace("[CreateTime]",$etime,$export);
									$export = str_replace("[SystemVersion]",OSI_VERSION,$export);
									$export = str_replace("[SearchResults]",$foutput,$export);
									$export = str_replace("[TableSorter]", file_get_contents(INCLUDES."tableSort.min.js"),$export);
									fwrite($file,$export);
									fclose($file);
									$output = str_replace("[ExportOptions]","<span class=\"export\">Export Results: <a href=\"".$_SERVER['PHP_SELF']."?a=browseManufacturerColors&amp;exportResults=html&amp;manID=".$newMan."\">HTML</a> | <a href=\"".$_SERVER['PHP_SELF']."?a=browseManufacturerColors&amp;exportResults=csv&amp;manID=".$newMan."\">CSV</a></span>",$output);
									print($this->createSuccessMessage("Your export file $fname has been created, you may download this file by <a href=\"".TMPDIR.$fname."\" target=\"_blank\">clicking here</a>."));

									print($output);
									$this->doAction("purgeTempFiles");
								} else if($_GET['exportResults'] == "csv") {
									$etime = date("F j, Y g:i:sA");
									$ftime = date("mdyhis");
									$fname = "OSIExport_".$ftime.".csv";
									$file = fopen(TMPDIR.$fname,"wb");
									fwrite($file,"OSI Colors Export".PHP_EOL);
									fwrite($file,"\"Created for user: ".$_SESSION['user']['attrib']->getUsername()." on ".$etime."\"".PHP_EOL);
									fwrite($file,PHP_EOL);
									fwrite($file,"COLOR NAME,MANUFACTURER,REFERENCE,MATCH,MUST COAT,LRV".PHP_EOL);
									foreach($colors as $c) {
										$export = array();
										$export[0] = $c->getName();
										$export[1] = $c->getManufacturer()->getManufacturerName();
										$export[2] = $c->getReference();
										$export[3] = $c->getOSIMatch()->getSealantID();
										$export[4] = $c->getOSIMustCoat()->getSealantID();
										$export[5] = $c->getLRV();
										foreach($export as &$e) {
											if(preg_match("/^[0].{0,}$/",$e)) {
												$e = "'$e";
											}
										}
										$export = implode(",",$export);
										fwrite($file,$export.PHP_EOL);
									}
									fclose($file);
									$output = $this->viewer->showColors($colors,$view,$row,"categoryView");
									$output = str_replace("[ExportOptions]","<span class=\"export\">Export Results: <a href=\"".$_SERVER['PHP_SELF']."?a=browseManufacturerColors&amp;exportResults=html&amp;manID=".$newMan."\">HTML</a> | <a href=\"".$_SERVER['PHP_SELF']."?a=browseManufacturerColors&amp;exportResults=csv&amp;manID=".$newMan."\">CSV</a></span>",$output);
									print($this->createSuccessMessage("Your export file $fname has been created, you may download this file by <a href=\"".TMPDIR.$fname."\" target=\"_blank\">clicking here</a>."));

									print($output);
									$this->doAction("purgeTempFiles");
								}
							} else {
								$output = $this->viewer->showColors($colors,$view,$row,"categoryView");
								$output = str_replace("[ExportOptions]","<span class=\"export\">Export Results: <a href=\"".$_SERVER['PHP_SELF']."?a=browseManufacturerColors&amp;exportResults=html&amp;manID=".$newMan."\">HTML</a> | <a href=\"".$_SERVER['PHP_SELF']."?a=browseManufacturerColors&amp;exportResults=csv&amp;manID=".$newMan."\">CSV</a></span>",$output);
								//$output .= '<script language="Javascript" type="text/javascript">document.forms["manufacturerSelector"].elements["lastManufacturer"].value = '.$newMan.';</script>';
								print($output);
							}

							//print($this->viewer->showColors($colors,$view,$row,"categoryView"));
							// END SEGEMENT
						}
					} else {
						$view = $this->cp->parseView("ManufacturerNotFound");
						$manufacturer = $this->dbi->query("SELECT * FROM OSIManufacturers WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($_POST['manufacturer']));
						if($this->dbi->getDBI()->affected_rows > 0) {
							$manufacturer = new Manufacturer($manufacturer->fetch_object());
							$view = str_replace("[ManufacturerName]",$manufacturer->getManufacturerName(),$view);
						} else {
							$view = str_replace("[ManufacturerName]","\"Not Specified\"",$view);
						}
						$view = str_replace("[ManufacturerList]",$this->generateManufacturerList("createColor",true),$view);
						$view = str_replace("[FormAction]","?a=browseManufacturerColors",$view);
						$view = str_replace("[Msg:ManufacturerNotFound]","No results found...",$view);
						print($view);
					}
					break;

				case "colorSearch":
					if(!isset($_SESSION['searchMode'])) {
						$_SESSION['searchMode'] = "standard";
						$_POST["colorSearch"] = "";
					}
					if(isset($_POST['standardForm'])) {
						unset($_SESSION['searchClauses']);
						if(isset($_POST["colorSearch"]) && $_POST["colorSearch"] != "") {
							$s = new SearchAgent($_POST["colorSearch"]);
							$s->tokenize();
							$_SESSION['searchQuery'] = $s;
							$_SESSION['searchMode'] = "standard";
						} else {
							$s = new SearchAgent(" ");
							$s->tokenize();
							$_SESSION['searchQuery'] = $s;
							$_SESSION['searchMode'] = "standard";
						}
					} else if(isset($_SESSION['searchMode']) && $_SESSION['searchMode'] == "advanced") {
						//do nothing
					} else if(isset($_SESSION['lastQuery']) && $_SESSION['lastQuery'] != "") {
						$s = new SearchAgent($_SESSION['lastQuery']);
						$s->tokenize();
						$_SESSION['searchQuery'] = $s;
						$_SESSION['searchMode'] = "standard";
					}  else {
						$s = new SearchAgent(" ");
						$s->tokenize();
						$_SESSION['searchQuery'] = $s;
						$_SESSION['searchMode'] = "standard";
					}
					if($_GET['a'] == "editColors" || isset($_GET['editMode']) || isset($_POST['editMode'])) {
						$this->doAction("editColors");
					} else {
						$this->doAction("viewColors");
					}
					break;

				case "advancedColorSearch":
					$form = $this->cp->parseForm("AdvancedColorSearch");
					$form = str_replace("[FormAction]","?a=advancedSearch",$form);
					$form = str_replace("[ManufacturerSearchList]",$this->generateManufacturerList("searchColor"),$form);
					$form = str_replace("[OSISearchMatch]",$this->generateSealantList("colorSearch","match"),$form);
					$form = str_replace("[OSISearchMustCoat]",$this->generateSealantList("colorSearch","mustCoat"),$form);
					print($form);
					break;

				case "advancedSearch":
					$info = $this->cp->processFormInput("AdvancedColorSearch");
					$clauses = array();
					if($info[0] != "" || strcmp($info[0],"All Colors") != 0) $clauses['keywords'] = $info[0];
					if($info[1] != 0) $clauses['manufacturer'] = $info[1];
					if($info[2] === "000" || $info[2] != 0) $clauses['match'] = $info[2];
					if($info[3] === "000" || $info[3] != 0) $clauses['mustcoat'] = $info[3];
					if($info[5] != "") {
						$clauses['lrvcriteria'] = $info[4];
						$clauses['lrv'] = $info[5];
					}
					$s = new SearchAgent("",false);
					if(isset($_POST['advancedForm'])) {
						$_SESSION['searchMode'] = "advanced";
					}
					$_SESSION['searchClauses'] = $clauses;
					$_SESSION['searchQuery'] = $s;
					if($_GET['a'] == "editColors" || isset($_POST['editMode']) || isset($_GET['editMode'])) {
						$this->doAction("editColors");
					} else {
						$this->doAction("viewColors");
					}
					break;
			}
		} else {
			throw new PermissionsException();
		}
	}

	/**
	 * Performs a sealant action.
	 *
	 * @param string A valid sealant action.
	 * @throws OSIException
	 * @throws DataValidationException
	 * @throws PermissionsException
	 * @throws BadFormInputException
	 * @throws FileException
	 */
	public function doSealantAction($action) {
		if(isset($_SESSION['user']['attrib']) && $_SESSION['user']['attrib']->getPermissions()->canManageInventory()) {
			switch($action) {
				case "manageInventory":
					$form = $this->cp->parseForm("ManageInventory");
					$formAction = $_SERVER['PHP_SELF']."?a=updateInventory";
					$csiStock = array();
					$vendorStock = array();
					$osiStock = array();
					$result = $this->dbi->query("SELECT SealantID FROM OSISealants ORDER BY SealantID ASC");
					while($row = $result->fetch_object()) {
						$osiStock[] = $row->SealantID;
					}
					$result = $this->dbi->query("SELECT SealantID FROM OSISealants WHERE LocalStock = TRUE ORDER BY SealantID ASC");
					while($row = $result->fetch_object()) {
						$csiStock[] = $row->SealantID;
					}
					$result = $this->dbi->query("SELECT SealantID FROM OSISealants WHERE VendorStock = TRUE ORDER BY SealantID ASC");
					while($row = $result->fetch_object()) {
						$vendorStock[] = $row->SealantID;
					}
					$osiStock = implode("\\n",$osiStock);
					$vendorStock = implode("\\n",$vendorStock);
					$csiStock = implode("\\n",$csiStock);
					$form = str_replace("[FormAction]",$formAction,$form);
					$form = str_replace("[PHP_SELF]",$_SERVER['PHP_SELF'],$form);
					$form = str_replace("[Download]",DATA."OSIMasterSealantList.csv",$form);
					global $js;
					$js = '<script language="javascript"> ';
					$js .= 'populateSealants("csiStock","'.$csiStock.'"); ';
					$js .= 'populateSealants("vendorStock","'.$vendorStock.'"); ';
					$js .= 'populateSealants("osiStock","'.$osiStock.'"); ';
					$js .= '</script>';
					print($form);
					print($js);
					break;

				case "updateInventory":
					$master = array();
					$sealants = array();
					$delete = array();
					$create = array();
					try {
						$info = $this->cp->processFormInput("ManageInventory");
					} catch (BadFormInputException $e) {
						print($e);
						$this->doAction("manageInventory");
						break;
					} catch (DataValidationException $e) {
						print($e);
						$this->doAction("manageInventory");
						break;
					} catch (OSIException $e) {
						print($e);
						$this->doAction("manageInventory");
						break;
					}
					if(isset($info[3]) && $info[3]['error'] == UPLOAD_ERR_OK) {
						$this->doAction("importInventory");
						break;
					}
					$result = $this->dbi->query("SELECT * FROM OSISealants ORDER BY SealantID ASC");
					while($r = $result->fetch_object()) {
						$master[$r->SealantID] = new Sealant($r);
					}

					//Check to see if there are differences between the master list
					//and the form input for all osi colors.  If items in the master
					//list are not found in the new list, move them to delete[].
					//Other colors additional in the osi color add to create[].
					$arr = explode("\n",$info[2]);
					foreach($master as $k => $v) {
						$found = false;
						foreach($arr as &$a) {
							$a = trim($a);
							if($a == "" || $a == "\n" || $a == null) {
								continue;
							}
							if($a != "" || $a != "\n") {
								if(strcmp($k,$a) == 0) { //match found
									$sealants[$a] = $v;
									$a = null;
									$found = true;
								}
							}
						}
						if(!$found) {
							$delete[$k] = $v;
						}
					}
					foreach($arr as $a) {
						if($a != null) $create[$a] = new Sealant(array($a,false,false));
					}
					//Set all flags on sealants[] to false;
					foreach($sealants as &$s) {
						$s->setVendorStock(false);
						$s->setLocalStock(false);
					}

					//Iterate vendor stock colors.  Colors not in sealants[] are added
					//to create[] (if they don't exist).  Colors in delete[] are
					//removed.
					$arr = explode("\n",$info[1]);
					foreach($arr as &$a) {
						$a = trim($a);
						if($a == "" || $a == "\n" || $a == null) {
							continue;
						}
						if(isset($delete[$a])) {
							$a = null;
							continue;
						} else {
							if(isset($sealants[$a])) {
								$sealants[$a]->setVendorStock(true);
							} else {
								$create[$a] = new Sealant(array($a,false,true));
							}
						}
					}

					//Iterate local stock colors.  Colors not in sealants[] are added
					//to create[] (if they don't exist).  Colors in delete[] are
					//removed.
					$arr = explode("\n",$info[0]);
					foreach($arr as &$a) {
						$a = trim($a);
						if($a == "" || $a == "\n" || $a == null) {
							continue;
						}
						if(isset($delete[$a])) {
							$a = null;
							continue;
						} else {
							if(isset($sealants[$a])) {
								$sealants[$a]->setLocalStock(true);
							} else {
								if(isset($create[$a])) {
									$create[$a]->setLocalStock(true);
								} else {
									$create[$a] = new Sealant(array($a,true,false));
								}
							}
						}
					}

					//Send it to the database

					$this->dbi->updateSealants($delete,$create,$sealants);
					$this->doAction("exportInventory");
					$this->doAction("manageInventory");
					break;



				case "importInventory";
					try {
						$info = $this->cp->processFormInput("ManageInventory");
					} catch (BadFormInputException $e) {
						print($e);
						$this->doAction("manageInventory");
						break;
					}
					$r = $this->dbi->query("SELECT * FROM OSISealants ORDER BY SealantID ASC");
					$caulk = array();
					while($o = $r->fetch_object()) {
						$caulk[$o->SealantID] = new Sealant($o);
					}
					try {
						$data = $this->data->importMasterSealantList($info[3],$caulk);
					} catch (FileException $e) {
						print($e);
						$this->doAction("manageInventory");
						break;
					} catch (DataValidationException $e) {
						print($e);
						$this->doAction("manageInventory");
						break;
					}
					$this->dbi->updateSealants($data['deletes'],$data['inserts'],$data['updates']);
					$this->doAction("exportInventory");
					$this->doAction("manageInventory");
					break;
			}
		} else {
			throw new PermissionsException();
		}
	}

	/**
	 * Performs a permissions action.
	 *
	 * @param string A valid permission action.
	 * @throws OSIException
	 * @throws DataValidationException
	 * @throws PermissionsException
	 * @throws BadFormInputException
	 */
	public function doPermissionsAction($action) {
		if(isset($_SESSION['user']['attrib']) && $_SESSION['user']['attrib']->getPermissions()->canManageUsers()) {
			switch($action) {
				case "newMask":
					$formAction = $_SERVER['PHP_SELF']."?a=createMask";
					$form = $this->cp->parseForm("CreatePermissionMask");
					print(str_replace("[FormAction]",$formAction,$form));
					break;

				case "createMask":
					try {
						$info = $this->cp->processFormInput("CreatePermissionMask");
					} catch (BadFormInputException $e) {
						print($e);
						$this->doAction("newMask");
						break;
					} catch (DataValidationException $e) {
						print($e);
						$this->doAction("newMask");
						break;
					} catch (OSIException $e) {
						print($e);
						$this->doAction("newMask");
						break;
					}
					if(isset($info) && $info) {
						$p = new Permissions($info);
						$this->dbi->insertRecords(array($p));
						if($this->dbi->getDBI()->errno == 0) {
							print($this->createSuccessMessage("Created permission mask '".$p->getMaskName()."'."));
						}
					} else {
						try {
							throw new OSIException(3,"Permissions Error","Unable to create a new permissions mask.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("newMask");
							break;
						}
					}
					$this->doAction("browseMasks");
					break;

				case "editMask":
					$formAction = "?a=updateMask";
					$form = $this->cp->parseForm("EditPermissionMask");
					$result = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID = ".$this->dbi->getDBI()->real_escape_string($_GET['MaskID']));
					$p = $result->fetch_object();
					if($p == null) {
						try {
							throw new OSIException(3,"Invalid Mask Lookup","The permission mask you are looking for does not exist in the database.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseMasks");
							break;
						}
					}
					$p = new Permissions($p);
					if($p->getMaskID() == 1) {
						try {
							throw new OSIException(2,"Administrator's Mask","This mask is the built-in system administrators permissions and cannot be edited.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseMasks");
							break;
						}
					}
					$form = str_replace("[FormAction]",$formAction,$form);
					$form = str_replace("[MaskID]",$p->getMaskID(),$form);
					$form = str_replace("[MaskName]",$p->getMaskName(),$form);
					$form = ($p->canManageUsers() == true ? str_replace('id="allowManageUsers"','id="allowManageUsers" checked="checked"',$form) : str_replace('id="denyManageUsers"','id="denyManageUsers" checked="checked"',$form));
					$form = ($p->canManageColors() == true ? str_replace('id="allowManageColors"','id="allowManageColors" checked="checked"',$form) : str_replace('id="denyManageColors"','id="denyManageColors" checked="checked"',$form));
					$form = ($p->canManageInventory() == true ? str_replace('id="allowManageInventory"','id="allowManageInventory" checked="checked"',$form) : str_replace('id="denyManageInventory"','id="denyManageInventory" checked="checked"',$form));
					$form = ($p->canManageDatabase() == true ? str_replace('id="allowManageDatabase"','id="allowManageDatabase" checked="checked"',$form) : str_replace('id="denyManageDatabase"','id="denyManageDatabase" checked="checked"',$form));
					$form = ($p->canManageOptions() == true ? str_replace('id="allowManageOptions"','id="allowManageOptions" checked="checked"',$form) : str_replace('id="denyManageOptions"','id="denyManageOptions" checked="checked"',$form));
					$form = ($p->canBrowseColors() == true ? str_replace('id="allowBrowseColors"','id="allowBrowseColors" checked="checked"',$form) : str_replace('id="denyBrowseColors"','id="denyBrowseColors" checked="checked"',$form));
					print($form);
					break;

				case "updateMask":
					try {
						$info = $this->cp->processFormInput("EditPermissionMask");
					} catch (BadFormInputException $e) {
						print($e);
						$_GET['MaskID'] = $_POST['maskid'];
						$this->doAction("editMask");
						break;
					} catch (DataValidationException $e) {
						print($e);
						$_GET['MaskID'] = $_POST['maskid'];
						$this->doAction("editMask");
						break;
					} catch (OSIException $e) {
						print($e);
						$_GET['MaskID'] = $_POST['maskid'];
						$this->doAction("editMask");
						break;
					}
					if(isset($info) && $info) {
						$result = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID = ".$this->dbi->getDBI()->real_escape_string($info[7]));
						$p = new Permissions($result->fetch_object());
						$p->setMaskName($info[0]);
						$p->setPermissions($info);
						if($p->getMaskID() == 1) {
							try {
								throw new OSIException(2,"Administrator's Mask","This mask is the built-in system administrators permissions and cannot be deleted.");
							} catch (OSIException $e) {
								print($e);
								$this->doAction("browseMasks");
								break;
							}
						}
						if($this->dbi->query($p->generateUpdateSQL())) {
							print($this->createSuccessMessage("Updated priviledges for '".$p->getMaskName()."'."));
						} else {
							try {
								throw new OSIException(3,"Permissions Error","Unable to update permissions mask.");
							} catch (OSIException $e) {
								print($e);
								$_GET['MaskID'] = $_POST['maskid'];
								$this->doAction("editMask");
								break;
							}
						}
					} else {
						try {
							throw new OSIException(3,"Permissions Error","Unable to update permissions mask.");
						} catch (OSIException $e) {
							print($e);
							$_GET['MaskID'] = $_POST['maskid'];
							$this->doAction("editMask");
							break;
						}
					}
					$this->doAction("browseMasks");
					break;

				case "deleteMask":
					$result = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID = ".$this->dbi->getDBI()->real_escape_string($_GET["MaskID"]));
					$p = new Permissions($result->fetch_object());
					if($p->getMaskID() == 1) {
						try {
							throw new OSIException(2,"Administrator's Mask","This mask is the built-in system administrators permissions and cannot be deleted.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseMasks");
							break;
						}
					}
					if($this->dbi->query($p->generateDeleteSQL())) {
						print($this->createSuccessMessage("Deleted permission mask '".$p->getMaskName()."'."));
					} else {
						try {
							throw new OSIException(3,"Permissions Error","Unable to delete this mask. The mask specified may no longer exist or it may still have user accounts associated with it.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseMasks");
							break;
						}
					}
					$this->doAction("browseMasks");
					break;

				case "browseMasks":
					print("<p>".$_SESSION['lastStatus']."</p>");
					$_SESSION['lastStatus'] = "";
					$formAction = $_SERVER['PHP_SELF']."?a=newMask";
					$permissions = array();
					$result = $this->dbi->query("SELECT * FROM OSIPermissions");
					while($p = $result->fetch_object()) {
						$permissions[$p->MaskID] = new Permissions($p);
						$c = $this->dbi->query("SELECT count(*) AS MemberCount FROM OSIUsers WHERE MaskID = ".$p->MaskID);
						$c = $c->fetch_object();
						$permissions[$p->MaskID]->setMemberCount($c->MemberCount);
					}
					print(str_replace("[FormAction]",$formAction,$this->viewer->showPermissions($permissions,$this->cp->parseView("PermissionsBrowse"),$this->cp->parseRowEntry("PermissionsEntry"))));
					break;
			}
		} else {
			throw new PermissionsException();
		}
	}

	/**
	 * Performs a manufacturer action.
	 *
	 * @param string A valid manufacturer action.
	 * @throws OSIException
	 * @throws DataValidationException
	 * @throws PermissionsException
	 * @throws BadFormInputException
	 */
	public function doManufacturerAction($action) {
		if(isset($_SESSION['user']['attrib']) && $_SESSION['user']['attrib']->getPermissions()->canManageColors()) {
			switch($action) {
				case "newManufacturer":
					$formAction = "?a=createManufacturer";
					$form = $this->cp->parseForm("CreateManufacturer");
					$form = str_replace("[FormAction]",$formAction,$form);
					print($form);
					break;

				case "createManufacturer":
					try {
						$info = $this->cp->processFormInput("CreateManufacturer");
					} catch (BadFormInputException $e) {
						print($e);
						$this->doAction("browseManufacturers");
						break;
					} catch (DataValidationException $e) {
						print($e);
						$this->doAction("browseManufacturers");
						break;
					}
					if(isset($info) && $info) {
						$m = new Manufacturer($info);
						$this->dbi->insertRecords(array($m));
						print($this->createSuccessMessage("Added manufacturer '".$m->getManufacturerName()."'."));
						$this->doAction("browseManufacturers");
					}
					break;

				case "editManufacturer":
					$formAction = $_SERVER['PHP_SELF']."?a=updateManufacturer";
					$result = $this->dbi->query("SELECT * FROM OSIManufacturers WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($_GET["ManufacturerID"]));
					$m = $result->fetch_object();
					try {
						if($m == null) {
							throw new OSIException(3,"Invalid Manufacturer Lookup","The specified manufacturer could not be found.");
						} else {
							$m = new Manufacturer($m);
							$form = $this->cp->parseForm("EditManufacturer");
							$form = str_replace("[FormAction]",$formAction,$form);
							$form = str_replace("[ManufacturerID]",$m->getManufacturerID(),$form);
							$form = str_replace("[ManufacturerName]",$m->getManufacturerName(),$form);
							print($form);
						}
					} catch (OSIException $e) {
						print($e);
						$this->doAction("browseManufacturers");
					}
					break;

				case "updateManufacturer":
					$info = $this->cp->processFormInput("EditManufacturer");
					if(isset($info) && $info) {
						$result = $this->dbi->query("SELECT * FROM OSIManufacturers WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($info[0]));
						$m = $result->fetch_object();
						try {
							if($m == null) {
								throw new OSIException(3,"Invalid Manufacturer Lookup","The specified manufacturer could not be found.");
							} else {
								$m = new Manufacturer($m);
								$m->setManufacturerName($info[1]);
								if($this->dbi->query($m->generateUpdateSQL())) {
									print($this->createSuccessMessage("Updated manufacturer details."));
								} else {
									try {
										throw new OSIException(3,"Update Cancelled","Unable to update manufacturer.");
									} catch (OSIException $e) {
										print($e);
									}
								}
								$this->doAction("browseManufacturers");
								break;
							}
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseManufacturers");
							break;
						}
					} else {
						try {
							throw new OSIException(3,"Update Cancelled","Unable to update manufacturer.");
						} catch (OSIException $e) {
							print($e);
						}
					}
					break;

				case "deleteManufacturer":
					$result = $this->dbi->query("SELECT * FROM OSIManufacturers WHERE ManufacturerID = ".$this->dbi->getDBI()->real_escape_string($_GET["ManufacturerID"]));
					if($m = $result->fetch_object()) {
						$m = new Manufacturer($m);
						if($this->dbi->query($m->generateDeleteSQL())) {
							print($this->createSuccessMessage("Deleted manufacturer '".$m->getManufacturerName()."'"));
						} else {
							try {
								throw new OSIException(3,"Delete Cancelled","Unable to delete manufacturer '".$m->getManufacturerName()."'.  The manufacturer may no longer exist or may have colors still associated with it.");
							} catch (OSIException $e) {
								print($e);
								$this->doAction("browseManufacturers");
								break;
							}
						}
						$this->doAction("browseManufacturers");
						break;
					} else {
						try {
							throw new OSIException(3,"Delete Cancelled","Unable to delete manufacturer '".$m->getManufacturerName()."'.  The manufacturer may no longer exist or may have colors still associated with it.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseManufacturers");
							break;
						}
					}
					break;

				case "browseManufacturers":
					$manufacturers = array();
					$formAction = "?a=createManufacturer";
					$result = $this->dbi->query("SELECT * FROM OSIManufacturers ORDER BY Name ASC");
					while($m = $result->fetch_object()) {
						$manufacturers[$m->ManufacturerID] = new Manufacturer($m);
					}
					$output = $this->viewer->showManufacturers($manufacturers,$this->cp->parseView("ManufacturerBrowse"),$this->cp->parseRowEntry("ManufacturerEntry"));
					$output = str_replace("[FormAction]",$formAction,$output);
					print($output);
					break;
			}
		} else {
			throw new PermissionsException();
		}
	}

	/**
	 * Performs a user action.
	 *
	 * @param string A valid user action.
	 * @throws OSIException
	 * @throws DataValidationException
	 * @throws PermissionsException
	 * @throws BadFormInputException
	 */
	public function doUserAction($action) {
		if(isset($_SESSION['user']['attrib']) && $_SESSION['user']['attrib']->getPermissions()->canManageUsers()) {
			switch($action) {
				case "newUser":
					$formAction = $_SERVER['PHP_SELF']."?a=createUser";
					$output = str_replace("[PermissionMasksList]",$this->generatePermissionsList(),$this->cp->parseForm("CreateUser"));
					print(str_replace("[FormAction]",$formAction,$output));
					break;

				case "createUser":
					try {
						$info = $this->cp->processFormInput("CreateUser");
					} catch (OSIException $e) {
						print($e);
						$this->doAction("newUser");
						break;
					} catch (DataValidationException $e) {
						print($e);
						$this->doAction("newUser");
						break;
					} catch (BadFormInputException $e) {
						print($e);
						$this->doAction("newUser");
						break;
					}
					if(isset($info) && $info) {
						$p = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID=".$this->dbi->getDBI()->real_escape_string($this->dbi->getDBI()->real_escape_string($info[4])));
						$p = $p->fetch_object();

						$p = new Permissions($p);
						$u = new User($info,$p);
						$u->encryptPassword($u->getFingerprint());
						$this->dbi->insertRecords(array($u));
						print($this->createSuccessMessage("Created user '".$u->getUsername()."'."));
						$this->doAction("browseUsers");
					}
					break;

				case "editUser":
					$formAction = $_SERVER['PHP_SELF']."?a=updateUser";
					$form = $this->cp->parseForm("EditUser");
					$u = $this->dbi->query("SELECT * FROM OSIUsers WHERE UserID = ".$this->dbi->getDBI()->real_escape_string($_GET["UserID"]));
					$u = $u->fetch_object();
					try {
						if($u == null) {
							throw new OSIException(2,"Invalid User Lookup","The user account you are attempting to access does not exist.");
						}
					} catch (OSIException $e) {
						print($e);
						$this->doAction("browseUsers");
						break;
					}
					$p = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID =".$this->dbi->getDBI()->real_escape_string($u->MaskID));
					$p = new Permissions($p->fetch_object());
					$u = new User($u,$p);
					if($u->getUserID() == 1) {
						try {
							throw new OSIException(2,"Administrator Account","The system administrator account is built-in and cannot be modified.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseUsers");
							break;
						}
					}
					$masks = $this->generatePermissionsList();
					$masks = str_replace('<option value="'.$u->getPermissions()->getMaskID().'">','<option value="'.$u->getPermissions()->getMaskID().'" selected="selected">',$masks);
					$form = str_replace("[UserID]",$u->getUserID(),$form);
					$form = str_replace("[FormAction]",$formAction,$form);
					$form = str_replace("[Username]",$u->getUsername(),$form);
					$form = str_replace("[PermissionMasksList]",$masks,$form);
					if($u->getStatus()) {
						$form = str_replace('id="accountActive"','id="AccountActive" checked="checked"',$form);
					} else {
						$form = str_replace('id="accountDisabled"','id="AccountDisabled" checked="checked"',$form);
					}
					print($form);
					break;

				case "deleteUser":
					$u = $this->dbi->query("SELECT * FROM OSIUsers WHERE UserID = ".$this->dbi->getDBI()->real_escape_string($_GET["UserID"]));
					$u = $u->fetch_object();
					try {
						if($u == null) {
							throw new OSIException(2,"Invalid User Lookup","The user account you are attempting to access does not exist.");
						}
					} catch (OSIException $e) {
						print($e);
						$this->doAction("browseUsers");
						break;
					}
					$p = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID =".$this->dbi->getDBI()->real_escape_string($u->MaskID));
					$p = new Permissions($p->fetch_object());
					$u = new User($u,$p);
					if($u->getUserID() == 1) {
						try {
							throw new OSIException(2,"Administrator Account","The system administrator account is built-in and cannot be deleted.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseUsers");
							break;
						}
					}
					if($this->dbi->query($u->generateDeleteSQL())) {
						print($this->createSuccessMessage("Deleted user account '".$u->getUsername()."'."));
					} else {
						throw new OSIException(2,"Delete Error","Unable to delete user account.  The user may no longer exist or may still be associated with colors.  If you are attempting to prevent system access for this user it is recommended to disable the account instead.");
					}
					$this->doAction("browseUsers");
					break;

				case "updateUser":
					$info = $this->cp->processFormInput("EditUser");
					$u = $this->dbi->query("SELECT * FROM OSIUsers WHERE UserID = ".$this->dbi->getDBI()->real_escape_string($info[0]));
					$u = $u->fetch_object();
					try {
						if($u == null) {
							throw new OSIException(2,"Invalid User Lookup","The user account you are attempting to access does not exist.");
						}
					} catch (OSIException $e) {
						print($e);
						$this->doAction("browseUsers");
						break;
					}
					$p = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID =".$this->dbi->getDBI()->real_escape_string($info[2]));
					$p = new Permissions($p->fetch_object());
					$u = new User($u,$p);
					$u->setStatus($info[1]);
					if($u->getUserID() == 1) {
						try {
							throw new OSIException(2,"Administrator Account","The system administrator account is built-in and cannot be modified.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("browseUsers");
							break;
						}
					}
					if($this->dbi->query($u->generateUpdateSQL())) {
						print($this->createSuccessMessage("Updated details for user '".$u->getUsername()."'."));
					} else {
						throw new OSIException(3,"Update Error","Unable to update user account.");
					}
					$this->doAction("browseUsers");
					break;

				case "browseUsers":
					$formAction = "?a=newUser";
					$users = array();
					$permissions = array();
					$result = $this->dbi->query("SELECT * FROM OSIPermissions");
					while($p = $result->fetch_object()) {
						$permissions[$p->MaskID] = new Permissions($p);
					}
					$result = $this->dbi->query("SELECT * FROM OSIUsers");
					while($u = $result->fetch_object()) {
						foreach($permissions as $p) {
							if($u->MaskID == $p->getMaskID()) {
								$users[$u->UserID] = new User($u,$p);
							}
						}
					}
					$output = $this->viewer->showUsers($users,$this->cp->parseView("UserBrowse"),$this->cp->parseRowEntry("UserEntry"));
					$output = str_replace("[FormAction]",$formAction,$output);
					print($output);
					break;
			}
		} else {
			throw new PermissionsException();
		}
	}
	/**
	 * Performs miscellaneous actions.
	 *
	 * @param string A valid action.
	 * @throws PermissionsException
	 */
	public function doLimitedCheckAction($action) {
		switch($action) {
			case "exportInventory":
				$csi = $this->dbi->query("SELECT SealantID FROM OSISealants WHERE LocalStock = TRUE ORDER BY SealantID ASC");
				$vendor = $this->dbi->query("SELECT SealantID FROM OSISealants WHERE VendorStock = TRUE ORDER BY SealantID ASC");
				$osi = $this->dbi->query("SELECT SealantID FROM OSISealants ORDER BY SealantID ASC");
				$this->data->createMasterSealantFile($csi,$vendor,$osi);
				break;

			case "changePassword":
				if($_SESSION['user']['attrib']->getPermissions()->canManageUsers() || $_GET["UserID"] == $_SESSION['user']['attrib']->getUserID()) {
					$formAction = $_SERVER['PHP_SELF']."?a=resetPassword";
					$form = $this->cp->parseForm("ResetPassword");
					$u = $this->dbi->query("SELECT * FROM OSIUsers WHERE UserID = ".$_GET["UserID"]);
					$u = $u->fetch_object();
					try {
						if($u == null) {
							throw new OSIException(2,"Invalid User","The specified user does not exist in the database");
						} else {
							$p = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID =".$u->MaskID);
							$p = new Permissions($p->fetch_object());
							$u = new User($u,$p);
							$form = str_replace("[FormAction]",$formAction,$form);
							$form = str_replace("[UserID]",$u->getUserID(),$form);
							$form = str_replace("[Username]",$u->getUsername(),$form);
							print($form);
						}
					} catch (OSIException $e) {
						print($e);
						$this->doAction("default");
						break;
					}
				} else {
					throw new PermissionsException(2,"","Your permissions only allow you to change your own password.");
				}
				break;

			case "resetPassword":
				try {
					$info = $this->cp->processFormInput("ResetPassword");
				} catch (OSIException $e) {
					print($e);
					$this->doAction("browseUsers");
					break;
				} catch (BadFormInputException $e) {
					print($e);
					$this->doAction("browseUsers");
					break;
				} catch (DataValidationException $e) {
					print($e);
					$this->doAction("browseUsers");
					break;
				}
				if($_SESSION['user']['attrib']->getPermissions()->canManageUsers() || $info[0] == $_SESSION['user']['attrib']->getUserID()) {
					$u = $this->dbi->query("SELECT * FROM OSIUsers WHERE UserID = ".$this->dbi->getDBI()->real_escape_string($info[0]));
					$u = $u->fetch_object();
					try {
						if($u == null) {
							throw new OSIException(2,"Invalid User Lookup","The ID number of the user whose password you want to reset does not exist.");
						}
					} catch (OSIException $e) {
						print($e);
						if($_SESSION['user']['attrib']->getPermissions()->canManageUsers()) {
							$this->doAction("browseUsers");
						} else {
							$this->doAction("default");
						}
						break;
					}
					$p = $this->dbi->query("SELECT * FROM OSIPermissions WHERE MaskID = ".$this->dbi->getDBI()->real_escape_string($u->MaskID));
					$p = new Permissions($p->fetch_object());
					$u = new User($u,$p);
					$this->dbi->query($u->resetPassword($info[1]));
					if($info[0] == $_SESSION['user']['attrib']->getUserID()) {
						print($this->createSuccessMessage("Your password has been reset."));
					} else {
						print($this->createSuccessMessage("Password was reset for user '".$u->getUsername()."'."));
					}
					if($_SESSION['user']['attrib']->getPermissions()->canManageUsers()) {
						$this->doAction("browseUsers");
					} else {
						$this->doAction("default");
					}
				} else {
					throw new PermissionsException(2,"","Your permissions only allow you to change your own password.");
				}
				break;

			case "login":
				print(str_replace("[FormAction]","?a=authenticate",$this->cp->parseForm("UserLogin")));
				break;

			case "authenticate":
				try {
					$info = $this->cp->processFormInput("UserLogin");
				} catch (DataValidationException $e) {
					print($e);
					$this->doAction("login");
					break;
				}
				if(isset($info) && $info) {
					$result = $this->dbi->query("SELECT * FROM OSIUsers LEFT JOIN OSIPermissions ON OSIPermissions.MaskID = OSIUsers.MaskID WHERE OSIUsers.Username = '".$this->dbi->getDBI()->real_escape_string($info[0])."'");
					$r = $result->fetch_object();
					if($r != null) { //User found
						$u = array($r->UserID,$r->Username,$r->Fingerprint,$r->Status);
						$p = array($r->MaskName,$r->ManageUsers,$r->ManageColors,$r->ManageInventory,$r->ManageOptions,$r->ManageDatabase,$r->BrowseColors);
						$p = new Permissions($p);
						$p->setMaskID($r->MaskID);
						$user = new User($u,$p);
						if($user->challengePassword($info[1])) {
							$_SESSION['user']['attrib'] = $user;
							try {
								if($_SESSION['user']['attrib']->getStatus() == false) {
									throw new OSIException(2,"Unauthorized Access","Your account has been disabled. Contact an administrator to have your account status reinstated.");
								}
							} catch (OSIException $e) {
								print($e);
								$this->doAction("login");
								break;
							}
							$_SESSION['user']['authenticated'] = true;
							//User is now logged in at this point
							print('<h3 style="text-align: center;">Loading....</h3>');
							print('<script language="javascript" type="text/javascript">window.location=\''.$_SERVER['PHP_SELF'].'?a=default\';</script>;');
						} else {
							$_SESSION['user']['authenticated'] = false;
							try {
								throw new OSIException(3,"Incorrect Password","You password is incorrect, please try again.");
							} catch (OSIException $e) {
								print($e);
								$this->doAction("login");
								break;
							}
						}
					} else {
						try {
							throw new OSIException(3,"Unknown User","The specified username does not exist, please try again.");
						} catch (OSIException $e) {
							print($e);
							$this->doAction("login");
							break;
						}
					}
				}
				break;

			case "logout":
				foreach($_SESSION as &$s) {
					$s = null;
					unset($s);
				}
				print($this->createSuccessMessage("You have been logged out of the system.","Logout"));
				$this->doAction("login");
				break;

			case "purgeTempFiles":
				//Removes files from TMPDIR that are more than 24 hours old
				$d = opendir(TMPDIR);
				while(false !== ($f = readdir($d))) {
					$f = TMPDIR.$f;
					if(is_file($f)) {
						if((time() - fileatime($f)) > (24 * 3600)) {
							unlink($f);
						}
					}
					if(is_dir($f) && $f !== TMPDIR."." && $f !== TMPDIR."..") {
						$e = opendir($f);
						while(false !== ($g = readdir($e))) {
							$g = $f."/".$g;
							print($g."\n");
							if(is_file($g)) {
								unlink($g);
							}
						}
						rmdir($f);
					}
				}
				break;

			case "changelog":
				echo '<div class="chlog">';
				include(INCLUDES."changelog.html");
				echo '</div>';
				break;

			case "default": default:
				$view = $this->cp->parseView("SplashPage");
				$view = str_replace("[Username]",$_SESSION['user']['attrib']->getUsername(),$view);
				$view = str_replace("[UserManual]","<span><a href=\"".DATA."OSICRM_UserManual.pdf\">Download User Manual</a></span><p>The user manual contains instructions for general usage of the software for all users. </p><hr />",$view);
				if($_SESSION['user']['attrib']->getPermissions()->canManageInventory()) {
					$view = str_replace("[BlankOSIFile]","<span><a href=\"".DATA."SealantsImport_BlankFile.csv\">Download an Empty OSI File</a></span><p>This file is an empty .CSV file setup to be interpreted correctly by the system when editing the entire catalog of sealants all at once. Consult the user manual for more information.</p><hr />",$view);
				} else {
					$view = str_replace("[BlankOSIFile]","",$view);
				}
				if($_SESSION['user']['attrib']->getPermissions()->canManageColors()) {
					$view = str_replace("[BlankColorsFile]","<span><a href=\"".DATA."ColorsImport_BlankFile.csv\">Download an Empty Colors File</a></span><p>Uploading mulitple colors at once is a powerful feature of the software that can add new manufactures and add or update colors in the database all on the fly.  This empty .CSV file is setup to be filled with color information before being sent to the server.  Consult the user manual for more information.</p><hr />",$view);
				} else {
					$view = str_replace("[BlankColorsFile]","",$view);
				}
				print($view);
		}
	}
}

?>