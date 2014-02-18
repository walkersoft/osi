var tableID = "sortable";

/**
 * Attach click events to all the <th> elements in a table to
 * call the tableSort() function. This function assumes that cells
 * in the first row in a table are <th> headers tags and that cells
 * in the remaining rows are <td> data tags.
 *
 * @param table The table element to work with.
 * @return void
 */
function initHeaders(table) {
	//Get the table element
	table = document.getElementById(table);
	//Get the number of cells in the header row
	var l = table.rows[0].cells.length;
	//Loop through the header cells and attach the events
	for(var i = 0; i < l; i++) {
		if(table.rows[0].cells[i].addEventListener) { //For modern browsers
			table.rows[0].cells[i].addEventListener("click", tableSort, false);
		} else if(table.rows[0].cells[i].attachEvent) { //IE specific method
			table.rows[0].cells[i].attachEvent("onclick", tableSort);
		}
	}
}


function tableSort(e) {

	var runs = 0;
	var ndx = 0;
	var dir = "right";

	//Get the element from the click event that was passed.
	if(e.currentTarget) { //For modern browsers
		e = e.currentTarget;
	} else if(window.event.srcElement) { //IE specific method
		e = window.event.srcElement;
	} else {
		console.log("Unable to determine source event. Terminating....");
		return false;
	}

	//Get the index of the cell, will be needed later
	ndx = e.cellIndex;

	//Toggle between "asc" and "desc" depending on element's id attribute
	if(e.id == "asc") {
		e.id = "desc";
	} else {
		e.id = "asc";
	}

	//Move up from the <th> that was clicked and find the parent table element.
	var parent = e.parentElement;
	var s = parent.tagName;
	while(s.toLowerCase() != "table") {
		parent = parent.parentElement;
		s = parent.tagName;
	}

	//Get the rows to operate on as an array
	var rows = document.getElementById("replace").rows;
	var a = new Array();
	for(i = 0; i < rows.length; i++) {
		a.push(rows[i]);
	}

	/**
	 * Checks to see if a value is numeric.
	 *
	 * @param n The incoming value to check.
	 * @return bool TRUE if value is numeric, FALSE otherwise.
	 */
	isNumeric = function (n) {
		var num = false;
		if(!isNaN(n) && isFinite(n)) {
			num = true;
		}
		return num;
	};

	/**
	 * Compares two values and determines which one is "bigger".
	 *
	 * @param x A reference value to check against.
	 * @param y The value to be determined bigger or smaller than the reference.
	 * @return TRUE if y is greater or equal to x, FALSE otherwise
	 */
	function compare(x, y) {
		var bigger = false;
		x = x.cells[ndx].textContent;
		y = y.cells[ndx].textContent;
		//console.log(e.id);
		if(isNumeric(x) && isNumeric(y)) {
			if(y >= x) {
				bigger = (e.id == "asc") ? true : false;
			} else {
				bigger = (e.id == "desc") ? true : false;
			}
		} else {
			if(y.localeCompare(x) >= 0) {
				bigger = (e.id == "asc") ? true : false;
			} else {
				bigger = (e.id == "desc") ? true : false;
			}
		}
		return bigger;
	}

	/**
	 * Performs a quicksort O(n log n) on an array.
	 *
	 * @param array The array that needs sorting
	 * @return array The sorted array.
	 */
	function nlognSort(array) {
		runs++
		if(array.length > 1) {
			var big = new Array();
			var small = new Array();
			var pivot = array.pop();
			var l = array.length;
			for(i = 0; i < l; i++) {
				if(compare(pivot,array[i])) {
					big.push(array[i]);
				} else {
					small.push(array[i]);
				}
			}
			return Array.prototype.concat(nlognSort(small), pivot, nlognSort(big));
		} else {
			return array;
		}
	}


	//Run sort routine
	b = nlognSort(a);

	//Rebuild <tbody> and replace new with the old
	var tbody = document.createElement("tbody");
	var l = b.length;
	for(i = 0; i < l; i++) {
		tbody.appendChild(b.shift());
	}
	parent.removeChild(document.getElementById("replace"));
	parent.appendChild(tbody);
	tbody.setAttribute("id","replace");
}


window.onload = function() {
	initHeaders(tableID);
}