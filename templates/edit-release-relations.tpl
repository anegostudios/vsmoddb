<div class="mod-relations-editor">

	<section class="rel-section rel-auto-section">
		<header class="rel-section-header">
			<strong>Auto-detected dependencies</strong>
			<span class="hint">Required dependencies from this release's modinfo.json. Read-only here - re-synced on every release upload.</span>
		</header>
		{if empty($autoRelations)}
			<div class="rel-empty">No auto-detected dependencies on this release.</div>
		{else}
			<ul class="rel-list">
				{foreach from=$autoRelations item=rel}
				<li class="rel-item rel-type-{$rel['relationType']}">
					<span class="rel-badge badge-{$rel['relationType']}">{$rel['relationType']}</span>
					<span class="rel-target">
						{if $rel['resolvedMod']}
							<a href="{formatModPath($rel['resolvedMod'])}">{$rel['resolvedMod']['name']}</a>
						{else}
							<span class="unresolved" title="Not hosted on this moddb">{$rel['targetIdentifier']}</span>
						{/if}
					</span>
					<span class="rel-version">{if $rel['minVersion']}&ge; {formatSemanticVersion($rel['minVersion'])}{/if}</span>
				</li>
				{/foreach}
			</ul>
		{/if}
	</section>

	<section class="rel-section rel-manual-section">
		<header class="rel-section-header">
			<strong>Manual relations</strong>
			<span class="hint">Add optional / incompatible / tested-with entries, or override an auto-detected version by creating a manual entry with the same target.</span>
		</header>
		<ul class="rel-list manual-list" id="manual-relations-body">
		{foreach from=$manualRelations item=rel}
			<li class="rel-item rel-type-{$rel['relationType']}">
				<div class="rel-field rel-field-type">
					<label class="rel-field-label">Type</label>
					<select name="relations[{$rel['relationId']}][type]" class="rel-type-select no-chosen">
						<option value="required"     {if $rel['relationType'] == 'required'}selected{/if}>required</option>
						<option value="optional"     {if $rel['relationType'] == 'optional'}selected{/if}>optional</option>
						<option value="incompatible" {if $rel['relationType'] == 'incompatible'}selected{/if}>incompatible</option>
						<option value="tested_with"  {if $rel['relationType'] == 'tested_with'}selected{/if}>tested with</option>
					</select>
				</div>
				<div class="rel-field rel-field-target">
					<label class="rel-field-label">Target mod identifier</label>
					<input type="text" name="relations[{$rel['relationId']}][target]" value="{$rel['targetIdentifier']}" placeholder="e.g. carrycapacity" class="rel-target-input">
				</div>
				<div class="rel-field rel-field-version">
					<label class="rel-field-label">Min version</label>
					<input type="text" name="relations[{$rel['relationId']}][minVersion]" value="{if $rel['minVersion']}{formatSemanticVersion($rel['minVersion'])}{/if}" placeholder="any" class="rel-version-input">
				</div>
				<div class="rel-field rel-field-version">
					<label class="rel-field-label">Max version</label>
					<input type="text" name="relations[{$rel['relationId']}][maxVersion]" value="{if $rel['maxVersion']}{formatSemanticVersion($rel['maxVersion'])}{/if}" placeholder="none" class="rel-version-input">
				</div>
				<label class="rel-remove" title="Mark for removal on save">
					<input type="checkbox" name="removeRelations[]" value="{$rel['relationId']}">
					<span class="rel-remove-icon" aria-hidden="true">&times;</span>
				</label>
			</li>
		{/foreach}
		</ul>
		<button type="button" id="add-relation-btn" class="rel-add-btn button">+ Add manual relation</button>
	</section>

</div>

<script nonce="{$cspNonce}">
(function () {
	var list = document.getElementById('manual-relations-body');
	var btn  = document.getElementById('add-relation-btn');
	var counter = 0;
	btn.addEventListener('click', function () {
		counter++;
		var key = 'new-' + counter;
		var li = document.createElement('li');
		li.className = 'rel-item rel-item-new rel-type-required';
		li.innerHTML =
			'<div class="rel-field rel-field-type">'+
			  '<label class="rel-field-label">Type</label>'+
			  '<select name="relations['+key+'][type]" class="rel-type-select no-chosen">'+
			    '<option value="required" selected>required</option>'+
			    '<option value="optional">optional</option>'+
			    '<option value="incompatible">incompatible</option>'+
			    '<option value="tested_with">tested with</option>'+
			  '</select>'+
			'</div>'+
			'<div class="rel-field rel-field-target">'+
			  '<label class="rel-field-label">Target mod identifier</label>'+
			  '<input type="text" name="relations['+key+'][target]" placeholder="e.g. carrycapacity" class="rel-target-input">'+
			'</div>'+
			'<div class="rel-field rel-field-version">'+
			  '<label class="rel-field-label">Min version</label>'+
			  '<input type="text" name="relations['+key+'][minVersion]" placeholder="any" class="rel-version-input">'+
			'</div>'+
			'<div class="rel-field rel-field-version">'+
			  '<label class="rel-field-label">Max version</label>'+
			  '<input type="text" name="relations['+key+'][maxVersion]" placeholder="none" class="rel-version-input">'+
			'</div>'+
			'<button type="button" class="rel-discard" title="Discard this new entry">&times;</button>';
		list.appendChild(li);
		var first = li.querySelector('input.rel-target-input');
		if (first) first.focus();
	});
	list.addEventListener('click', function (e) {
		if (e.target && e.target.classList.contains('rel-discard')) {
			var li = e.target.closest('.rel-item-new');
			if (li) li.remove();
		}
	});
	// When the user changes the type select, update the li's class so the badge color refreshes.
	list.addEventListener('change', function (e) {
		if (e.target && e.target.classList.contains('rel-type-select')) {
			var li = e.target.closest('.rel-item');
			if (li) {
				var isNew = li.classList.contains('rel-item-new');
				li.className = 'rel-item ' + (isNew ? 'rel-item-new ' : '') + 'rel-type-' + e.target.value;
			}
		}
	});
})();
</script>
