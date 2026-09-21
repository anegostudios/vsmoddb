{include file="header"}
<h3>Account Settings</h3>

<form method="post" autocomplete="off" class="flex-list">
	<input type="hidden" name="at" value="{$user['actionToken']}">

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
<script nonce="{$cspNonce}" type="text/javascript">{
	const cb = document.getElementById('ch-a-opaque');
	try {
		cb.checked = +window.localStorage.getItem('opaque-desc');
		cb.addEventListener('change', e => window.localStorage.setItem('opaque-desc', String(+e.target.checked)));
	}
	catch {
		cb.parentElement.replaceWith('[Please allow local storage]');
	}
}</script>

<h3>Content Settings</h3>
<p><small>Changes apply immediately.</small></p>

<div id="gen-ai">
	<label>Low effort / Gen-AI tolerance: <span class="slider-wrapper"><input id="gen-ai-input" type="range" min="1" max="100" value="{$user['genAiTolerance'] === 0 ? 100 : $user['genAiTolerance']}" autocomplete="off"></span><br><span id="gen-ai-label">Mods reported as low effort will not be hidden.</span></label>
</div>

<h3>Notification Settings</h3>
{if count($followedMods)}
<p><small>Changes apply immediately.</small></p>
<table id="followed-mods-settings">
	<thead>
		<tr><th>Followed Mod</th><th>Release Notifications</th></tr>
	</thead>
	<tbody>
		{foreach from=$followedMods item=followedMod}
			<tr data-modid="{$followedMod['modId']}" data-flags="{$followedMod['flags']}">
				<td><a href="{formatModPath($followedMod)}" target="_blank">{$followedMod['name']}</a></td>
				<td><label class="toggle" for="ch-0-{$followedMod['modId']}"><input id="ch-0-{$followedMod['modId']}" data-bit="0" type="checkbox"{if $followedMod['flags'] & FOLLOW_FLAG_CREATE_NOTIFICATIONS} checked="true"{/if} autocomplete="off" /></label></td>
			</tr>
		{/foreach}
	</tbody>
</table>
{else}
	<span>You don't follow any mods</span>
{/if}

<script nonce="{$cspNonce}" type="text/javascript">
const fms = document.getElementById('followed-mods-settings');
if(fms) fms.addEventListener('change', e => \{
	const trEl = e.target.parentElement.parentElement.parentElement;
	const targetModId = trEl.dataset.modid;
	const oldFlags = parseInt(trEl.dataset.flags);
	const targetBitMask = 1 << parseInt(e.target.dataset.bit);
	const targetBitState = e.target.checked;

	const newFlags = targetBitState ? (oldFlags | targetBitMask) : (oldFlags & ~targetBitMask);
	trEl.dataset.flags = newFlags;

	const xhr = $.post('/api/v2/settings/notifications/followed-mods/'+targetModId, \{ new: newFlags, at: window.user.at })
	R.attachDefaultFailHandler(xhr, 'Failed to change notification setting', () => \{
		e.target.checked = !targetBitState; // reset setting on error
		const oldFlags = parseInt(trEl.dataset.flags); // can't reuse outer oldSetting, other bits might have changed in the meantime
		trEl.dataset.flags = !targetBitState ? (oldFlags | targetBitMask) : (oldFlags & ~targetBitMask);
		return false;
	});
});


const genaiInputEl = document.getElementById('gen-ai-input');
const genaiLabelEl = document.getElementById('gen-ai-label');
function updateGenAiLabel(value)
\{

	genaiLabelEl.textContent = (value == 100) 
		? 'Mods reported as low effort will not be hidden.' 
		: `Mods with at least ${value} 'low effort' report${value == 1 ? '' : 's'} will be hidden from you.`;
}
updateGenAiLabel(genaiInputEl.value);
genaiInputEl.addEventListener('input', e => updateGenAiLabel(e.target.value));
genaiInputEl.addEventListener('change', e => \{
	const xhr = $.post('/api/v2/settings/gen-ai', \{ 'tolerance': e.target.value == 100 ? 0 : parseInt(e.target.value) })
	R.attachDefaultFailHandler(xhr, 'Failed to change tolerance setting');
});
</script>

{capture name="footerjs"}
<script nonce="{$cspNonce}">
if(document.location.hash) \{
	const el = document.getElementById(document.location.hash.substring(1));
	if(el) temporaryHighlight(el);
}
</script>
{/capture}

{include file="footer"}
