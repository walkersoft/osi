var threshold = 250;  //Pixel height to scroll to before activated link
var phaseSpeed = 600; //Speed to show/hide linkbox
var rightBuff = 70;   //Pixel amount for right hand buffer
var lowerBuff = 100;   //Pixel amount for lower buffer
var linkup = false;   //Boolean flag to show if linkbox is currently shown.
var linkbox = $('div#b2t');   //jquery selector for the divbox with link
var bwidth = linkbox.width();
var bheight = linkbox.height();

function calcBoxCoords()
{
	var cwidth = $(window).width();
	var cheight = $(window).height();
	return { "x" : cwidth - (bwidth + rightBuff),
	         "y" : cheight - (bheight + lowerBuff)
		   };
}

function checkThreshold()
{
	if($(window).scrollTop() > threshold) {
		if(!linkup) {
			linkup = true;
			showLink();
		}
		return true;
	} else {
		linkup = false;
		hideLink();
	}
	return false;
}

function showLink()
{
	var coords = calcBoxCoords();
	linkbox.hide();
	linkbox.css( { "left" : coords.x,
	               "top" : coords.y,
				   "position" : "fixed"
				 });
	linkbox.fadeIn(phaseSpeed);
}

function hideLink()
{
	linkbox.fadeOut(phaseSpeed);
}

$(window).scroll( function () {
	checkThreshold();
});

$(window).resize( function () {
	if(checkThreshold()) {
		showLink();
	}
});