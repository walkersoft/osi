<?php
/**
 * Search Agent Module
 *
 * Handles search input from the user and breaks down requests into keywords.  This module is
 * responsible for creating the different SQL queries to return results from the database after
 * a search.
 *
 * @author Jason L. Walker
 * @package OSI
 * @subpackage Modules
 */
 
class SearchAgent
{
	/**
	 * The search string from user input.
	 *
	 * @var string
	 */
	private $searchString;
	
	/**
	 * Array of keywords broken up from the <code>$searchString</code>
	 *
	 * @var array
	 */
	private $tokens;
	
	/**
	 * The mode the search engine is currently in.
	 *
	 * @var int
	 */
	private $searchMode;
	
	/**
	 * Simple flag to stop the search engine from generating query strings.
	 *
	 * @var bool
	 */
	private $searchStop;
	
	/**
	 * Array of values for additional WHERE clauses.
	 *
	 * @var array
	 */
	private $where;
	
	/**
	 * Constructor function.
	 *
	 * Creates a new Search Agent object.
	 *
	 * @param string A search value to construct queries with.
	 * @param boolean TRUE to use the advanced search engine, FALSE to use the basic search engine.
	 */
	public function __construct($search = "",$advanced = false) {
		$this->searchStop = false;
		if($search != "" && $search != null) {
			$this->searchString = $search;
			$this->tokens = array('all' => array(),'fts' => array(), 'like' => array());
			$this->searchMode = ($advanced == false ? 1 : 5);
		} else {
			$this->where = array();
			$this->searchMode = 5;
		}
	}
	
	/**
	 * Gets the <code>tokens</code> array.
	 *
	 * @return array Array of the keywords from the user's search string.
	 */
	public function getTokens() {
		return $this->tokens;
	}
	
	/**
	 * Gets the <code>searchString</code> from the user's input.
	 *
	 * @return string The user's search string from form input.
	 */
	public function getSearchString() {
		return $this->searchString;
	}
	
	/**
	 * Gets the mode of the search engine query builder that produced results.
	 *
	 * @return int The search mode that yielded results.
	 */
	public function getSearchMode() {
		return $this->searchMode;
	}
	
	/**
	 * Resets the mode of the basic or advanced search.
	 *
	 * @return void
	 */
	public function resetMode() {
		if($this->searchMode < 5) {
			$this->searchMode = 1;
		} else {
			$this->searchMode = 5;
		}
	}
	
	/**
	 * Parses the <code>searchString</code> into keywords usable by the fulltext search and the LIKE clause.
	 *
	 * @return void
	 */
	public function tokenize() {
		if($this->searchString != "" && $this->searchString != null) {
			$this->tokens['all'] = explode(" ",$this->searchString);
			foreach($this->tokens['all'] as &$t) {
				trim($t);
				if($t == "") {
					$t = null;
				} else {
					$result = (preg_match("/^[^\-\+\~\*]{4,}$/",$t) == true ? true : false);
					if($result) {
						$this->tokens['fts'][] = $t;
					} else {
						$this->tokens['like'][] = $t;
					}
				}
			}
		}
	}
	
	/**
	 * Breaks up critera from the advanced search form into arrays for WHERE clauses.
	 *
	 * @param array A set of additional criteria to include when creating the search queries.
	 */
	public function parseAdvancedSearchOptions($clauses) {
		$this->where = array();
		$this->tokens = array('all' => array(),'fts' => array(), 'like' => array());
		foreach($clauses as &$adv) {
			$adv = addslashes($adv);
		}
		if(isset($clauses['keywords'])) {
			$this->searchString = $clauses['keywords'];
			$this->tokenize();
		}
		if(isset($clauses['manufacturer'])) {
			$this->where['manufacturer'] = "OSIColors.ManufacturerID = ".$clauses['manufacturer'];
		}
		if(isset($clauses['match'])) {
			$this->where['match'] = "OSIColors.OSIMatch = '".$clauses['match']."'";
		}
		if(isset($clauses['mustcoat'])) {
			$this->where['mustcoat'] = "OSIColors.OSIMustCoat = '".$clauses['mustcoat']."'";
		}
		if(isset($clauses['lrv'])) {
			switch($clauses['lrvcriteria']) {
				case "lt":
					$this->where['lrvcriteria'] = " < ";
					break;
				
				case "gt":
					$this->where['lrvcriteria'] = " > ";
					break;
					
				case "eq": default:
					$this->where['lrvcriteria'] = " = ";
					break;
			}
			$this->where['lrv'] = "OSIColors.LRV".$this->where['lrvcriteria']."'".$clauses['lrv']."'";
		}
	}
	
	/**
	 * Builds SQL queries for the database to perform basic or advanced color searches.
	 *
	 * @param array Array of additional criteria for WHERE clauses.
	 * @return string The SQL statement to send to the database.
	 */
	public function buildSearchQuery($clauses = false) {
		$query = ($this->searchMode === false ? false : "");
		$tokens = "";
		$where = array();
		if($this->searchStop) {
			return false;
		}
		switch($this->searchMode) {
			case 1: case "FTS_WITH_BOOLEAN":
				foreach($this->tokens['fts'] as $t) {
					$t = addslashes($t);
					$t = "+$t ";
					$tokens .= $t;
				}
				$query = "SELECT MATCH(OSISearchColors.Name, OSISearchColors.Reference) AGAINST ('$tokens') AS Relevance, OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser WHERE MATCH(OSISearchColors.Name, OSISearchColors.Reference) AGAINST ('$tokens' IN BOOLEAN MODE) ORDER BY Relevance DESC";
				$this->searchMode++;
				break;
			
			case 2: case "FTS_NO_BOOLEAN_OPS":
				foreach($this->tokens['fts'] as $t) {
					$t = addslashes($t);
					$t = "$t* ";
					$tokens .= $t;
				}
				$query = "SELECT MATCH(OSISearchColors.Name, OSISearchColors.Reference) AGAINST ('$tokens') AS Relevance, OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser WHERE MATCH(OSISearchColors.Name, OSISearchColors.Reference) AGAINST ('$tokens' IN BOOLEAN MODE) ORDER BY Relevance DESC";
				$this->searchMode++;
				break;
				
			case 3: case "LIKE_RIGHT_WILDCARD":  //DEPRECATED
				/*$tokens = array();
				foreach($this->tokens['all'] as &$t) {
					if($t != "" && $t != null) {
						$t = addslashes($t);
						$t = "$t%";
						$tokens[0][] = "OSISearchColors.Name LIKE '$t'";
						$tokens[1][] = "OSISearchColors.Reference LIKE '$t'";
					}
				}
				if((isset($tokens[0]) && is_array($tokens[0])) || (isset($tokens[1]) && is_array($tokens[1]))) {
					if(count($tokens[0] > 0)) {
						$tokens1 = implode(" OR ",$tokens[0]);
					} else {
						$tokens1 = "";
					}
					if(count($tokens[0] > 0)) {
						$tokens2 = implode(" OR ",$tokens[1]);
					} else {
						$tokens2 = "";
					}
					if($tokens1 == "" || $tokens2 == "") {
						if($tokens1 != "") {
							$tokens = $tokens1;
						} else if($tokens2 != "") {
							$tokens = $tokens2;
						}
					} else {
						$tokens = $tokens1." OR ".$tokens2;
					}
					$query = "SELECT OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser WHERE $tokens ORDER BY OSIColors.Name ASC";
				}*/
				$query = "SELECT * FROM OSIColors WHERE ColorID = -1";
				$this->searchMode++;
				break;
				
			case 4: case "LIKE_BOTH_WILDCARD":  
				$tokens = array();
				foreach($this->tokens['all'] as &$t) {
					if($t != "" && $t != null) {
						$t = addslashes($t);
						$t = "%$t%";
						$tokens[0][] = "OSISearchColors.Name LIKE '$t'";
						$tokens[1][] = "OSISearchColors.Reference LIKE '$t'";
					}
				}
				if(((isset($tokens[0]) && is_array($tokens[0]))) || ((isset($tokens[1]) && is_array($tokens[1])))) {
					if(count($tokens[0] > 0)) {
						$tokens1 = implode(" OR ",$tokens[0]);
					} else {
						$tokens1 = "";
					}
					if(count($tokens[0] > 0)) {
						$tokens2 = implode(" OR ",$tokens[1]);
					} else {
						$tokens2 = "";
					}
					if($tokens1 == "" || $tokens2 == "") {
						if($tokens1 != "") {
							$tokens = $tokens1;
						} else if($tokens2 != "") {
							$tokens = $tokens2;
						}
					} else {
						$tokens = $tokens1." OR ".$tokens2;
					}
					$query = "SELECT OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser WHERE $tokens ORDER BY OSIColors.Name ASC";
				}
				//$this->searchMode++;
				$this->searchStop = true;
				break;
				
			case 5: case "ADVANCED_FTS_WITH_BOOLEAN":
				$this->searchStop = false;
				$this->parseAdvancedSearchOptions($clauses);
				$tokens = "";
				$relevance = "";
				$where = array();
				if(isset($this->tokens['fts']) && count($this->tokens['fts'] > 0)) {
					foreach($this->tokens['fts'] as $t) {
						$t = addslashes($t);
						$t = "+$t* ";
						$tokens .= $t;
					}
					$relevance = "MATCH(OSISearchColors.Name, OSISearchColors.Reference) AGAINST ('$tokens') AS Relevance,";
					$this->where['colors'] = "MATCH(OSISearchColors.Name, OSISearchColors.Reference) AGAINST ('$tokens' IN BOOLEAN MODE)";
				}
				$this->where['select'] = "SELECT $relevance OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser ";
				if($relevance != "") {
					$this->where['sort'] = " ORDER BY Relevance DESC";
				} else { 
					$this->where['sort'] = " ORDER BY OSIColors.Name ASC";
				}
				if(isset($this->where['colors'])) $where[] = $this->where['colors'];
				if(isset($this->where['manufacturer'])) $where[] = $this->where['manufacturer'];
				if(isset($this->where['match'])) $where[] = $this->where['match'];
				if(isset($this->where['mustcoat'])) $where[] = $this->where['mustcoat'];
				if(isset($this->where['lrv'])) $where[] = $this->where['lrv'];
				$query = $this->where['select'];
				if(count($where) > 0) {
					$query .= " WHERE ";
					$query .= implode(" AND ",$where);
					$query .= $this->where['sort'];
				} else {
					$query .= $this->where['sort'];
				}
				$this->searchMode++;
				break;
				
			case 6: case "ADVANCED_FTS_NO_BOOLEAN_OPS":
				$this->parseAdvancedSearchOptions($clauses);
				$tokens = "";
				$relevance = "";
				$where = array();
				if(isset($this->tokens['fts']) && count($this->tokens['fts'] > 0)) {
					foreach($this->tokens['fts'] as $t) {
						$t = addslashes($t);
						$t = "$t* ";
						$tokens .= $t;
					}
					$relevance = "MATCH(OSISearchColors.Name, OSISearchColors.Reference) AGAINST ('$tokens') AS Relevance,";
					$this->where['colors'] = "MATCH(OSISearchColors.Name, OSISearchColors.Reference) AGAINST ('$tokens' IN BOOLEAN MODE)";
				}
				$this->where['select'] = "SELECT $relevance OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser ";
				if($relevance != "") {
					$this->where['sort'] = " ORDER BY Relevance DESC";
				} else { 
					$this->where['sort'] = " ORDER BY OSIColors.Name ASC";
				}
				if(isset($this->where['colors'])) $where[] = $this->where['colors'];
				if(isset($this->where['manufacturer'])) $where[] = $this->where['manufacturer'];
				if(isset($this->where['match'])) $where[] = $this->where['match'];
				if(isset($this->where['mustcoat'])) $where[] = $this->where['mustcoat'];
				if(isset($this->where['lrv'])) $where[] = $this->where['lrv'];
				$query = $this->where['select'];
				if(count($where) > 0) {
					$query .= " WHERE ";
					$query .= implode(" AND ",$where);
					$query .= $this->where['sort'];
				} else {
					$query .= $this->where['sort'];
				}
				$this->searchMode++;
				break;
			
			//DEPRECATED
			case 7: case "ADVANCED_LIKE_RIGHT_WILDCARD_NAME_ONLY":  
				/*$this->parseAdvancedSearchOptions($clauses);
				$where = array();
				$tokens = array();
				foreach($this->tokens['all'] as &$t) {
					if($t != "" && $t != null) {
						$t = addslashes($t);
						$t = "$t%";
						$tokens[0][] = "OSISearchColors.Name LIKE '$t'";
						$tokens[1][] = "OSISearchColors.Reference LIKE '$t'";
					}
				}
				if((isset($tokens[0]) && is_array($tokens[0])) || (isset($tokens[1]) && is_array($tokens[1]))) {
					if(count($tokens[0] > 0)) {
						$tokens1 = implode(" OR ",$tokens[0]);
					} else {
						$tokens1 = "";
					}
					
					if($tokens1 == "") {
						if($tokens1 != "") {
							$this->where['colors'] = $tokens1;
						}
					} else {
						$this->where['colors'] = $tokens1." ";
					}
				}
				$this->where['select'] = "SELECT OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser";
				
				$this->where['sort'] = " ORDER BY OSIColors.Name ASC";
				if(isset($this->where['colors'])) $where[] = $this->where['colors'];
				if(isset($this->where['manufacturer'])) $where[] = $this->where['manufacturer'];
				if(isset($this->where['match'])) $where[] = $this->where['match'];
				if(isset($this->where['mustcoat'])) $where[] = $this->where['mustcoat'];
				if(isset($this->where['lrv'])) $where[] = $this->where['lrv'];
				$query = $this->where['select'];
				if(count($where) > 0) {
					$query .= " WHERE ";
					$query .= implode(" AND ",$where);
					$query .= $this->where['sort'];
				} else {
					$query .= $this->where['sort'];
				}*/
				$this->searchMode++;
				$query = "SELECT * FROM OSIColors WHERE ColorID = -1";
				break;
			
			//DEPRECATED
			case 8: case "ADVANCED_LIKE_RIGHT_WILDCARD_REFERENCE_ONLY":
				/*$this->parseAdvancedSearchOptions($clauses);
				$where = array();
				$tokens = array();
				foreach($this->tokens['all'] as &$t) {
					if($t != "" && $t != null) {
						$t = addslashes($t);
						$t = "$t%";
						$tokens[1][] = "OSISearchColors.Reference LIKE '$t'";
					}
				}
				if((isset($tokens[0]) && is_array($tokens[0])) || (isset($tokens[1]) && is_array($tokens[1]))) {
					if(count($tokens[1] > 0)) {
						$tokens2 = implode(" OR ",$tokens[1]);
					} else {
						$tokens2 = "";
					}
					if($tokens2 == "") {
						if($tokens2 != "") {
							$this->where['colors'] = $tokens2;
						}
					} else {
						$this->where['colors'] = $tokens2." ";
					}
				}
				$this->where['select'] = "SELECT OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser";
				$this->where['sort'] = " ORDER BY OSIColors.Name ASC";
				if(isset($this->where['colors'])) $where[] = $this->where['colors'];
				if(isset($this->where['manufacturer'])) $where[] = $this->where['manufacturer'];
				if(isset($this->where['match'])) $where[] = $this->where['match'];
				if(isset($this->where['mustcoat'])) $where[] = $this->where['mustcoat'];
				if(isset($this->where['lrv'])) $where[] = $this->where['lrv'];
				$query = $this->where['select'];
				if(count($where) > 0) {
					$query .= " WHERE ";
					$query .= implode(" AND ",$where);
					$query .= $this->where['sort'];
				} else {
					$query .= $this->where['sort'];
				}*/
				$this->searchMode++;
				$query = "SELECT * FROM OSIColors WHERE ColorID = -1";
				break;

			case 9: case "ADVANCED_LIKE_BOTH_WILDCARD_NAME_ONLY":
				$this->parseAdvancedSearchOptions($clauses);
				$where = array();
				$tokens = array();
				foreach($this->tokens['all'] as &$t) {
					if($t != "" && $t != null) {
						$t = addslashes($t);
						$t = "%$t%";
						$tokens[0][] = "OSISearchColors.Name LIKE '$t'";
						$tokens[1][] = "OSISearchColors.Reference LIKE '$t'";
					}
				}
				if((isset($tokens[0]) && is_array($tokens[0])) || (isset($tokens[1]) && is_array($tokens[1]))) {
					if(count($tokens[0] > 0)) {
						$tokens1 = implode(" OR ",$tokens[0]);
					} else {
						$tokens1 = "";
					}
					
					if($tokens1 == "") {
						if($tokens1 != "") {
							$this->where['colors'] = $tokens1;
						}
					} else {
						$this->where['colors'] = $tokens1." ";
					}
				}
				$this->where['select'] = "SELECT OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser";
				$this->where['sort'] = " ORDER BY OSIColors.Name ASC";
				if(isset($this->where['colors'])) $where[] = $this->where['colors'];
				if(isset($this->where['manufacturer'])) $where[] = $this->where['manufacturer'];
				if(isset($this->where['match'])) $where[] = $this->where['match'];
				if(isset($this->where['mustcoat'])) $where[] = $this->where['mustcoat'];
				if(isset($this->where['lrv'])) $where[] = $this->where['lrv'];
				$query = $this->where['select'];
				if(count($where) > 0) {
					$query .= " WHERE ";
					$query .= implode(" AND ",$where);
					$query .= $this->where['sort'];
				} else {
					$query .= $this->where['sort'];
				}
				$this->searchMode++;
				break;
				
			case 10: case "ADVANCED_LIKE_BOTH_WILDCARD_REFERENCE_ONLY":
				$this->parseAdvancedSearchOptions($clauses);
				$where = array();
				$tokens = array();
				foreach($this->tokens['all'] as &$t) {
					if($t != "" && $t != null) {
						$t = addslashes($t);
						$t = "%$t%";
						$tokens[1][] = "OSISearchColors.Reference LIKE '$t'";
					}
				}
				if((isset($tokens[0]) && is_array($tokens[0])) || (isset($tokens[1]) && is_array($tokens[1]))) {
					if(count($tokens[1] > 0)) {
						$tokens2 = implode(" OR ",$tokens[1]);
					} else {
						$tokens2 = "";
					}
					if($tokens2 == "") {
						if($tokens2 != "") {
							$this->where['colors'] = $tokens2;
						}
					} else {
						$this->where['colors'] = $tokens2." ";
					}
				}
				$this->where['select'] = "SELECT OSIColors.ColorID, OSIManufacturers.ManufacturerID, OSIColors.Name, OSIColors.Reference, OSIColors.OSIMatch, OSIColors.OSIMustCoat, OSIColors.LRV, OSIColors.LastAccessed, OSIUsers.UserID FROM OSISearchColors LEFT JOIN OSIColors ON OSIColors.ColorID = OSISearchColors.ColorID LEFT JOIN OSIManufacturers ON OSIManufacturers.ManufacturerID = OSIColors.ManufacturerID LEFT JOIN OSIUsers ON OSIUsers.UserID = OSIColors.LastAccessedUser";
				$this->where['sort'] = " ORDER BY OSIColors.Name ASC";
				if(isset($this->where['colors'])) $where[] = $this->where['colors'];
				if(isset($this->where['manufacturer'])) $where[] = $this->where['manufacturer'];
				if(isset($this->where['match'])) $where[] = $this->where['match'];
				if(isset($this->where['mustcoat'])) $where[] = $this->where['mustcoat'];
				if(isset($this->where['lrv'])) $where[] = $this->where['lrv'];
				$query = $this->where['select'];
				if(count($where) > 0) {
					$query .= " WHERE ";
					$query .= implode(" AND ",$where);
					$query .= $this->where['sort'];
				} else {
					$query .= $this->where['sort'];
				}
				$this->searchMode = false;
				break;
			
			default:	
				$query = false;
				break;
		}
		return $query;
	}
	
}

?>