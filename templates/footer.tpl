	<div style="clear:both;"></div>
{if !empty($buttons)}
	<div id="rightbuttons">
		<div id="buttonsinner">
			{$buttons}
		</div>
	</div>
{/if}

	</div>

</div>

	<script type="text/javascript" src="/web/js/jquery-1.11.1.min.js"></script>
	<script type="text/javascript" src="/web/js/chosen/chosen.jquery.min.js"></script>
	<script type="text/javascript" src="/web/js/jquery.are-you-sure.js"></script>
     <script type="text/javascript" src="/web/js/ays-beforeunload-shim.js"></script>
	<script type="text/javascript" src="/web/js/jquery.cookie.js"></script>

	<script type="text/javascript" src="/web/js/wysiwyg.js?version=25"></script>
	<script type="text/javascript" src="/web/js/tinymce/tinymce.min.js"></script>

	<script type="text/javascript" src="/web/js/jquery.filedrop.js"></script>
	<script type="text/javascript" src="/web/js/datepicker.min.js"></script>
	<script type="text/javascript" src="/web/js/i18n/datepicker.en.js"></script>
	
	<script type="text/javascript" src="/web/js/tabs.js?version=6"></script>
	
	<script type="text/javascript" src="/web/js/jquery.fancybox.min.js" async></script>
	 
	<link  href="https://cdnjs.cloudflare.com/ajax/libs/fotorama/4.6.4/fotorama.css" rel="stylesheet">
	<script src="/web/js/fotorama.js"></script>

	<script type="text/javascript">
		assetid = {if isset($asset['assetid'])}{$asset['assetid']}{else}0{/if};
		assettypeid = {if isset($asset)}{$asset['assettypeid']}{else}0{/if};		
		actiontoken = {if isset($user)}"{$user['actiontoken']}"{else}""{/if};
		
		$(document).ready(function() {
			makeTabs();
			
			$("select").each(function() {
				if ($(this).parents(".template").length == 0) {
					var ds = $(this).attr("noSearch") == 'noSearch';
					$(this).chosen({ placeholder_text_multiple: " ", disable_search:ds, });
				}
			});
			
			$('form[name=form1]').areYouSure();
		});		
		
		$(document).ready(function() \{
			{if isset($okmessage)}
				showMessage($(".okmessagepopup"));
			{/if}
			{if isset($warningmessage)}
				showMessage($(".warningmessagepopup"));
			{/if}
			{if isset($errormessage)}
				showMessage($(".errormessagepopup"));
			{/if}
		});

		function showMessage(elem) {
			elem
				.css("bottom", "-200px")
				.show()
				.animate(\{bottom: "0px" }, 500)
				.animate(\{bottom: "0px"}, 4000)
				.animate(\{bottom: "-200px" }, 100, function() { $(this).hide(); })
			;
		}
	</script>
	{if !empty($footerjs)}{$footerjs}{/if}

	<ul class="footer">
		<li style="float:left;">Copyright © 2021 Anego Studios | <a href="https://www.vintagestory.at/impressum.html/">Impressum</a></li>
		<li style="margin-left:10px;"><a href="https://github.com/anegostudios/vsmoddb#vs-mod-db-api-docs">Json Api</a></li>
		<li style="margin-left:10px;"><a href="/terms">Terms of use</a></li>
		<li style="margin-left:10px;"><a href="https://www.vintagestory.at/privacy/">Privacy Policy</a></li>
		<li><a rel="nofollow" href="https://www.vintagestory.at/contact/" data-ipsdialog="" data-ipsdialog-remotesubmit="" data-ipsdialog-flashmessage="Thanks, your message has been sent to the administrators." data-ipsdialog-title="Contact Us">Contact Us</a></li>
	</ul>


</body>
</html>
