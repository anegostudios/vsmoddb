{assign var="first" value="1"}
{capture name="head"}
<meta content="{$showuser['name']}" property="og:title" />
<meta content="{strip_tags($assetraw['text'])}" property="og:description" />
<meta name="twitter:card" content="summary_large_image">
{if (empty($asset['logofilename']))}
<meta content="/web/img/mod-default.png" property="og:image" />
{else}
<meta content="/files/asset/{$asset['assetid']}/{$asset['logofilename']}" property="og:image" />
{/if}
<meta content="#91A357" data-react-helmet="true" name="theme-color" />
{/capture}

{include file="header" pagetitle="`$asset['name']` - "}

<div class="edit-asset mod-{$asset['statuscode']}">
	{if $asset['assetid']}
		<h2>
			<span class="assettype">
				<a href="/list/mod">Mods</a>
			</span> / 
			<span class="title">
				{$asset["name"]}
			</span>
		</h2>	
	{else}
		<h2>
			<span class="assettype">
				<a href="/list/{$entrycode}">{$entryplural}</a>
			</span> / 
			<span class="title">Add new {$entrysingular}</span>
		</h2>
	{/if}
	
	{if $asset['statuscode']=='draft'}
		<div class="showmod-draftnotice"><span class="title">Draft</span><br>Set to published to be listed. A draft mod is still visible to everyone via direct link</div>
	{/if}
	
	<ul class="tabs">
		<li><a href="#tab-description">Description</a></li>
		<li><a href="#tab-files">Files</a></li>
		{if $asset['homepageurl']}
			<li><a href="{$asset['homepageurl']}"><img src="/web/img/externallink.png" height="18"> Homepage</a></li>
		{/if}
		{if $asset['wikiurl']}
			<li><a href="{$asset['wikiurl']}"><img src="/web/img/externallink.png" height="18"> Wiki</a></li>
		{/if}
		{if $asset['issuetrackerurl']}
			<li><a href="{$asset['issuetrackerurl']}"><img src="/web/img/externallink.png" height="18"> Issue tracker</a></li>
		{/if}
		{if $asset['sourcecodeurl']}
			<li><a href="{$asset['sourcecodeurl']}"><img src="/web/img/externallink.png" height="18"> Source</a></li>
		{/if}
		{if $asset['donateurl']}
			<li><a href="{$asset['donateurl']}" target="_blank"><img src="/web/img/externallink.png" height="18"> Donate</a></li>
		{/if}
	</ul>
	
	<div class="tab_container">
		<div class="tab_content" id="description">
			<div style="float: right;">
				{if !empty($user) && $user['userid'] == $asset['createdbyuserid']}
					{include
						file="button"
						href="/edit/mod/?assetid=`$asset['assetid']`"
						buttontext="Edit"
					}
					{include
						file="button"
						href="/edit/release/?modid=`$asset['modid']`"
						buttontext="Add release"
					}
				{/if}
			</div>
		
			<div class="imageslideshow fotorama" data-width="675" data-autoplay="5000" data-nav="thumbs" data-allowfullscreen="true">
				{if (!empty($asset['trailervideourl']))}
					<a href="{$asset['trailervideourl']}">Trailer Video</a>
				{/if}
				{foreach from=$files item=file}
					<img src="/files/asset/{$asset['assetid']}/{$file['filename']}">
				{/foreach}
			</div>
			
			<div class="infobox{if empty($asset['trailervideourl']) && empty($files)} nomedia{/if}">
				<span class="text-weak">Category:</span>
					{foreach from=$tags item=tag}
						<a href="/list/mod/?tagids[]={$tag['tagid']}" class="tag" style="background-color:{$tag['color']}" title="{$tag['text']}">#{$tag['name']}</a>
					{/foreach}
				<br>
				
				<span class="text-weak">Author:</span> <a href="/list/mod?userid={$asset['createdbyuserid']}">{$asset['createdusername']}</a><br>
				<span class="text-weak">Side:</span> {ucfirst($asset['side'])}<br>
				<span class="text-weak">Created:</span> {fancyDate($asset['created'])}<br>
				<span class="text-weak">Last modified:</span> {fancyDate($asset['lastreleased'])}<br>
				<span class="text-weak">Downloads:</span> {intval($asset['downloads'])}<br>
				<a href="{if !empty($user)}#follow{else}/login{/if}" class="interactbox {if $isfollowing}on{else}off{/if}">
					<span class="off"><i class="far fa-star"></i>Follow</span>
					<span class="on"><i class="fas fa-star"></i>Unfollow</span>
					<span class="count">{$asset["follows"]}</span>
				</a>
			</div>
			
			<div style="clear:both;"><br></div>
			{$assetraw['text']}
			<div style="clear:both;"></div>
		</div>
		
		
		<div class="tab_content" id="files">
			<div style="float: right;">
				{if !empty($user) && $user['userid'] == $asset['createdbyuserid']}
					{include
						file="button"
						href="/edit/release/?modid=`$asset['modid']`"
						buttontext="Add release"
					}
				{/if}
			</div>
					
			<p></p>
			<table class="stdtable" id="{$entryplural}" style="min-width: 900px">
				<thead>
					<tr>
						<th class="version">Version</th>
						<th class="gameversion">For Game version</th>
						<th class="downloads">Downloads</th>
						<th class="releasedate">Release date</th>
						<th class="changelog">Changelog</th>
						<th class="download">Download</th>
						<th><abbr title="Works only on Windows and only from game client version 1.17.9 onwards">1-click mod install*</abbr></th>
					</tr>
				</thead>
				<tbody>
				{if !empty($releases)}
					{foreach from=$releases item=release}
						<tr data-assetid="{$release['assetid']}" {if !isset($first)} class="latest"{/if}>
							<td>
								{if !empty($user) && $user['userid'] == $asset['createdbyuserid']}
									<a style="display:block;" href="/edit/release?assetid={$release['assetid']}">v{$release['modversion']}</a>
								{else}v{$release['modversion']}{/if}
								<div class="changelogtext" style="display:none;">
									<strong>v{$release['modversion']}</strong><br>
									{$release["text"]}
								</div>
							</td>
							<td>
								<div class="tags">
								{foreach from=$release['tags'] item=tag}
									<a href="/list/mod/?gv[]={$tag['tagid']}" class="tag" style="background-color:{$tag['color']}">#{$tag['name']}</a>
								{/foreach}
								</div>
						</td>
							<td>{if !empty($release['file'])}{intval($release['file']['downloads'])}{/if}</td>
							<td>{fancyDate($release['created'])}</td>
							<td><a href="#showchangelog">Show</a></td>
							<td>{if !empty($release['file'])}<a style="display:block;" href="/download?fileid={$release['file']['fileid']}">{$release['file']['filename']}</a>{/if}</td>
							<td>{if !empty($release['modidstr'])}<a href="vintagestorymodinstall://-i {$release['modidstr']}@{$release['modversion']}">Install now</a>{/if}</td>
						</tr>
						{assign var="first" value="1"}
					{/foreach}
				{else}
					<td colspan="{count($columns)}"><i>No releases found</i></td>
				{/if}
				</tbody>
			</table>			
			
			<div style="clear:both;"></div>
			
			

		</div>
	
	</div>
	
	<div style="clear:both;"></div>
	

	{include file="comments"}
	
</div>


<p style="clear:both;"><br></p>
{capture name="footerjs"}
<script type="text/javascript">
	modid = {$asset['modid']};

	$(document).ready(function() {
		$("a[href='#showchangelog']").click(function() {
			$self = $(this).parent().parent().find(".changelogtext");
			$(".changelogtext").each(function() { if ($(this)[0] != $self[0]) $(this).hide(); }); // hide others
			$self.toggle();
			return true;
		});
		
		$("a[href='#follow']").click(function() {
			var op = "follow";
			var cnt = parseInt($(".count", $(this)).html());		
			if ($(this).hasClass("on")) {
				op="unfollow";
				$(this).removeClass("on").addClass("off");			
				$(".count", $(this)).html(""+(cnt-1));
			} else {
				$(this).removeClass("off").addClass("on");
				$(".count", $(this)).html(""+(cnt + 1));
			}
			
			$.get("/set-follow", { op: op, modid: modid });
		});
	});
</script>
<script type="text/javascript" src="/web/js/comments.js?version=7" async></script>
{/capture}

{include file="footer"}
