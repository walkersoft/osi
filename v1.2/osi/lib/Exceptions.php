<?php
/**
 * Custom Exceptions.
 *
 * Class definitions for custom exception handlers.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Exceptions
 */

class OSIException extends Exception {
	private $label;
	private $level;
	
	public function __construct($level, $label, $message, $code = 0, Exception $previous = null) {
		$this->label = $label;
		$this->level = $level;
		parent::__construct($message, $code, $previous);
	}

	public function __toString() {
		$bgc = "#ffffff";
		switch($this->level) {
			case 1: case "FATAL":
			$bgc = "#ff0000";
				break;
				
			case 2: case "WARNING":
			$bgc = "#ff8800";
				break;
				
			case 3: case "NOTICE":
			$bgc = "#ffff00";
				break;
				
			default: break;
		}
		$m = '<div style="border: solid 2px black; width: 500px; margin-left: auto; margin-right: auto;"><div style="background: '.$bgc.';"><span style="font-size: 16px; font-weight: bold;">'.$this->label.'</span></div><div style="background:#eeeeee;">'.$this->message.'</div></div><br />';
		return $m;
	}
}

class PermissionsException extends OSIException
{
	public function __construct($level = 0, $label = "", $message = "", $code = 0, Exception $previous = null) {
		if($level == 0) $level = 2;
		if($label == "") $label = "Access Denied";
		if($message == "") $message = "You do not have the necessary permissions to perform this action";
		parent::__construct($level, $label, $message, $code, $previous);
	}
}

class DataValidationException extends OSIException
{
	public function __construct($level = 0, $label = "", $message = "", $code = 0, Exception $previous = null) {
		if($level == 0) $level = 3;
		if($label == "") $label = "Invalid Data";
		if($message == "") $message = "A data field failed it's validation test.";
		parent::__construct($level, $label, $message, $code, $previous);
	}
}

class BadFormInputException extends OSIException
{
	public function __construct($level = 0, $label = "", $message = "", $code = 0, Exception $previous = null) {
		if($level == 0) $level = 3;
		if($label == "") $label = "Required Field";
		if($message == "") $message = "You have left a required field blank.";
		parent::__construct($level, $label, $message, $code, $previous);
	}
}

class FileException extends OSIException
{
	public function __construct($level = 0, $label = "", $message = "", $code = 0, Exception $previous = null) {
		if($level == 0) $level = 3;
		if($label == "") $label = "File Error";
		if($message == "") $message = "A filesystem error has occurred.";
		parent::__construct($level, $label, $message, $code, $previous);
	}
}

class DatabaseException extends OSIException
{
	public function __construct($level = 0, $label = "", $message = "", $code = 0, Exception $previous = null) {
		if($level == 0) $level = 1;
		if($label == "") $label = "Database Error";
		if($message == "") $message = "A database error has occurred.";
		parent::__construct($level, $label, $message, $code, $previous);
	}
}


?>