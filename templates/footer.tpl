		{if !empty($buttons)}
			<div id="buttons-overlay">
				{$buttons}
			</div>
		{/if}

		</main>
	</div>

	<script type="text/javascript" src="/web/js/jquery.are-you-sure.js"></script>
	<script type="text/javascript" src="/web/js/ays-beforeunload-shim.js"></script>
	<script type="text/javascript" src="/web/js/jquery.cookie.js"></script>

	<script type="text/javascript" src="/web/js/wysiwyg.js?version=28"></script>
	<script type="text/javascript" src="/web/js/tinymce/tinymce.min.js"></script>

	<script type="text/javascript" src="/web/js/jquery.filedrop.js?v=2"></script>
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

		const MSG_CLASS_OK = 'bg-success text-success';
		const MSG_CLASS_WARN = 'bg-warning';
		const MSG_CLASS_ERROR = 'bg-error text-error';

		const msgContainer = document.getElementById('message-container');
		function addMessage(clazz, html, escapeMessage) {
			escapeMessage = escapeMessage || false;
			const msgEl = document.createElement('div');
			msgEl.classList.add(...(clazz.split(' ')));
			if(escapeMessage) {
				msgEl.textContent = html;
				const d = document.createElement('span');
				d.classList.add('dismiss');
				msgEl.append(d)
			}
			else {
				msgEl.innerHTML = html+'<span class="dismiss"></span>';
			}
			msgContainer.append(msgEl);
		}

		msgContainer.addEventListener('click', function(e) {
			let t = e.target;
			if(!t || !t.classList.contains('dismiss')) return;
			t = t.parentElement;
			$(t).slideUp(400, () => t.remove());
		})
	</script>
	{if !empty($footerjs)}{$footerjs}{/if}

	<ul class="footer">
		<li style="float:left;">Copyright © 2021-2025 Anego Studios | <a href="https://www.vintagestory.at/impressum.html/">Impressum</a></li>
		<li style="margin-left:10px;"><a href="https://github.com/anegostudios/vsmoddb#vs-mod-db-api-docs">Json Api</a></li>
		<li style="margin-left:10px;"><a rel="terms-of-service" href="/terms">Terms of use</a></li>
		<li style="margin-left:10px;"><a rel="privacy-policy" href="https://www.vintagestory.at/privacy/">Privacy Policy</a></li>
		<li><a rel="nofollow" href="https://www.vintagestory.at/contact/" data-ipsdialog="" data-ipsdialog-remotesubmit="" data-ipsdialog-flashmessage="Thanks, your message has been sent to the administrators." data-ipsdialog-title="Contact Us">Contact Us</a></li>
	</ul>


</body>
</html>
