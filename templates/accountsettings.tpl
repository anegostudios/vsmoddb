{include file="header"}
<h3>Account Settings</h3>

<form method="post" autocomplete="off" class="flex-list">

	<div class="editbox">
		<label>Name (<a class="external" href="https://account.vintagestory.at/profile">edit</a>)</label>
		<input type="text" name="name" value="{$user['name']}" disabled>
	</div>

	<div class="editbox">
		<label>E-Mail (<a class="external" href="https://account.vintagestory.at/profile">edit</a>)</label>
		<input type="text" name="email" value="{$user['email']}" disabled>
	</div>

	<div class="editbox">
		<label>Time Zone</label>
		<select name="timezone">
			{foreach from=$timezones item=timezone key=index}
				<option value="{$index}" {if $user['timezone'] == $timezone}selected="selected"{/if}>{$timezone}</option>
			{/foreach}
		</select>
	</div>

	<div class="flex-fill">
		<input type="submit" name="save" value="Save changes">
	</div>

</form>

<h3>Accessibility Settings</h3>
<p><small>Changes apply immediately and are saved per device.</small></p>
<label for="ch-a-opaque"><label class="toggle" for="ch-a-opaque"><input id="ch-a-opaque" type="checkbox" autocomplete="off" /></label> <abbr title="Turns the semi-transparent backgrounds of mod-card descriptions fully opaque.">Opaque mod-card descriptions</abbr></label>
<script>{
	const cb = document.getElementById('ch-a-opaque');
	try {
		cb.checked = +window.localStorage.getItem('opaque-desc');
		cb.addEventListener('change', e => window.localStorage.setItem('opaque-desc', String(+e.target.checked)));
	}
	catch {
		cb.parentElement.replaceWith('[Please allow local storage]');
	}
}</script>

<h3>Notification Settings</h3>
{if count($followedMods)}
<p><small>Changes apply immediately.</small></p>
<table id="followed-mods-settings">
	<thead>
		<tr><th>Followed Mod</th><th>Release Notifications</th></tr>
	</thead>
	<tbody>
		{foreach from=$followedMods item=followedMod}
			<tr data-modid="{$followedMod['modid']}" data-flags="{$followedMod['flags']}">
				<td><a href="{formatModPath($followedMod)}" target="_blank">{$followedMod['name']}</a></td>
				<td><label class="toggle" for="ch-0-{$followedMod['modid']}"><input id="ch-0-{$followedMod['modid']}" data-bit="0" type="checkbox"{if $followedMod['flags'] & FOLLOW_FLAG_CREATE_NOTIFICATIONS} checked="true"{/if} autocomplete="off" /></label></td>
			</tr>
		{/foreach}
	</tbody>
</table>
{else}
	<span>You don't follow any mods</span>
{/if}

<style>
	#followed-mods-settings {
		background-color: hsl(var(--c-accent) 86%);
		padding: .25rem;
		border: 1px solid hsl(var(--c-accent) 58%);
		border-radius: 2px;
	}

	#followed-mods-settings tr>*:nth-child(n+2) {
		text-align: center;
		padding-left: 1em;
	}
</style>

{capture name="footerjs"}
	<script type="text/javascript">
		const fms = document.getElementById('followed-mods-settings');
		if(fms) fms.addEventListener('change', e => {
			const trEl = e.target.parentElement.parentElement.parentElement;
			const targetModId = trEl.dataset.modid;
			const oldFlags = parseInt(trEl.dataset.settings);
			const targetBitMask = 1 << parseInt(e.target.dataset.bit);
			const targetBitState = e.target.checked;

			const newFlags = targetBitState ? (oldFlags | targetBitMask) : (oldFlags & ~targetBitMask);
			trEl.dataset.settings = newFlags;

			$.post('/api/v2/notifications/settings/followed-mods/'+targetModId, { 'new': newFlags })
				.fail(jqXHR => {
					e.target.checked = !targetBitState; // reset setting on error
					const oldFlags = parseInt(trEl.dataset.settings); // can't reuse outer oldSetting, other bits might have changed in the meantime
					trEl.dataset.settings = !targetBitState ? (oldFlags | targetBitMask) : (oldFlags & ~targetBitMask);

					const d = JSON.parse(jqXHR.responseText);
					addMessage(MSG_CLASS_ERROR, 'Failed to clear change setting' + (d.reason ? (': '+d.reason) : '.'))
				});
		});
	</script>
{/capture}

{include file="footer"}
