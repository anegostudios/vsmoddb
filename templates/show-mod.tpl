{capture name="head"}
<meta content="{$asset['name']}" property="og:title" />
<meta content="{strip_tags($assetraw['text'])}" property="og:description" />
<meta name="twitter:card" content="summary_large_image">
{if (empty($asset['logoUrl']))}
<meta content="/web/img/mod-default.png" property="og:image" />
{else}
<meta content="{$asset['logoUrl']}" property="og:image" />
{/if}
<meta content="#91A357" name="theme-color" />
{/capture}

{include file="header"}

{if $transferownership}
	<div class="teaminvite overlay-when-readonly">
    <span>You have been invited to become the owner of this modification.</span>
    <div class="buttons">
        <a title="Accept Ownership" class="button submit" href="?acceptownershiptransfer=1">Accept</a>
        <a title="Decline Ownership" class="button btndelete" href="?acceptownershiptransfer=0">Decline</a>
    </div>
	</div>
{elseif $teaminvite}
	<div class="teaminvite overlay-when-readonly">
		<span>You have been invited to join the team of this mod</span>
		<div class="buttons">
			<a title="Click here to join to the team of this mod" class="button submit"
				href="?acceptteaminvite=1">Accept</a>
			<a title="Click here to decline the invitation to the team" class="button btndelete"
				href="?acceptteaminvite=0">Decline</a>
		</div>
	</div>
{/if}

<div class="edit-asset mod-{$asset['statusCode']}">
	<h2>
		<span>
			<a href="/list/mod">Mods</a>
		</span> /
		<span>
			{$asset["name"] ?? 'Add new Mod'}
		</span>
	</h2>

	{if $asset['statusCode']=='draft'}
		<div class="showmod-draftnotice">
			<h2 style="margin-bottom: 0;">Draft</h2>
			<small>Set to published to be listed. A draft mod is still visible to everyone via direct link</small>
		</div>
	{elseif $asset['statusCode']=='locked'}
		<div class="showmod-draftnotice" style="color:#e00">
			<h2 style="margin-bottom: 0;">Locked&nbsp;<i class="ico alert"></i></h2>
			<small>This mod has been locked by a moderator. The author may edit their mod to address existing issues.</small>
		</div>
	{/if}

	<input class="tab-trigger" id="tab-description" type="radio" name="tab" autocomplete="off">
	<input class="tab-trigger" id="tab-files" type="radio" name="tab" autocomplete="off">

	<ul class="tabs no-mark">
		<li><label for="tab-description" onclick="location.hash = 'tab-description'">Description</label></li>
		<li><label for="tab-files" onclick="location.hash = 'tab-files'">Files</label></li>
		{if $asset['homepageUrl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['homepageUrl']}">Homepage</a></li>
		{/if}
		{if $asset['wikiUrl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['wikiUrl']}">Wiki</a></li>
		{/if}
		{if $asset['issueTrackerUrl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['issueTrackerUrl']}">Issue tracker</a></li>
		{/if}
		{if $asset['sourceCodeUrl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['sourceCodeUrl']}">Source</a></li>
		{/if}
		{if $asset['donateUrl']}
			<li><a class="external" rel="external nofollow" target="_blank" href="{$asset['donateUrl']}">Donate</a></li>
		{/if}
	</ul>

	<div class="tab-container">
		<div class="tab-content description">
			<script nonce="{$cspNonce}">if(location.hash !== '#tab-files') document.getElementById('tab-description').checked = true;</script>
			<div style="float: right; margin-bottom: 1em;">
				{if isset($user) && canEditAsset($asset, $user)}
					<a class="button large shine strikethrough-when-banned strikethrough-when-readonly" href="/edit/mod/?assetid={$asset['assetId']}">Edit</a>&nbsp;
					<a class="button large shine strikethrough-when-banned strikethrough-when-readonly" href="/edit/release/?modid={$asset['modId']}">Add release</a>
				{/if}
			</div>

			<div class="imageslideshow fotorama" data-max-width="min(800px, 100%)" data-max-height="450"{if !empty($asset['trailerVideoUrl'])} data-width="800"{/if} data-autoplay="5000" data-nav="thumbs" data-allowfullscreen="true">
				{if !empty($asset['trailerVideoUrl'])}
					<a rel="nofollow" href="{$asset['trailerVideoUrl']}">Trailer Video</a>
				{/if}
				{foreach from=$files item=file}
					<img src="{$file['url']}">
				{/foreach}
				{if empty($files) && empty($asset['trailerVideoUrl']) && !empty($asset['logoUrl'])}
				<img src="{$asset['logoUrl']}">
				{/if}
			</div>

			<div class="infobox{if empty($asset['trailerVideoUrl']) && empty($files)} nomedia{/if}">
				<span class="text-weak">Tags:</span>
				{foreach from=$tags item=tag}
					<a href="/list/mod/?tagids[]={$tag['tagId']}" class="tag" style="background-color:{$tag['color']}"
						title="{$tag['text']}">#{$tag['name']}</a>
				{/foreach}
				<br>

				{if !empty($teamMembers)}
					<span class="text-weak">Authors:</span>

					<a class="username" href="/show/user/{$asset['creatorHash']}">{$asset['creatorName']}</a>{foreach from=$teamMembers item=teamMember}, <a class="username" href="/show/user/{$teamMember['userHash']}">{$teamMember['name']}</a>{/foreach}
				{else}
					<span class="text-weak">Author:</span> <a class="username" href="/show/user/{$asset['creatorHash']}">{$asset['creatorName']}</a>
				{/if}

				<br>

				<span class="text-weak">Side:</span> {ucfirst($asset['side'])}<br>
				<span class="text-weak">Created:</span> {fancyDate($asset['created'])}<br>
				<span class="text-weak">Last modified:</span> {fancyDate($asset['lastReleased'])}<br>
				<span class="text-weak">Downloads:</span> {intval($asset['downloads'])}<br>
				<a href="{if !empty($user)}#follow{else}/login{/if}"
					class="interactbox {if $isFollowing}on{else}off{/if}">
					<span class="off"><i class="bx bx-star"></i>Follow</span>
					<span class="on"><i class="bx bxs-star"></i>Unfollow</span>
					<span class="count">{$asset["follows"]}</span>
				</a>
				<p>
					{if $recommendedReleaseStable}
						{if count($recommendedReleaseStable['compatibleGameVersions']) > 0}<strong>
							{formatRecommendationAdjustedHint('Recommended', $recommendationIsInfluencedBySearch, $highestTargetVersion)}
							download (for Vintage Story {formatGrammaticallyCorrectEnumeration($recommendedReleaseStable['compatibleGameVersionsFolded'])}):</strong><br>
						{else}<strong>Recommended download:</strong><br>
						{/if}

						<a class="button square ico-button mod-dl" href="{formatDownloadTrackingUrl($recommendedReleaseStable['file'])}">{htmlspecialchars($recommendedReleaseStable['file']['name'])}</a>
						{if !empty($recommendedReleaseStable['identifier']) && $shouldShowOneClickInstall}&nbsp;{include file="button-one-click-install" release=$recommendedReleaseStable}{/if}
						{if $recommendedReleaseUnstable}<br>{/if}
					{elseif $fallbackRelease}
						{if count($fallbackRelease['compatibleGameVersions']) > 0}<strong>
							{formatRecommendationAdjustedHint('Latest', $recommendationIsInfluencedBySearch, $highestTargetVersion)}
							release (for Vintage Story {formatVersionsAndWarning($fallbackRelease, $highestTargetVersion)}):</strong><br>
						{else}<strong>Latest release:</strong><br>
						{/if}

						<a class="button square ico-button mod-dl" href="{formatDownloadTrackingUrl($fallbackRelease['file'])}">{htmlspecialchars($fallbackRelease['file']['name'])}</a>
						{if !empty($fallbackRelease['identifier']) && $shouldShowOneClickInstall}&nbsp;{include file="button-one-click-install" release=$fallbackRelease}{/if}
						{if $recommendedReleaseUnstable}<br>{/if}
					{/if}
					{if $recommendedReleaseUnstable}
						{if count($recommendedReleaseUnstable['compatibleGameVersions']) > 0}<strong>For testers (for Vintage Story {formatVersionsAndWarning($recommendedReleaseUnstable, $highestTargetVersion)}):</strong><br>
						{else}<strong>For testers:</strong><br>
						{/if}

						<a class="button square ico-button mod-dl" href="{formatDownloadTrackingUrl($recommendedReleaseUnstable['file'])}">{htmlspecialchars($recommendedReleaseUnstable['file']['name'])}</a>
						{if !empty($recommendedReleaseUnstable['identifier']) && $shouldShowOneClickInstall}&nbsp;{include file="button-one-click-install" release=$recommendedReleaseUnstable}{/if}
					{/if}
				</p>
			</div>

			<div style="clear:both;"><br></div>
			{$assetraw['text']}
			<div style="clear:both;"></div>
		</div>

		<div class="tab-content files">
			<script nonce="{$cspNonce}">if(location.hash === '#tab-files') document.getElementById('tab-files').checked = true;</script>
			<div style="float: right; margin-bottom: 1em;">
				{if isset($user) && canEditAsset($asset, $user)}
					<a class="button large shine strikethrough-when-banned strikethrough-when-readonly" href="/edit/release/?modid={$asset['modId']}">Add release</a>
				{/if}
			</div>

			<p style="clear: both"></p>
			<div style="overflow-x:auto;">
			<table class="stdtable release-table {$shouldListCompatibleGameVersion ? 'gv' : 'no-gv'} {$shouldShowOneClickInstall ? 'oc' : 'oc-oc'}">
				<thead>
					<tr>
						<th class="version">Mod Version</th>
						{if $shouldListCompatibleGameVersion}<th>Mod Identifier</th><th class="gameversion">For Game version</th>{/if}
						<th class="downloads">Downloads</th>
						<th class="releasedate">Released</th>
						<th class="changelog">Changelog</th>
						<th class="download">Download</th>
						{if $shouldShowOneClickInstall}<th><abbr title="Requires game version v1.18.0-rc.1 or later, currently not supported on MacOS.">1-click mod install*</abbr></th>{/if}
					</tr>
				</thead>
				<tbody>
				{if !empty($releases)}
					{foreach from=$releases item=release}
						<tr data-assetid="{$release['assetId']}">
							<td>
								{if isset($user) && canEditAsset($asset, $user)}
									<a style="display:block;" href="/edit/release?assetid={$release['assetId']}">{formatSemanticVersion($release['version'])}</a>
								{else}{formatSemanticVersion($release['version'])}{/if}
							</td>
							{if $shouldListCompatibleGameVersion}<td>
								{$release['identifier']}
							</td>
							<td>
								<div class="tags">
								{foreach from=$release['compatibleGameVersionsFolded'] item=versionStr}
									{if contains($versionStr, ' - ')}<span class="tag">{$versionStr}</span>
									{else}<a href="/list/mod/?gv[]={$versionStr}" class="tag" rel="tag">{$versionStr}</a>{/if}
								{/foreach}
								</div>
							</td>{/if}
							<td>{if !empty($release['file'])}{intval($release['file']['downloads'])}{/if}</td>
							<td>{fancyDate($release['created'])}</td>
							<td>{if $release['text']}<label for="cl-trigger-{$release['assetId']}" class="button square cl-trigger">Show</label>{else}Empty{/if}</td>
							<td>{if !empty($release['file'])}<a class="button square ico-button mod-dl" href="{formatDownloadTrackingUrl($release['file'])}">{htmlspecialchars($release['file']['name'])}</a>{/if}</td>
							{if $shouldShowOneClickInstall}<td>{if !empty($release['identifier'])}{include file="button-one-click-install"}{/if}</td>{/if}
						</tr>
						{if $release['text']}
						<tr><td class="collapsable cl-changelog" colspan="{$changelogColspan}">
							<input type="checkbox" id="cl-trigger-{$release['assetId']}" autocomplete="off">
							<div><div><div class="release-changelog">{$release['text']}</div></div></div>
						</td></tr>
						{/if}
					{/foreach}
				{else}
					<tr>
						<td colspan="6"><i>No releases found</i></td>
					</tr>
				{/if}
				</tbody>
			</table>
			</div>

			<script nonce="{$cspNonce}" type="text/javascript">
			{
				const table = document.getElementsByClassName('release-table')[0];
				table.addEventListener('change', e => {
					const t = e.target;
					table.querySelector(`label[for="${t.id}"]`).textContent = t.checked ? 'Hide' : 'Show';
				})
			}
			</script>

			<div style="clear:both;"></div>
		</div>
	</div>

	<div style="clear:both;"></div>


{include file="comments"}

{capture name="footerjs"}
	<script nonce="{$cspNonce}" type="text/javascript">
		modid = {$asset['modId']};

		$(document).ready(function() {
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
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/comments.js?version=14" async></script>
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/jquery.fancybox.min.js" async></script>
	<link nonce="{$cspNonce}" href="https://cdnjs.cloudflare.com/ajax/libs/fotorama/4.6.4/fotorama.css" rel="stylesheet">
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/fotorama.js?v=2"></script>
{/capture}

{include file="footer"}