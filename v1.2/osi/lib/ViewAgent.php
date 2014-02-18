<?php
/**
 * Viewing Agent Module.
 *
 * The viewer handles the construction of a variety of HTML views used by the software.
 * These views eventually make their way to the web browser for the user to interact with.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Modules
 */
 
class ViewAgent
{
	/**
	 * Array of manufactures to use when creating HTML seletion elements.
	 *
	 * @var array
	 */
	private $manufacturers;
	
	/**
	 * Constructor function.
	 *
	 * Creates a new Viewer Agent object.
	 */
	public function __construct() {
		$this->manufacturers = array();
	}
	
	/**
	 * Takes a result set with manufacturer records from the database and populates the <code>$manufacturers</code> array.
	 *
	 * @param MySQL_Result Result set with manufacturer records in it.
	 */
	public function importManufacturers($list) {
		while($o = $list->fetch_object()) {
			$m = new Manufacturer($o);
			$this->manufacturers[$m->getManufacturerID()] = $m->getName();
		}
	}
	
	/**
	 * Scores a set of colors from the database and sorts them based on keyword relevance.
	 *
	 * @param array Array of color objects to sort.
	 * @param array Set of tokens that was used to produce the search query.
	 * @param int Search mode that yielded results in the search engine.
	 * @return array Array of sorted colors.
	 */
	public function sortColors($colors,$tokens,$mode) {
		$tok = false;
		//Step 1: Clean out the tokens that are not found in any of the color records
		switch($mode) {	
			//FTS modes
			case 1: case 2: case 5: case 6:
				$tok = $tokens['fts'];
				$j = count($tok);
				for($i = 0; $i < $j; $i++) {
					foreach($tok as $k => $t) {
						$tokFound = false;
						$t = preg_quote($t,"/");
						$test = "/^.{0,}(".$t.").{0,}$/";
						foreach($colors as $c) {
							if(preg_match($test,$c->getName()) === true || preg_match($test,$c->getReference() === true)) {
								$tokFound = true;
							}
						}
						if(!$tokFound) {
							$t[$k] = null;
						}
					}
				}
				break;	
				
			//LIKE modes
			case 3: case 4: case 7: case 8: case 9: case 10:
				$tok = $tokens['like'];
				$j = count($tok);
				for($i = 0; $i < $j; $i++) {
					foreach($tok as $k => $t) {
						$tokFound = false;
						$t = preg_quote($t,"/");
						$test = "/^.{0,}(".$t.").{0,}$/";
						foreach($colors as $c) {
							if(preg_match($test,$c->getName()) === true || preg_match($test,$c->getReference() === true)) {
								$tokFound = true;
							}
						}
						if(!$tokFound) {
							$t[$k] = null;
						}
					}
				}
				break;			
		}
		//Step 2: Create "tiers" for different matches to go into.
		$matches = array();
		$matches['best'] = array();
		$matches['similar'] = array();
		$matches['other'] = array();
		
		//Step 3: Cycle through the incoming $colors array looking for
		//records that match the "best" criteria. "Best" is any record
		//whose name is an exact match (case-insensitive) to the keywords
		foreach($colors as $k => &$c) {
			if(strcasecmp($c->getName(),implode(" ",$tok)) == 0) {
				if($c instanceof Color) {
					$matches['best'][] = $c;
				}
				$c = null;
			}
		}
		
		//Step 4: Cycle through the incoming $colors array scoring records
		//based on similarity to the search strings
		$keywords = array();
		$searchTerms = array();
		$similar = false;
		$tcount = count($tok);
		$points = $tcount;
		
		//Create an array for $searchTerms with scoring and position information
		for($i = 0; $i < $tcount; $i++) {
			if($tok[$i] != null) {
				$searchTerms[] = array("keyword" => $tok[$i],"position" => $i,"score" => $points,"occured" => false);
			}
			$points--;
		}
		
		//Cycle through the colors and analyze keywords, score by better keyword results.
		$similar = array();
		foreach($colors as $k => &$c) {
			$score = 0;
			if($c != null) {
				$keywords = explode(" ",$c->getName());
				//Keyword Analysis
				foreach($keywords as $key => $word) {
					foreach($searchTerms as $term) {
						if(strcasecmp($term['keyword'],$word) == 0) {
							if($key <= $term['position']) {
								if(!$term['occured']) {
									$score += $term['score'];
								} else {
									$score -= .2;
								}
							} else {
								if(!$term['occured']) {
									$score += $term['score'] / 2;
								} else {
									$score -= .2;
								}
							}
							$term['occured'] = true;
						}
					}
				}
			}
			if($c instanceof Color) {
				$similar[] = array("record" => $c,"score" => $score);
			}
			$c = null;
		}
		
		//Step 5: Sort records in $similar based on their score, the higher
		//the score, the higher they place.
		$tcount = count($similar);
		$current = "";
		$next = "";
		$temp = "";
		if($tcount > 1) {
			for($i = 0; $i < $tcount; $i++) {
				for($j = 0; $j < $tcount; $j++) {
					$current = $similar[$j];
					if(isset($similar[$j+1])) {
						$next = $similar[$j+1];
					} else {
						$next = $current;
					}
					if($current['score'] < $next['score']) {
						$temp = $current;
						$similar[$j] = $next;
						$similar[$j+1] = $temp;
					}
				}
			}
		}
		
		//Finished - merge arrays and return
		foreach($similar as $s) {
			$matches['similar'][] = $s['record'];
		}
		$colors = array_merge($matches['best'],$matches['similar']);
		return $colors;
	}
	
	/**
	 * Creates display HTML for the various color views.
	 *
	 * @param array Array of color objects to display.
	 * @param string HTML code for the view.
	 * @param string HTML code for the table row.
	 * @param string The viewing mode. Determines what HTML code to include.
	 * @return string The HTML to be sent to the browser.
	 */
	public function showColors($colors,$view,$row,$mode="tableView") {
		$output = "";
		foreach($colors as $c) {
			$r = $row;
			switch($mode) {
				case "tableView":
					$r = str_replace("[ManufacturerName]",$c->getManufacturer()->getManufacturerName(),$r);
					$r = str_replace("[ColorName]",$c->getName(),$r);
					$r = str_replace("[ColorReference]",$c->getReference(),$r);
					if(!$c->getOSIMatch()->getLocalStock() && !$c->getOSIMatch()->getVendorStock()) {
						$r = str_replace("[OSIMatch]","<span style=\"color: #ff0000;\" title=\"Sealant must be ordered from Henkel\">".$c->getOSIMatch()->getSealantID()."</span>",$r);
					} else if(!$c->getOSIMatch()->getLocalStock() && $c->getOSIMatch()->getVendorStock()) {
						$r = str_replace("[OSIMatch]","<span style=\"color: #6a5acd;\" title=\"Sealant must be ordered from Woolf\">".$c->getOSIMatch()->getSealantID()."</span>",$r);
					} else {
						$r = str_replace("[OSIMatch]","<span title=\"Sealant is stocked locally by CSI\">".$c->getOSIMatch()->getSealantID()."</span>",$r);
					}
					if(!$c->getOSIMustCoat()->getLocalStock() && !$c->getOSIMustCoat()->getVendorStock()) {
						$r = str_replace("[OSIMustCoat]","<span style=\"color: #ff0000;\" title=\"Sealant must be ordered from Henkel\">".$c->getOSIMustCoat()->getSealantID()."</span>",$r);
					} else if(!$c->getOSIMustCoat()->getLocalStock() && $c->getOSIMustCoat()->getVendorStock()) {
						$r = str_replace("[OSIMustCoat]","<span style=\"color: #6a5acd;\" title=\"Sealant must be ordered from Woolf\">".$c->getOSIMustCoat()->getSealantID()."</span>",$r);
					} else {
						$r = str_replace("[OSIMustCoat]","<span title=\"Sealant is stocked locally by CSI\">".$c->getOSIMustCoat()->getSealantID()."</span>",$r);
					}
					$r = str_replace("[LRV]",$c->getLRV(),$r);
					$r = str_replace("&","&amp;",$r);
					$output .= $r;
					break;
				
				case "categoryView":					
					$r = str_replace("[ColorName]",$c->getName(),$r);
					$r = str_replace("[ColorReference]",$c->getReference(),$r);
					if(!$c->getOSIMatch()->getLocalStock() && !$c->getOSIMatch()->getVendorStock()) {
						$r = str_replace("[OSIMatch]","<span style=\"color: #ff0000;\" title=\"Sealant must be ordered from Henkel\">".$c->getOSIMatch()->getSealantID()."</span>",$r);
					} else if(!$c->getOSIMatch()->getLocalStock() && $c->getOSIMatch()->getVendorStock()) {
						$r = str_replace("[OSIMatch]","<span style=\"color: #6a5acd;\" title=\"Sealant must be ordered from Woolf\">".$c->getOSIMatch()->getSealantID()."</span>",$r);
					} else {
						$r = str_replace("[OSIMatch]","<span title=\"Sealant is stocked locally by CSI\">".$c->getOSIMatch()->getSealantID()."</span>",$r);
					}
					if(!$c->getOSIMustCoat()->getLocalStock() && !$c->getOSIMustCoat()->getVendorStock()) {
						$r = str_replace("[OSIMustCoat]","<span style=\"color: #ff0000;\" title=\"Sealant must be ordered from Henkel\">".$c->getOSIMustCoat()->getSealantID()."</span>",$r);
					} else if(!$c->getOSIMustCoat()->getLocalStock() && $c->getOSIMustCoat()->getVendorStock()) {
						$r = str_replace("[OSIMustCoat]","<span style=\"color: #6a5acd;\" title=\"Sealant must be ordered from Woolf\">".$c->getOSIMustCoat()->getSealantID()."</span>",$r);
					} else {
						$r = str_replace("[OSIMustCoat]","<span title=\"Sealant is stocked locally by CSI\">".$c->getOSIMustCoat()->getSealantID()."</span>",$r);
					}
					$r = str_replace("[LRV]",$c->getLRV(),$r);
					$r = str_replace("&","&amp;",$r);
					$output .= $r;
					break;
				
				case "tableEdit":
					$atime = new DateTime($c->getLastAccessedTime());
					$alist = '<a href="'.$_SERVER['PHP_SELF'].'?a=editColor&amp;ColorID='.$c->getColorID().'">Edit</a> - <a href="#" onclick="deleteItem(\'color\',\''.$_SERVER['PHP_SELF'].'?a=deleteColor&amp;ColorID='.$c->getColorID().'\'); return false;">Delete</a>';
					$r = str_replace("[ManufacturerName]",$c->getManufacturer()->getManufacturerName(),$r);
					$r = str_replace("[ColorName]",$c->getName(),$r);
					$r = str_replace("[ColorReference]",$c->getReference(),$r);
					$r = str_replace("[LastAccess]",$atime->format("M j, Y g:iA"),$r);
					$r = str_replace("[LastUser]",$c->getLastAccessedUser(),$r);
					$r = str_replace("[ActionsList]",$alist,$r);
					//$r = str_replace("&","&amp;",$r);
					$output .= $r;					
					break;
					
				case "categoryEdit":
					$atime = new DateTime($c->getLastAccessedTime());
					$alist = '<a href="'.$_SERVER['PHP_SELF'].'?a=editColor&amp;ColorID='.$c->getColorID().'">Edit</a> - <a href="#" onclick="deleteItem(\'color\',\''.$_SERVER['PHP_SELF'].'?a=deleteColor&amp;ColorID='.$c->getColorID().'\'); return false;">Delete</a>';
					//$r = str_replace("[ManufacturerName]",$c->getManufacturer()->getManufacturerName(),$r);
					$r = str_replace("[ColorName]",$c->getName(),$r);
					$r = str_replace("[ColorReference]",$c->getReference(),$r);
					$r = str_replace("[LastAccess]",$atime->format("M j, Y g:iA"),$r);
					$r = str_replace("[LastUser]",$c->getLastAccessedUser(),$r);
					$r = str_replace("[ActionsList]",$alist,$r);
					//$r = str_replace("&","&amp;",$r);
					$output .= $r;	
					break;
			}
		}
		if($output != "") {
			switch($mode) {
				case "tableView":
					$output = str_replace("[Row:ColorViewTableEntry]",$output,$view);
					break;
				
				case "categoryView":					
					$output = str_replace("[Row:ColorViewBrowseEntry]",$output,$view);
					break;
					
				case "tableEdit":					
					$output = str_replace("[Row:ColorEditTableEntry]",$output,$view);
					break;
					
				case "categoryEdit":					
				$output = str_replace("[Row:ColorEditBrowseEntry]",$output,$view);
				break;
			}
		}
		return $output;
	}
	
	/**
	 * Creates display HTML for the permission mask view.
	 *
	 * @param array Array of permission mask objects to display.
	 * @param string HTML code for the view.
	 * @param string HTML code for the table row.
	 * @return string The HTML to be sent to the browser.
	 */
	public function showPermissions($permissions,$view,$row) {
		$output = "";
		foreach($permissions as $p) {
			$r = $row;
			$alist = '';
			if($p->getMaskID() == 1) {
				$alist = "N/A (Built-in Mask)";
			} else {
				$alist = '<a href="'.$_SERVER['PHP_SELF'].'?a=editMask&amp;MaskID='.$p->getMaskID().'">Edit</a> - <a href="#" onclick="deleteItem(\'permission\',\''.$_SERVER['PHP_SELF'].'?a=deleteMask&amp;MaskID='.$p->getMaskID().'\'); return false;">Delete</a>';
			}
			$perms = '<div class="toggle">';
			$perms .= ($p->canManageUsers() == true ? '<div class="perm">Manage Users</div><div class="setting">Allow</div>' : '<div class="perm">Manage Users</div><div class="setting">Deny</div>');
			$perms .= ($p->canManageColors() == true ? '<div class="perm">Manage Colors</div><div class="setting">Allow</div>' : '<div class="perm">Manage Colors</div><div class="setting">Deny</div>');
			$perms .= ($p->canManageInventory() == true ? '<div class="perm">Manage Inventory</div><div class="setting">Allow</div>' : '<div class="perm">Manage Inventory</div><div class="setting">Deny</div>');
			$perms .= ($p->canManageOptions() == true ? '<div class="perm">Manage Options</div><div class="setting">Allow</div>' : '<div class="perm">Manage Options</div><div class="setting">Deny</div>');
			$perms .= ($p->canManageDatabase() == true ? '<div class="perm">Manage Database</div><div class="setting">Allow</div>' : '<div class="perm">Manage Database</div><div class="setting">Deny</div>');
			$perms .= ($p->canBrowseColors() == true ? '<div class="perm">Browse Colors</div><div class="setting">Allow</div>' : '<div class="perm">Browse Colors</div><div class="setting">Deny</div>');
			$perms .= '</div>';
			$r = str_replace("[MaskToggle]",'<a href="" class="permToggle" onclick="togglePermissions(this); return false;">(show permissions)</a>',$r);
			$r = str_replace("[MaskPermissions]",$perms,$r);
			$r = str_replace("[MemberCount]",$p->getMemberCount(),$r);
			$r = str_replace("[MaskName]",$p->getMaskName(),$r);
			$r = str_replace("[ActionsList]",$alist,$r);
			$output .= $r;
		}
		return str_replace("[Row:PermissionsEntry]",$output,$view);
	}
	
	/**
	 * Creates display HTML for the manufacturer view.
	 *
	 * @param array Array of manufacturer objects to display.
	 * @param string HTML code for the view.
	 * @param string HTML code for the table row.
	 * @return string The HTML to be sent to the browser.
	 */
	public function showManufacturers($manufacturers,$view,$row) {
		$output = "";
		foreach($manufacturers as $m) {
			$r = $row;
			$alist = '<a href="'.$_SERVER['PHP_SELF'].'?a=editManufacturer&ManufacturerID='.$m->getManufacturerID().'">Edit</a> - <a href="#" onclick="deleteItem(\'manufacturer\',\''.$_SERVER['PHP_SELF'].'?a=deleteManufacturer&ManufacturerID='.$m->getManufacturerID().'\'); return false;">Delete</a>';
			$r = str_replace("[ManufacturerName]",$m->getManufacturerName(),$r);
			$r = str_replace("[ActionsList]",$alist,$r);
			$output .= $r;
		}
		return str_replace("[Row:ManufacturerEntry]",$output,$view);
	}
	
	/**
	 * Creates display HTML for the user view.
	 *
	 * @param array Array of user objects to display.
	 * @param string HTML code for the view.
	 * @param string HTML code for the table row.
	 * @param string The viewing mode. Determines what HTML code to include.
	 * @return string The HTML to be sent to the browser.
	 */
	public function showUsers($users,$view,$row) {
		$output = "";
		foreach($users as $u) {
			$r = $row;
			$alist = "";
			if($u->getUserID() == 1) {
				$alist = '<a href="'.$_SERVER['PHP_SELF'].'?a=changePassword&UserID='.$u->getUserID().'">Reset Password</a>';
			} else {
				$alist = '<a href="'.$_SERVER['PHP_SELF'].'?a=editUser&UserID='.$u->getUserID().'">Edit</a> - <a href="#" onclick="deleteItem(\'user\',\''.$_SERVER['PHP_SELF'].'?a=deleteUser&UserID='.$u->getUserID().'\'); return false;">Delete</a> - <a href="'.$_SERVER['PHP_SELF'].'?a=changePassword&UserID='.$u->getUserID().'">Reset Password</a>';
			}
			$status = ($u->getStatus() == true ? "Active" : "Disabled");
			$r = str_replace("[Username]",$u->getUsername(),$r);
			$r = str_replace("[MaskName]",$u->getPermissions()->getMaskName(),$r);
			$r = str_replace("[AcctStatus]",$status,$r);
			$r = str_replace("[ActionsList]",$alist,$r);
			$output .= $r;
		}
		return str_replace("[Row:UserEntry]",$output,$view);
	}
} 

?>