function makeTabs() {
	//When page loads...
	$(".tab_content").hide(); //Hide all content
	
	var child = 1;
	if (typeof tabpage == "number") {
		child = tabpage;
	}
	
	$("ul.tabs").each(function() { $("li:nth-child(" + child+ ")", this).addClass("active").show(); });
	$(".tab_container").each(function() { $(".tab_content:nth-child(" + child + ")", this).show(); });

	$("ul.tabs li").click(function() {
		if ($(this).hasClass("disabled")) {
			return false;
		}
		
		var activeTab = $(this).find("a").attr("href");
		console.log(activeTab);
		
		if (activeTab.startsWith('http')) return true;
		
		var otherclasses = $(this).parent().attr("class").replace("tabs", "").trimStart().replace(" ", ".");
		if (otherclasses.length > 0) otherclasses = "." + otherclasses;
		
		$("ul.tabs li").removeClass("active");
		$(this).addClass("active");
		
		$(".tab_container"+otherclasses+" .tab_content").hide();
		
		
		
		$(activeTab).show();
		return false;
	});
	
	if (location.hash.startsWith('#tab-')) {
		$("a[href=#"+location.hash.substr(5)+"]").trigger("click");
	}
}
