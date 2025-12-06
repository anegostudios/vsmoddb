		{if !empty($buttons)}
			<div id="buttons-overlay">
				{$buttons}
			</div>
		{/if}

		</main>
	</div>

	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/jquery.cookie.js"></script>

	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/wysiwyg.js?version=41"></script>
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/tinymce/tinymce.min.js"></script>

	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/jquery.filedrop.js?v=2"></script>
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/datepicker.min.js"></script>
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/i18n/datepicker.en.js"></script>

	<script nonce="{$cspNonce}" type="text/javascript">
		assetid = {$asset['assetId'] ?? 0};
		assettypeid = {$asset['assetTypeId'] ?? 0};
		actiontoken = "{$user['actionToken'] ?? ''}";
	</script>
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/script.js?v=0"></script>
	{if !empty($footerjs)}{$footerjs}{/if}

	<footer>
		<ul class="no-mark">
			<li style="text-align: center;">Copyright © 2021-2025 Anego Studios | <span style="white-space: nowrap;">Currently hosting <b>{$totalModCount} Mods</b></span></li>
			<li><a href="https://www.vintagestory.at/impressum.html/">Impressum</a></li>
			<li><a href="https://github.com/anegostudios/vsmoddb#vs-mod-db-api-docs">Json Api</a></li>
			<li><a rel="terms-of-service" href="/terms">Terms of use</a></li>
			<li><a rel="privacy-policy" href="https://www.vintagestory.at/privacy/">Privacy Policy</a></li>
			<li><a rel="nofollow" href="https://www.vintagestory.at/contact/">Contact Us</a></li>
		</ul>
	</footer>
</body>
</html>
