{capture name="head"}
<meta property="og:title" content="{$asset['name']}" />
<meta property="og:description" content="{$asset['summary']}" />
<meta name="twitter:card" content="summary_large_image">
{if (empty($asset['logoUrl']))}
<meta property="og:image" content="/web/img/mod-default.png" />
{else}
<meta property="og:image" content="{$asset['logoUrl']}" />
{/if}
<meta property="og:site_name " content="VS ModDB" />
<meta name="theme-color" content="#91A357" />
<link nonce="{$cspNonce}" href="/web/css/prism.min.css?version=0" rel="stylesheet" type="text/css">
<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/prism.min.js?v=0" defer=""></script>
{/capture}

{include file="header"}

{if $transferownership}
	<form class="teaminvite transfer overlay-when-readonly" method="post" onsubmit="return false;">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<span>You have been invited to become the owner of this modification.</span>
		<div class="buttons">
			<button type="submit" name="accept" value="1" title="Accept Ownership" class="button submit">Accept</button>
			<button type="submit" name="accept" value="0" title="Decline Ownership" class="button btndelete">Decline</button>
		</div>
	</form>
{elseif $teaminvite}
	<form class="teaminvite overlay-when-readonly" method="post">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<span>You have been invited to join the team of this mod</span>
		<div class="buttons">
			<button type="submit" name="acceptteaminvite" value="1" title="Click here to join to the team of this mod" class="button submit">Accept</button>
			<button type="submit" name="acceptteaminvite" value="0" title="Click here to decline the invitation to the team" class="button btndelete">Decline</button>
		</div>
	</form>
{/if}

<div class="edit-asset mod-{$asset['statusCode']}">
	<h2>
		<span>
		{if $asset['category'] === CATEGORY_SERVER_TWEAK}
			<a href="/list/mod?c=s">Server Tweaks</a>
		{else}
			<a href="/list/mod">Mods</a>
		{/if}
		</span> /
		<span>
			{$asset["name"]}
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

	<input class="tab-trigger" id="tab-description" type="radio" name="tab" checked="true" autocomplete="off">
	<input class="tab-trigger" id="tab-files" type="radio" name="tab" autocomplete="off">

	<script nonce="{$cspNonce}">
		document.getElementById(location.hash !== '#tab-files' ? 'tab-description' : 'tab-files').checked = true;
		window.addEventListener('pageshow', e => {
			if(e.persisted) document.getElementById(location.hash === '#tab-files' ? 'tab-files' : 'tab-description').checked = true;
		});
	</script>


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
			<div style="float: right; margin-bottom: 1em;">
				{if isset($user) && canEditMod($asset, $user)}
					<a class="button large shine strikethrough-when-banned strikethrough-when-readonly" href="/edit/mod/?assetid={$asset['assetId']}">Edit</a>&nbsp;
					<a class="button large shine strikethrough-when-banned strikethrough-when-readonly" href="/edit/release/?modid={$asset['modId']}">Add release</a>&nbsp;
				{/if}
			</div>

			{if !empty($files) || !empty($trailerEmbedUrl)}
			<div class="gallery" style="--gallery-w:{$galleryWidth}px; --gallery-h:{$galleryHeight}px; float: left; margin-right: 20px;">
				<div class="viewport">
					<div class="stage">
						{if !empty($trailerEmbedUrl)}
						<div><iframe src="{$trailerEmbedUrl}" allowfullscreen loading="lazy"></iframe></div>
						{/if}
						{foreach from=$files item=file}
						<div><img src="{$file['url']}" loading="lazy"></div>
						{/foreach}
					</div>
					{if count($files) + (!empty($trailerEmbedUrl) ? 1 : 0) >= 2}
					<button class="ctrl arr prev" title="Previous"></button>
					<button class="ctrl arr next" title="Next"></button>
					{/if}
					<button class="ctrl fullscreen" title="Fullscreen"></button>
				</div>
				{if count($files) + (!empty($trailerEmbedUrl) ? 1 : 0) >= 2}
				<nav>
					<div><?
						$i = 0;
						if(!empty($trailerEmbedUrl)) {
							?><button class="video-thumb" data-i="0"><img src="<?= $trailerThumbUrl ?>" loading="lazy"></button><?
							$i++;
						}
						foreach($files as $file) {
							?><button data-i="<?= $i ?>"><img src="<?= $file['url'] ?>" loading="lazy"></button><?
							$i++;
						}
						?>
						<div class="indicator"></div>
					?></div>
				</nav>
				{/if}
			</div>
			{/if}

			<dl class="infobox{if empty($asset['trailerVideoUrl']) && empty($files)} nomedia{/if}">
				<dt>Tags:</dt>
				{if empty($user) || DISABLE_USER_TAGS}
				<dd class="tags">
					{if $hiddenTagsCount > 0}<input type="checkbox" id="more-tags-trigger" autocomplete="off" />{/if}
					{foreach from=$tags item=tag key=i}
					<a href="/list/mod?tagids[]={$tag['tagId']}" class="tag{if $tag['votes'] < TAG_DOWNVOTED_THRESHOLD} downvoted hidden{elseif $i >= 8} hidden{/if}" style="background-color:{$tag['color']}" title="{$tag['text']}">{$tag['name']}</a>
					{/foreach}
				</dd>
				{else}
				<dd class="tags votable">
					{if $hiddenTagsCount > 0}<input type="checkbox" id="more-tags-trigger" autocomplete="off" />{/if}
					{foreach from=$tags item=tag key=i}
					<span class="tag{if $tag['votes'] < TAG_DOWNVOTED_THRESHOLD} downvoted hidden{elseif $i >= 8} hidden{/if}" style="background-color:{$tag['color']}" title="{$tag['text']}" data-tagid="{$tag['tagId']}" data-vote="{$tag['vote'] ?? 0}">
						<a href="/list/mod?tagids[]={$tag['tagId']}">{$tag['name']}</a><span class="add"></span><span class="rem"></span>
					</span>
					{/foreach}
					{if $hiddenTagsCount > 0}<label for="more-tags-trigger">{$hiddenTagsCount}</label>{/if}
					<a href="#" data-opens-dialog="add-tag-mdl" onclick="return false;">Add tags...</a>
				</dd>
				{/if}

				{if !empty($teamMembers)}
				<dt>Authors:</dt>
				<dd><a class="username" href="/show/user/{$asset['creatorHash']}">{$asset['creatorName']}</a>{foreach from=$teamMembers item=teamMember}, <a class="username" href="/show/user/{$teamMember['userHash']}">{$teamMember['name']}</a>{/foreach}</dd>
				{else}
				<dt>Author:</dt><dd><a class="username" href="/show/user/{$asset['creatorHash']}">{$asset['creatorName']}</a></dd>
				{/if}

				<dt>Side:</dt><dt>{ucfirst($asset['side'])}</dt>
				<dt>Created:</dt><dt>{fancyDate($asset['created'])}</dt>
				<dt>Last modified:</dt><dt>{fancyDate($asset['lastReleased'])}</dt>
				<dt>Downloads:</dt><dt>{intval($asset['downloads'])}</dt>
				{if isset($user)}
				<dd class="full-width">
					<a href="{if !empty($user)}#follow{else}/login{/if}" class="interactbox {if $isFollowing}on{else}off{/if} strikethrough-when-readonly" onclick="return false;">
						<span class="off"><i class="bx bx-star"></i>Follow</span>
						<span class="on"><i class="bx bxs-star"></i>Unfollow</span>
						<span class="count">{$asset["follows"]}</span>
					</a>
					&nbsp;&nbsp;
					<a id="report-mod-btn" class="strikethrough-when-banned strikethrough-when-readonly" onclick="return false;"><i class="bx bx-error"></i> <span>Report</span></a>
				</dd>
				{/if}
				<dd class="full-width">
					{if $recommendedReleaseStable}
						{if count($recommendedReleaseStable['compatibleGameVersions']) > 0}<strong>
							{formatRecommendationAdjustedHint('Recommended', $recommendationIsInfluencedBySearch, $highestTargetVersion)}
							download (for Vintage Story {formatGrammaticallyCorrectEnumeration($recommendedReleaseStable['compatibleGameVersionsFolded'])}):</strong><br>
						{else}<strong>Recommended download:</strong><br>
						{/if}

						<a class="button square ico-button mod-dl" href="{formatDownloadTrackingUrl($recommendedReleaseStable['file'])}">{escapeHtml($recommendedReleaseStable['file']['name'])}</a>
						{if !empty($recommendedReleaseStable['identifier']) && $shouldShowOneClickInstall}&nbsp;{include file="button-one-click-install" release=$recommendedReleaseStable}{/if}
						{if $recommendedReleaseUnstable}<br>{/if}
					{elseif $fallbackRelease}
						{if count($fallbackRelease['compatibleGameVersions']) > 0}<strong>
							{formatRecommendationAdjustedHint('Latest', $recommendationIsInfluencedBySearch, $highestTargetVersion)}
							release (for Vintage Story {formatVersionsAndWarning($fallbackRelease, $highestTargetVersion)}):</strong><br>
						{else}<strong>Latest release:</strong><br>
						{/if}

						<a class="button square ico-button mod-dl" href="{formatDownloadTrackingUrl($fallbackRelease['file'])}">{escapeHtml($fallbackRelease['file']['name'])}</a>
						{if !empty($fallbackRelease['identifier']) && $shouldShowOneClickInstall}&nbsp;{include file="button-one-click-install" release=$fallbackRelease}{/if}
						{if $recommendedReleaseUnstable}<br>{/if}
					{/if}
					{if $recommendedReleaseUnstable}
						{if count($recommendedReleaseUnstable['compatibleGameVersions']) > 0}<strong>For testers (for Vintage Story {formatVersionsAndWarning($recommendedReleaseUnstable, $highestTargetVersion)}):</strong><br>
						{else}<strong>For testers:</strong><br>
						{/if}

						<a class="button square ico-button mod-dl" href="{formatDownloadTrackingUrl($recommendedReleaseUnstable['file'])}">{escapeHtml($recommendedReleaseUnstable['file']['name'])}</a>
						{if !empty($recommendedReleaseUnstable['identifier']) && $shouldShowOneClickInstall}&nbsp;{include file="button-one-click-install" release=$recommendedReleaseUnstable}{/if}
					{/if}
				</dd>
			</dl>

			<div style="clear:both;"><br></div>
			{$assetraw['text']}
			<div style="clear:both;"></div>
		</div>

		<div class="tab-content files">
			<div style="float: right; margin-bottom: 1em;">
				{if isset($user) && canEditMod($asset, $user)}
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
						<tr data-assetid="{$release['assetId']}" {$release['retractionReason'] ? 'class="retracted"' : ''}>
							<td>
								{if isset($user) && (!$release['retractionReason'] || canModerate(null, $user)) && canEditMod($asset, $user)}
									<a style="display:block;" href="/edit/release?assetid={$release['assetId']}">{formatSemanticVersion($release['version'])}</a>
								{else}{formatSemanticVersion($release['version'])}{/if}
							</td>
							{if $shouldListCompatibleGameVersion}<td>
								{$release['identifier']}
							</td>
							<td>
								<div class="tags">
								{foreach from=$release['compatibleGameVersionsFolded'] item=versionStr}
									{if str_contains($versionStr, ' - ')}<span class="tag">{$versionStr}</span>
									{else}<a href="/list/mod?gv[]={$versionStr}" class="tag" rel="tag">{$versionStr}</a>{/if}
								{/foreach}
								</div>
							</td>{/if}
							<td>{if !empty($release['file'])}{intval($release['file']['downloads'])}{/if}</td>
							<td>{fancyDate($release['created'])}</td>
							<td>{if $release['text'] || $release['retractionReason']}<label for="cl-trigger-{$release['assetId']}" class="button square cl-trigger">Show</label>{else}Empty{/if}</td>
							{if !$release['retractionReason']}
								<td>{if !empty($release['file'])}<a class="button square ico-button mod-dl" href="{formatDownloadTrackingUrl($release['file'])}">{escapeHtml($release['file']['name'])}</a>{/if}</td>
							{if $shouldShowOneClickInstall}<td>{if !empty($release['identifier'])}{include file="button-one-click-install"}{/if}</td>{/if}
							{else}
								<td {if $shouldShowOneClickInstall} colspan="2"{/if}>Release Retracted</td>
							{/if}
						</tr>
						{if $release['text'] || $release['retractionReason']}
						<tr><td class="collapsable cl-changelog" colspan="{$changelogColspan}">
							<input type="checkbox" id="cl-trigger-{$release['assetId']}" autocomplete="off">
							<div><div><div class="release-changelog">{if $release['retractionReason']}<div><h4>Retraction Reason:</h4>{$release['retractionReason']}</div><h4>Changelog:</h4>{/if}{$release['text'] ?? ''}</div></div></div>
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

	{if !empty($user) && !DISABLE_USER_TAGS}
	<dialog id="add-tag-mdl" closedby="any">
		<form class="with-buttons-bottom" method="dialog" data-method="post" autocomplete="off" action="/api/v2/mods/{$asset['modId']}/tags">
			<h1>Add Tags</h1>
			<p>While anyone can add tags to mods, everyone is also allowed to vote whether or not the tag makes sense.</p>
			<p>Tags that get downvoted will eventually be hidden, not show up in searches and get removed.</p>
			<p>The tags you want to add, separated by comma (you can add arbitrary new tags):</p>
			<div id="tag-input-wrap">
				<input type="text" name="newTags" maxlength="255" value="" autofocus>
				<div></div>
			</div>
			<input type="hidden" name="at" value="{$user['actionToken']}">
			<div class="buttons">
				<button class="button large submit shine" id="tag-subm" onclick="return false;">Add</button>
				<button class="button large shine" style="margin-left:auto;" formmethod="dialog">Cancel</button>
			</div>
		</form>
	</dialog>
	{/if}

	{if isset($user)}
	<dialog id="report-mod-mdl" closedby="any">
		<form class="with-buttons-bottom text-section" method="dialog" data-method="PUT" autocomplete="off" action="/api/v2/mods/{$asset['modId']}/report">
			<h1>Report Mod</h1>
			<div style="margin-bottom: 1em;">
				<p>Rule violation to report:</p>
				<select name="category" class="no-chosen" autofocus style="width: 100%;">
					<option value="">-- Please Select --</option>
					<? foreach(REPORT_CATEGORIES_MOD as $key => $description): ?>
					<option value="<?= $key ?>"><?= $description ?></option>
					<? endforeach; ?>
				</select>
				<p class="category-note" style="margin-top: .5em;"></p>
				<p><i>(see the <a href="/terms" target="_blank">terms of use</a> page for more details on our rules)</i></p>
			</div>
			
			<div style="margin-bottom: 1em;">
				<p>Details on how the rule is being violated:</p>
				<textarea name="reason" style="width: 100%; min-height: 10em;"></textarea>
			</div>
			
			<p><i>This report will be sent to review by moderators.</i></p>

			<div class="err-container">&nbsp;</div>
			
			<input type="hidden" name="at" value="{$user['actionToken']}">
			<div class="buttons">
				<button class="button large btndelete shine btn-submit" onclick="return false;">Report</button>
				<button class="button large shine" style="margin-left:auto;" formmethod="dialog">Cancel</button>
			</div>
		</form>
	</dialog>
	{/if}


{include file="comments"}

{capture name="footerjs"}
	<script nonce="{$cspNonce}" type="text/javascript">
		modId = {$asset['modId']};

		{if !empty($user)}
			{if !DISABLE_USER_TAGS}attachTagVoteButtons(document.getElementsByClassName('tags votable')[0], R.get('add-tag-mdl'));{/if}
			attachReportModHandlers();

			$("a[href='#follow']").click(function() {
				const oldCount = parseInt($(".count", $(this)).text());

				let promise;
				if ($(this).hasClass("on")) {
					$(this).toggleClass("on off");
					$(".count", $(this)).text("" + (oldCount - 1));

					promise = $.post(`/api/v2/settings/notifications/followed-mods/${modId}/unfollow`);
				} else {
					$(this).toggleClass("on off");
					$(".count", $(this)).text("" + (oldCount + 1));

					promise = $.post(`/api/v2/settings/notifications/followed-mods/${modId}`, \{ 'new': 1 /* @hardcoded */ });
				}

				promise.fail(jqXHR => {
					$(this).toggleClass("on off");
					$(".count", $(this)).text("" + oldCount);

					const d = JSON.parse(jqXHR.responseText);
					R.addMessage(MSG_CLASS_ERROR, 'Failed to (un-)follow mod' + (d.error ? (': '+d.error) : '.'), true)
				});
			});

			const transferForm = document.getElementsByClassName('teaminvite transfer')[0];
			if(transferForm) transferForm.addEventListener('submit', (e) => \{
				const data = new FormData(e.target, e.submitter); // before the disable

				const buttons = e.target.getElementsByTagName('button');
				for(const btn of buttons) btn.disabled = true;

				const buttonContent = e.submitter.textContent;
				startSubmissionSpinner(e.submitter);

				const promise = $.ajax(\{ url: `/api/v2/mods/${modId}/transfer`, method: 'POST', data: data, processData: false, contentType: false });
				R.attachDefaultFailHandler(promise, 'Failed '+(+e.submitter.value ? 'accept' : 'reject')+' transfer');
				promise
					.fail(() => \{
						stopSubmissionSpinner(e.submitter, buttonContent);
						for(const btn of buttons) btn.disabled = false;
					})
					.done(() => \{
						stopSubmissionSpinner(e.submitter, 'Success!');
						R.addMessage(MSG_CLASS_OK, 'Transfer '+(+e.submitter.value ? 'accepted' : 'rejected')+'.');
						window.location.reload();
					});
			});
		{/if}

		attachCommentHandlers();

		initGallery();
	</script>
{/capture}

{include file="footer"}