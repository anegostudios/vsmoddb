{include file="header" hclass="innercontent with-buttons-bottom"}

<div style="padding: 1em 1em 0 1em">
<h2><span>Moderate {$shownUser['name']}</span></h2>

{if !empty($records)}
	<h3 style="margin-bottom: 1em;">History:</h3>
	<table class="stdtable">
	<thead>
		<tr><th>Applied at</th><th>Kind</th><th>Until</th><th>(Reason) Message</th><th>Acting Moderator</th></tr>
	</thead>
	<tbody>
		{foreach from=$records item=rec}
			<tr>
				<td>{fancydate($rec['created'])}</td>
				{if $rec['commentId']}
					<td><a target="_blank" href="/show/mod/{$rec['assetId']}#cmt-{$rec['commentId']}">{stringifyModactionKind($rec['kind'])}</a></td>
				{else}
					<td>{stringifyModactionKind($rec['kind'])}</td>
				{/if}
				<td>{formatDateWhichMightBeForever($rec['until'])}</td>
				<td>{$rec['reason']}</td><td>{htmlspecialchars($rec['moderatorName'])}</td>
			</tr>
		{/foreach}
	</tbody>
	</table>
{else}
	<h3 style="text-align: center;">No moderation record for this user</h3>
{/if}

</div>

<div class="buttons">
	<button class="button large btndelete shine" data-opens-dialog="ban-mdl" onclick="return false;">Ban User</button>
	<button class="button large submit shine" data-opens-dialog="unban-mdl" onclick="return false;">Redeem User</button>
	<button class="button large btndelete shine" data-opens-dialog="warn-mdl" onclick="return false;">Issue Warning</button>
	<div class="flex-spacer not-mobile"></div>
</div>

<dialog id="ban-mdl" autofocus="">
	<form class="with-buttons-bottom" method="post" autocomplete="off">
		<h1>Ban {$shownUser['name']}</h1>
		<p>
			Banning a user prevents them from posting new content or modifying old content of any kind on the ModDB.
			This includes Comments, Mods and Releases.
		</p>
		<p>Please provide a reason for the ban, which will be shown to the user:</p>
		<textarea name="modreason">{$banReasonSuggestion}</textarea>
		<p style="margin-top: 1em;"><label for="until">Ban until: <input type="datetime-local" name="until" /></label> or <label style="user-select: none;"><input type="checkbox" name="forever" /> Forever</label></p>
		<input type="hidden" name="at" value="{$user['actionToken']}">

		<div class="buttons">
			<button class="button large btndelete shine" type="submit" name="submit" value="ban">Ban User</button>
			<button class="button large shine" formmethod="dialog">Cancel</button>
		</div>
	</form>
</dialog>

<dialog id="unban-mdl" autofocus="">
	<form class="with-buttons-bottom" method="post" autocomplete="off">
		<h1>Redeem {$shownUser['name']}</h1>
		<p>
			Redeeming a user cuts all outstanding bans to end at the current moment.
		</p>
		<p>Please provide a reason for the redemption, this will not be shown to the user:</p>
		<textarea name="modreason"></textarea>
		<input type="hidden" name="at" value="{$user['actionToken']}">

		<div class="buttons">
			<button class="button large submit shine" type="submit" name="submit" value="redeem">Redeem User</button>
			<button class="button large shine" formmethod="dialog">Cancel</button>
		</div>
	</form>
</dialog>

<dialog id="warn-mdl" autofocus="">
	<form class="with-buttons-bottom" method="post" autocomplete="off">
		<h1>Issue Warning to {$shownUser['name']}</h1>
		<p>
			Issuing a warning to a user does nothing on a technical level, but delivers them a banner message about it.
			A warning generates a notification, which can be dismissed to get rid of the warning.
		</p>
		<p>Please provide the warning message to be shown to the user:</p>
		<textarea name="modreason"></textarea>
		<input type="hidden" name="at" value="{$user['actionToken']}">

		<div class="buttons">
			<button class="button large btndelete shine" type="submit" name="submit" value="warn">Issue Warning</button>
			<button class="button large shine" formmethod="dialog">Cancel</button>
		</div>
	</form>
</dialog>

{capture name="footerjs"}
<script nonce="{$cspNonce}" type="text/javascript">
\{
	const mdl = R.get('ban-mdl');
	const formEl = mdl.getElementsByTagName('form')[0];
	const reasonEl = formEl.getElementsByTagName('textarea')[0];
	const untilEl = formEl.querySelector('input[name="until"]');
	const foreverEl = formEl.querySelector('input[name="forever"]');
	R.onDOMLoaded(() => createEditor(reasonEl, tinymceSettingsCmt));
	formEl.addEventListener('submit', e => \{
		if(e.submitter.formMethod === 'dialog') return;
		if(!reasonEl.value) \{
			e.preventDefault();
			R.markAsErrorElement(formEl.getElementsByClassName('tox-tinymce')[0]);
		}
		if(!untilEl.value && !foreverEl.checked) \{
			e.preventDefault();
			R.markAsErrorElement(untilEl);
			R.markAsErrorElement(foreverEl.parentElement);
		}
	});
}
\{
	const mdl = R.get('unban-mdl');
	const formEl = mdl.getElementsByTagName('form')[0];
	const reasonEl = formEl.getElementsByTagName('textarea')[0];
	R.onDOMLoaded(() => createEditor(reasonEl, tinymceSettingsCmt));
	formEl.addEventListener('submit', e => \{
		if(e.submitter.formMethod === 'dialog') return;
		if(!reasonEl.value) \{
			e.preventDefault();
			R.markAsErrorElement(formEl.getElementsByClassName('tox-tinymce')[0]);
		}
	});
}
\{
	const mdl = R.get('warn-mdl');
	const formEl = mdl.getElementsByTagName('form')[0];
	const reasonEl = formEl.getElementsByTagName('textarea')[0];
	R.onDOMLoaded(() => createEditor(reasonEl, tinymceSettingsCmt));
	formEl.addEventListener('submit', e => \{
		if(e.submitter.formMethod === 'dialog') return;
		if(!reasonEl.value) \{
			e.preventDefault();
			R.markAsErrorElement(formEl.getElementsByClassName('tox-tinymce')[0]);
		}
	});
}
</script>
{/capture}

{include file="footer"}