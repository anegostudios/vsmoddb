{include file="header"}
	
	<form method="get">
		{if !empty($sortby)}
			<input type="hidden" name="sortby" value="{$sortby}">
			<input type="hidden" name="sortdir" value="{$sortdir}">
		{/if}

		<div class="searchbox">
			<label>Text</label>
			<input type="text" name="text" value="{$searchvalues['text']}">
		</div>

		<div class="searchbox">
			<label>Side</label>
			<select name="side">
				<option value="">Any</option>
				<option value="both" {if isset($searchvalues['side']) && $searchvalues['side']=='both'}selected="selected"{/if}>Both</option>
				<option value="client" {if isset($searchvalues['side']) && $searchvalues['side']=='client'}selected="selected"{/if}>Client side mod</option>
				<option value="server" {if isset($searchvalues['side']) && $searchvalues['side']=='server'}selected="selected"{/if}>Server side mod</option>
			</select>
		</div>

		<div class="searchbox">
			<label>Tags</label>
			<select style="width:300px;" name="tagids[]" multiple>
				<option value="">-</option>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagid']}" title="{$tag['text']}" {if !empty($searchvalues['tagids'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>
		
		<div class="searchbox">
			<label>Author</label>
			<select style="width:150px;" name="userid">
				<option value="">-</option>
				{foreach from=$authors item=author}
					<option value="{$author['userid']}" {if !empty($searchvalues['userid']) && $searchvalues['userid'] == $author['userid']}selected="selected"{/if}>{$author['name']}</option>
				{/foreach}
			</select>
		</div>
		
		<div class="searchbox">
			<label>Game Version</label>
			<select style="width:100px;" name="mv" noSearch="noSearch">
				<option value="">-</option>
				{foreach from=$majorversions item=majorversion}
					<option value="{$majorversion['majorversionid']}" {if !empty($searchvalues['mv']) && $searchvalues['mv'] == $majorversion['majorversionid']}selected="selected"{/if}>{$majorversion['name']}</option>
				{/foreach}
			</select>
		</div>
		
		<div class="searchbox">
			<label>Game Version Exact</label>
			<select style="width:160px;" name="gv[]" multiple>
				<option value="">-</option>
				{foreach from=$versions item=tag}
					<option value="{$tag['tagid']}" {if !empty($searchvalues['gameversions'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="searchbox">
			<p style="height:6px;"></p>
			<button type="submit" name="" style="padding:6px 12px;">Search</button>
		</div>
	</form>
	
	<div class="sort" style="font-size: 84%; clear:both";>
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
	</div>

	{if !empty($rows)}
		<p style="margin-bottom:0px;">{count($rows)} mods, sorted by {$sortbypretty}</p>
		<div class="mods">
			{foreach from=$rows item=mod}{include file="list-mod-entry"}{/foreach}
		</div>
	{else}
		<p style="clear:both;">&nbsp;</p>
		No mods found :(
	{/if}
	



{include file="footer"}
