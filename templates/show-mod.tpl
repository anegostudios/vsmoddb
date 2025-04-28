{assign var="first" value="1"}
{capture name="head"}
<meta content="{$asset['name']}" property="og:title" />
<meta content="{strip_tags($assetraw['text'])}" property="og:description" />
<meta name="twitter:card" content="summary_large_image">
{if (empty($asset['logourl']))}
<meta content="/web/img/mod-default.png" property="og:image" />
{else}
<meta content="{$asset['logourl']}" property="og:image" />
{/if}
<meta content="#91A357" data-react-helmet="true" name="theme-color" />
{/capture}

{include file="header" pagetitle="`$asset['name']` - "}

{if $transferownership}
	<div class="teaminvite">
    <span>You have been invited to become the owner of this modification.</span>
    <div class="buttons">
        <a title="Accept Ownership" class="button submit" href="?acceptownershiptransfer=1">Accept</a>
        <a title="Decline Ownership" class="button btndelete" href="?acceptownershiptransfer=0">Decline</a>
    </div>
	</div>
{elseif $teaminvite}
	<div class="teaminvite">
		<span>You have been invited to join the team of this mod</span>
		<div class="buttons">
			<a title="Click here to join to the team of this mod" class="button submit"
				href="?acceptteaminvite=1">Accept</a>
			<a title="Click here to decline the invitation to the team" class="button btndelete"
				href="?acceptteaminvite=0">Decline</a>
		</div>
	</div>
{/if}

<div class="edit-asset mod-{$asset['statuscode']}">
	<h2>
		<span class="assettype">
			<a href="/list/mod">Mods</a>
		</span> /
		<span>
			{$asset["name"] ?? 'Add new Mod'}
		</span>
	</h2>

	{if $asset['statuscode']=='draft'}
		<div class="showmod-draftnotice"><span class="title">Draft</span><br>Set to published to be listed. A draft mod is
			still visible to everyone via direct link</div>
	{/if}

	<ul class="tabs">
		<li><a href="#tab-description" rel="bookmark">Description</a></li>
		<li><a href="#tab-files" rel="bookmark">Files</a></li>
		{if $asset['homepageurl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['homepageurl']}">Homepage</a></li>
		{/if}
		{if $asset['wikiurl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['wikiurl']}">Wiki</a></li>
		{/if}
		{if $asset['issuetrackerurl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['issuetrackerurl']}">Issue tracker</a></li>
		{/if}
		{if $asset['sourcecodeurl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['sourcecodeurl']}">Source</a></li>
		{/if}
		{if $asset['donateurl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['donateurl']}">Donate</a></li>
		{/if}
	</ul>

	<div class="tab_container">
		<div class="tab_content" id="description">
			<div style="float: right; margin-bottom: 1em;">
				{if isset($user) && canEditAsset($asset, $user)}
					<a class="button large shine strikethrough-when-banned" href="/edit/mod/?assetid={$asset['assetid']}">Edit</a>&nbsp;
					<a class="button large shine strikethrough-when-banned" href="/edit/release/?modid={$asset['modid']}">Add release</a>
				{/if}
			</div>

			<div class="imageslideshow fotorama" data-max-width="800" data-max-height="450" data-autoplay="5000" data-nav="thumbs" data-allowfullscreen="true">
				{if (!empty($asset['trailervideourl']))}
					<a rel="nofollow" href="{$asset['trailervideourl']}">Trailer Video</a>
				{/if}
				{foreach from=$files item=file}
					<img src="{$file['url']}">
				{/foreach}
				{if empty($files) && empty($asset['trailervideourl']) && !empty($asset['logourl'])}
				<img src="{$asset['logourl']}">
				{/if}
			</div>

			<div class="infobox{if empty($asset['trailervideourl']) && empty($files)} nomedia{/if}">
				<span class="text-weak">Tags:</span>
				{foreach from=$tags item=tag}
					<a href="/list/mod/?tagids[]={$tag['tagid']}" class="tag" style="background-color:{$tag['color']}"
						title="{$tag['text']}">#{$tag['name']}</a>
				{/foreach}
				<br>

				{if !empty($teammembers)}
					<span class="text-weak">Authors:</span>

					<a href="/show/user/{$createdusertoken}">{$asset['createdusername']}</a>{foreach from=$teammembers item=teammember}, <a href="/show/user/{$teammember['usertoken']}">{$teammember['name']}</a>{/foreach}
				{else}
					<span class="text-weak">Author:</span> <a href="/show/user/{$createdusertoken}">{$asset['createdusername']}</a>
				{/if}

				<br>

				<span class="text-weak">Side:</span> {ucfirst($asset['side'])}<br>
				<span class="text-weak">Created:</span> {fancyDate($asset['created'])}<br>
				<span class="text-weak">Last modified:</span> {fancyDate($asset['lastreleased'])}<br>
				<span class="text-weak">Downloads:</span> {intval($asset['downloads'])}<br>
				<a href="{if !empty($user)}#follow{else}/login{/if}"
					class="interactbox {if $isfollowing}on{else}off{/if}">
					<span class="off"><i class="bx bx-star"></i>Follow</span>
					<span class="on"><i class="bx bxs-star"></i>Unfollow</span>
					<span class="count">{$asset["follows"]}</span>
				</a>

				{if $latestReleaseStable || $latestReleaseUnstable}
					<p>
						{if $latestReleaseStable}
							{if count($latestReleaseStable['compatibleGameVersions']) > 0}<span class="text-weak">Recommended download (for game {$latestReleaseStable['compatibleGameVersions'][count($latestReleaseStable['compatibleGameVersions'])-1]['name']}):</span><br>
							{else}<span class="text-weak">Recommended download:</span><br>
							{/if}


							<a class="downloadbutton" href="{formatDownloadTrackingUrl($latestReleaseStable['file'])}">{$latestReleaseStable['file']['filename']}</a>
							{if !empty($latestReleaseStable['modidstr'])}&nbsp;<a href="vintagestorymodinstall://{$latestReleaseStable['modidstr']}@{$latestReleaseStable['modversion']}"><abbr title="Requires game version v1.18.0-rc.1 or later, currently not supported on MacOS.">1-click install</abbr></a>{/if}
						{/if}
						<br>
						{if $latestReleaseUnstable}
							{if count($latestReleaseUnstable['compatibleGameVersions']) > 0}<span class="text-weak">For testers (for game {$latestReleaseUnstable['compatibleGameVersions'][count($latestReleaseUnstable['compatibleGameVersions'])-1]['name']}):</span><br>
							{else}<span class="text-weak">For testers:</span><br>
							{/if}


							<a class="downloadbutton pre-release" href="{formatDownloadTrackingUrl($latestReleaseUnstable['file'])}">{$latestReleaseUnstable['file']['filename']}</a>
							{if !empty($latestReleaseUnstable['modidstr'])}&nbsp;<a href="vintagestorymodinstall://{$latestReleaseUnstable['modidstr']}@{$latestReleaseUnstable['modversion']}"><abbr title="Requires game version v1.18.0-rc.1 or later, currently not supported on MacOS.">1-click install</abbr></a>{/if}
						{/if}
					</p>
				{/if}
			</div>

			<div style="clear:both;"><br></div>
			{$assetraw['text']}
			<div style="clear:both;"></div>
		</div>


		<div class="tab_content" id="files">
			<div style="float: right; margin-bottom: 1em;">
				{if isset($user) && canEditAsset($asset, $user)}
					<a class="button large shine strikethrough-when-banned" href="/edit/release/?modid={$asset['modid']}">Add release</a>
				{/if}
			</div>

			<p></p>
			<table class="stdtable" style="min-width: 900px">
				<thead>
					<tr>
						<th class="version">Version</th>
						<th class="gameversion">For Game version</th>
						<th class="downloads">Downloads</th>
						<th class="releasedate">Release date</th>
						<th class="changelog">Changelog</th>
						<th class="download">Download</th>
						<th><abbr title="Requires game version v1.18.0-rc.1 or later, currently not supported on MacOS.">1-click mod install*</abbr></th>
					</tr>
				</thead>
				<tbody>
				{if !empty($releases)}
					{foreach from=$releases item=release}
						<tr data-assetid="{$release['assetid']}" {if !isset($first)} class="latest"{/if}>
							<td>
								{if isset($user) && canEditAsset($asset, $user)}
									<a style="display:block;" href="/edit/release?assetid={$release['assetid']}">v{$release['modversion']}</a>
								{else}v{$release['modversion']}{/if}
								<div class="changelogtext" style="display:none;">
									<strong>v{$release['modversion']}</strong><br>
									{$release["text"]}
								</div>
							</td>
							<td>
								<div class="tags">
								{foreach from=$release['compatibleGameVersions'] item=tag}
									{if $tag['tagid']==0}<a href="#" class="tag" style="background-color:{$tag['color']}" title="{$tag['desc']}">{$tag['name']}*</a>
									{else}<a href="/list/mod/?gv[]={$tag['tagid']}" class="tag" rel="tag" style="background-color:{$tag['color']}">#{$tag['name']}</a>{/if}
								{/foreach}
								</div>
						</td>
							<td>{if !empty($release['file'])}{intval($release['file']['downloads'])}{/if}</td>
							<td>{fancyDate($release['created'])}</td>
							<td><a href="#showchangelog">Show</a></td>
							<td>{if !empty($release['file'])}<a class="downloadbutton{if $release['isPreRelease']} pre-release{/if}" href="{formatDownloadTrackingUrl($release['file'])}">{$release['file']['filename']}</a>{/if}</td>
							<td>{if !empty($release['modidstr'])}<a href="vintagestorymodinstall://{$release['modidstr']}@{$release['modversion']}">Install now</a>{/if}</td>
						</tr>
						{assign var="first" value="1"}
					{/foreach}
				{else}
					<tr>
						<td colspan="6"><i>No releases found</i></td>
					</tr>
				{/if}
				</tbody>
			</table>

			<div style="clear:both;"></div>



		</div>

	</div>

	<div style="clear:both;"></div>


{include file="comments"}

{capture name="footerjs"}
	<script type="text/javascript">
		modid = {$asset['modid']};

		$(document).ready(function() {
			$("a[href='#showchangelog']").click(function() {
				$self = $(this).parent().parent().find(".changelogtext");
				$(".changelogtext").each(function() {
					if ($(this)[0] != $self[0]) $(this)
						.hide();
				}); // hide others
				$self.toggle();
				return true;
			});

			$("a[href='#follow']").click(function() {
				const oldCount = parseInt($(".count", $(this)).text());

				let promise;
				if ($(this).hasClass("on")) {
					$(this).toggleClass("on off");
					$(".count", $(this)).text("" + (oldCount - 1));

					promise = $.post(`/api/v2/notifications/settings/followed-mods/${modid}/unfollow`);
				} else {
					$(this).toggleClass("on off");
					$(".count", $(this)).text("" + (oldCount + 1));

					promise = $.post(`/api/v2/notifications/settings/followed-mods/${modid}`, { 'new': 1 /* @hardcoded */ });
				}

				promise.fail(jqXHR => {
					$(this).toggleClass("on off");
					$(".count", $(this)).text("" + oldCount);

					const d = JSON.parse(jqXHR.responseText);
					addMessage(MSG_CLASS_ERROR, 'Failed to (un-)follow mod' + (d.reason ? (': '+d.reason) : '.'), true)
				});
			});
		});
	</script>
	<script type="text/javascript" src="/web/js/comments.js?version=13" async></script>
{/capture}

{include file="footer"}