{include file="header"}
	
	<form method="get" autocomplete="off" class="flex-list" style="margin-bottom: 1em;">
		{if !empty($sortby)}
			<input type="hidden" name="sortby" value="{$sortby}">
			<input type="hidden" name="sortdir" value="{$sortdir}">
		{/if}

		<span data-label="Text">
			<input type="text" name="text" value="{$searchvalues['text']}">
		</span>

		<span data-label="Side">
			<select name="side">
				<option value="">Any</option>
				<option value="both" {if isset($searchvalues['side']) && $searchvalues['side']=='both'}selected="selected"{/if}>Both</option>
				<option value="client" {if isset($searchvalues['side']) && $searchvalues['side']=='client'}selected="selected"{/if}>Client side mod</option>
				<option value="server" {if isset($searchvalues['side']) && $searchvalues['side']=='server'}selected="selected"{/if}>Server side mod</option>
			</select>
		</span>

		<span data-label="Tags">
			<select style="width:300px;" name="tagids[]" multiple>
				<option value="">-</option>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagid']}" title="{$tag['text']}" {if !empty($searchvalues['tagids'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</span>
		
		<span data-label="Author">
			<select style="width:150px;" name="userid">
				<option value="">-</option>
				{foreach from=$authors item=author}
					<option value="{$author['userid']}" {if !empty($searchvalues['userid']) && $searchvalues['userid'] == $author['userid']}selected="selected"{/if}>{$author['name']}</option>
				{/foreach}
			</select>
		</span>
		
		<span data-label="Game Version">
			<select style="width:100px;" name="mv" noSearch="noSearch">
				<option value="">-</option>
				{foreach from=$majorversions item=majorversion}
					<option value="{$majorversion['majorversionid']}" {if !empty($searchvalues['mv']) && $searchvalues['mv'] == $majorversion['majorversionid']}selected="selected"{/if}>{$majorversion['name']}</option>
				{/foreach}
			</select>
		</span>
		
		<span data-label="Game Version Exact">
			<select style="width:160px;" name="gv[]" multiple>
				<option value="">-</option>
				{foreach from=$versions item=tag}
					<option value="{$tag['tagid']}" {if !empty($searchvalues['gameversions'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</span>

		<span data-label="">
			<button class="button shine" type="submit" name="">Search</button>
		</span>
	</form>
	
	<div class="sort" style="margin-bottom: 1em;">
		<small>
		Sort by: 
		{foreach from=$sortbys item=sbnames key=sortbyname}
			<span style="margin-right: 5px;">
				{if $sortbyname == $sortby && $sortdir=='desc'}
					<a href="/list/mod?{$searchparams}&sortby={$sortbyname}&sortdir=a">{$sbnames[1]}</a>
				{else}
					<a href="/list/mod?{$searchparams}&sortby={$sortbyname}&sortdir=d">{$sbnames[0]}</a>
				{/if}
			</span>
		{/foreach}
		</small>
	</div>

	<script type="text/javascript">
		//$(window).load(function(){
			$("select").each(function() {
				if ($(this).parents(".template").length == 0) {
					var ds = $(this).attr("noSearch") == 'noSearch';
					$(this).chosen({ placeholder_text_multiple: " ", disable_search:ds, });
				}
			});
		//});	
	</script>

	{if !empty($rows)}
		<p>{count($rows)} mods, sorted by {$sortbypretty}</p>
		<div class="mods">
			{foreach from=$rows item=mod}{include file="list-mod-entry"}{/foreach}
			{if count($rows) < 5 /* @hack some spacing so the mods dont blow out */}<span></span><span></span><span></span><span></span><span></span>{/if}
		</div>
	{else}
		<p>&nbsp;</p>
		No mods found :(
	{/if}
	

{include file="footer"}
