// OSI Javascript Support

function togglePermissions(target)	//Toggles the view of a permissions mask breakdown
{
	var t = null;
	for(i = 0; i < target.parentNode.childNodes.length; i++)
	{
		//Loop through selected node's children to find the class name needed
		if(target.parentNode.childNodes[i].className == "toggle")
		{
			t = target.parentNode.childNodes[i]; //Assign then change to 'block' or 'none'
			if(t.style.display == "none" || t.style.display == "")
			{
				t.style.display = "block";
				target.innerHTML = "(hide permissions)";
			} 
			else if(t.style.display == "block")
			{
				t.style.display = "none";
				target.innerHTML = "(show permissions)";
			}
		}
	}
}

function populateSealants(id,sealants) {
	textarea = document.getElementById(id);
	textarea.innerHTML = sealants;
}

function deleteItem(item,url) {
	c = false;
	switch(item)	{
		case "user":
			c = confirm("Are you sure you want to delete the user?");
			break;
		
		case "permission":
			c = confirm("Are you sure you want to delete the permission?");
			break;
		
		case "color":
			c = confirm("Are you sure you want to delete the color?");
			break;
		
		case "manufacturer":
			c = confirm("Are you sure you want to delete the manufacturer?");
			break;
	}
	if(c) {
		window.location = url;
	}
	return c;
}

function importNotice(location) {
	c = false;
	c = confirm("You are about to import a database file. This will overwrite ALL records currently in the database, including user accounts.  Once your database file has been validated the import process will begin.  This may take a few seconds up to a couple of minutes depending on the size of your database.  Would you like to continue?");
	if(c) {
		document.forms['databaseRecoveryForm'].elements['importButton'].disabled = true;
		document.forms['databaseRecoveryForm'].elements['formClear'].disabled = true;
		document.forms['databaseRecoveryForm'].submit();
		c = true;
	} else {
		c = false;
	}
	return c;
}