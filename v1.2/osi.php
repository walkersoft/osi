<?php

//SET THE SYSTEM VERSION
//This is the version number that will be displayed on the website
//and in HTML export files.
define("OSI_VERSION","1.2-DEVELOPMENT");

//SET IF THE OS THE SERVER IS RUNNING IS WINDOWS
//Session path slashes must be reversed from Linus style slashes.
//Default for production environment: false
define("WINDOWS",true);

//SET SESSION DIRECTORY
//Change this path to match where the session directory is located
//relative to DOCUMENT_ROOT
//Default for production environment: /osi/sessions
define("SESSIONS","/osi/latest/osi/sessions");

//DEFINE SOME DIRECTORY LOCATIONS
define("OSI","osi/");
define("LIBRARY",OSI."lib/");
define("DATA",OSI."data/");
define("INCLUDES",OSI."includes/");
define("BACKUPS",OSI."backups/");
define("TMPDIR",OSI."tmp/");

// LOAD THE LIBRARY
require_once(LIBRARY."Exceptions.php");
require_once(LIBRARY."Color.php");
require_once(LIBRARY."User.php");
require_once(LIBRARY."Permissions.php");
require_once(LIBRARY."Sealant.php");
require_once(LIBRARY."Manufacturer.php");
require_once(LIBRARY."CPanel.php");
require_once(LIBRARY."DataAgent.php");
require_once(LIBRARY."OSICore.php");
require_once(LIBRARY."Database.php");
require_once(LIBRARY."ViewAgent.php");
require_once(LIBRARY."SearchAgent.php");

//START A SESSION
$sessionpath = $_SERVER['DOCUMENT_ROOT'].SESSIONS;
if(WINDOWS) {
	$sessionpath = str_replace("/","\\",$sessionpath);
}
session_save_path($sessionpath);
ini_set('session.gc_maxlifetime',4*60*60);
ini_set('session.gc_probability',1);
ini_set('session.gc_divisor',100);
ini_set('session.cookie_lifetime',0);
session_start();
if(!isset($_SESSION['lastStatus'])) $_SESSION['lastStatus'] = "";

// SET THE TIMEZONE
date_default_timezone_set("America/Chicago");

// CREATE THE CORE
$osi = new OSICore();

//Run a few tests and set some variables based on those tests

//Test - Is the action variable set
$actionSet = false;
if(isset($_GET['a']) && $_GET['a'] != "default") {
	$actionSet = true;
}

//Test - Does the database file exist
$databaseConfigFound = false;
if(file_exists(DATA."database.ini")) {
	$databaseConfigFound = true;
}

//Test - Is the installation lock in place
$installationLocked = false;
if(file_exists(DATA."install.lock.php")) {
	$installationLocked = true;
}

//Test - Is the user requesting to install the software
$attemptingInstall = false;
if(isset($_GET['a']) && $_GET['a'] == "install") {
	$attemptingInstall = true;
}

//Test - Is the user requesting to logout
$attemptingLogout = false;
if(isset($_GET['a']) && $_GET['a'] == "logout") {
	$attemptingLogout = true;
}

//Test - Is the user logging in (attempting authentication)
$authenticatingUser = false;
if(isset($_GET['a']) &&  $_GET['a'] == "authenticate") {
	$authenticatingUser = true;
}

//Test - Is the user already logged in
$userAuthenticated = false;
if(isset($_SESSION['user']['authenticated']) && $_SESSION['user']['authenticated'] == true) {
	$userAuthenticated = true;
}

//Test - Is a database about to be imported
$importingDatabase = false;
if(isset($_GET['a']) && $_GET['a'] == "importDB") {
	$importingDatabase = true;
}

//Test - Is the user logging out
$loggingOut = false;
if(isset($_GET['a']) && $_GET['a'] == "logout") {
	$loggingOut = true;
}

//START BROWSER OUTPUT

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=8" />
	<title>Cedar Siding Inc. - OSI Color Reference Manager</title>
	<script type="text/javascript" src="osi/includes/osi.js"></script>
	<script type="text/javascript" src="osi/includes/tableSort.js"></script>
	<link rel="stylesheet" type="text/css" href="osi/includes/OSIStyles.css" media="all" />
</head>
<body>

<div id="wrapper">

	<div id="header">
	<a href="#"></a>
	</div>


	<div id="navbar">


		<?php
		//Create the nav bar if a user session has been established and the user is logged in.
		if($userAuthenticated && !$loggingOut) {
			print($_SESSION['user']['attrib']->generateUserNavigation());
		}
		?>


	</div>

	<div id="content">
		<?php
		//Look for an action, got to have an action to do anything, set it to default if it isn't set.
		if(!$actionSet) {
			$_GET['a'] = "default";
		}

		//Check to see if the system has been installed, if it isn't throw an exception and get the installer running
		if(!$databaseConfigFound && !$installationLocked && !$attemptingInstall) {
			try {
				throw new OSIException(1,"System Not Configured","The system appears to not have been installed. The installer may have run and did not complete properly, or you may have just now installed the files on your web server.  You will need to <a href=\"".$_SERVER['PHP_SELF']."?a=install\">run the installer</a> to continue.");
			} catch (OSIException $e) {
				print($e);
				exit();
			}
		}

		//Check to see if the database file has been removed, system cannot load without a database.ini file
		if(!$databaseConfigFound && !$attemptingInstall) {
			try {
				throw new OSIException(1,"Database Configuration Missing","The database file holding connection information cannot be found.  The system may have become corrupted.  Contact a system administrator to troubleshoot this error.");
			} catch (OSIException $e) {
				print($e);
				exit();
			}
		}

		//Attempt to connect to the database, this can only happen if the installer isn't being run OR if the installer is being run and the database configuration exists
		if($attemptingInstall && $databaseConfigFound) {
			try {
				$osi->connectDBI();
			} catch (OSIException $e) {
				print($e);
				exit();
			}
		}

		if(!$attemptingInstall) {
			try {
				$osi->connectDBI();
			} catch (OSIExcpetion $e) {
				print($e);
				exit();
			}
		}

		//Check for a couple different situations where the action mode need to be forced into a certain path
		// 1 - Is the user attempting to authenticate after submitting the login form
		// 2 - Is the installer still running
		// 3 - Is the database import feature trying to run
		// 4 - Is the user already authenticated and logged in
		// None of these situations check out then the login form should be shown
		if(!$userAuthenticated) {
			if($authenticatingUser) {
				$osi->doAction("authenticate");
			} else if($attemptingInstall) {
				try {
					$osi->doAction("install");
				}  catch (OSIException $e) {
					print($e);
				}
			} else if($importingDatabase) {
				try {
					$osi->doAction("importDB");
				} catch (OSIException $e) {
					print($e);
				}
			} else {
				$osi->doAction("login");
			}
		}

		//If all other situations did not apply, then it ***should*** safely be assumed that the user should be checked for authentication and then run whatever the action mode that was specified
		if($userAuthenticated) {
			try {
				$osi->doAction($_GET['a']);
			} catch (PermissionsException $e) {
				print($e);
			} catch (OSIException $e) {
				print($e);
			}
		}

		?>


	</div>

	<?php include(INCLUDES."footer.php"); ?>

