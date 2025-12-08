function attachSpoilerToggle($sel) {
	$sel.click(function(){
		$(this).toggleClass("expanded");
	});
}
