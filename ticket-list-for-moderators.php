<?php
/** @var object $con */
/** @var object $view */
/** @var array<string> $urlparts */

if(empty($user['userId']))   showErrorPage(HTTP_UNAUTHORIZED);
if(!canModerate(null, $user))   showErrorPage(HTTP_FORBIDDEN);

require(SRC_ROOT.'/lib/moderation.php');

///
/// Mods
///

$modReports = $con->getAll("
	SELECT requestId, kind, category, referenceId,
		IF(LENGTH(requestSearchable) > 256, CONCAT(SUBSTR(requestSearchable, 1, 256), '...'), requestSearchable) AS requestSearchable
	FROM moderationRequests
	WHERE kind = ".MOD_REQUEST_KIND_REPORT_MOD." AND category != ".REPORT_CATEGORY_MOD_LOW_EFFORT_AI." AND (~stateFlags & ".MOD_REQUEST_FLAG_CLOSED.") 
	ORDER BY referenceId, created DESC
");

$reportedModIds = array_unique(array_column($modReports, 'referenceId'), SORT_NUMERIC);
$foldedModIds = implode(',', $reportedModIds);

$reportedMods = $reportedModIds ? $con->getAssoc(<<<SQL
	SELECT m.modId, a.assetId, m.urlAlias, a.name, m.summary, f.cdnPath AS logoCdnPath
	FROM mods m
	JOIN assets a ON a.assetId = m.assetId
	LEFT JOIN files f ON f.fileId = m.cardLogoFileId
	WHERE m.modId IN ($foldedModIds)
SQL) : [];

foreach($modReports as $report) {
	$reportedMods[$report['referenceId']]['reports'][] = $report;
}

usort($reportedMods, fn($a, $b) => -(count($a['reports']) <=> count($b['reports']))); // sort by report count descending


///
/// Comments
///


$commentReports = $con->getAll("
	SELECT requestId, kind, category, referenceId,
		IF(LENGTH(requestSearchable) > 256, CONCAT(SUBSTR(requestSearchable, 1, 256), '...'), requestSearchable) AS requestSearchable
	FROM moderationRequests
	WHERE kind = ".MOD_REQUEST_KIND_REPORT_COMMENT." AND (~stateFlags & ".MOD_REQUEST_FLAG_CLOSED.") 
	ORDER BY referenceId, created DESC
");

$reportedCommentIds = array_unique(array_column($commentReports, 'referenceId'), SORT_NUMERIC);
$foldedCommentIds = implode(',', $reportedCommentIds);

$reportedComments = $reportedCommentIds ? $con->getAssoc(<<<SQL
	SELECT c.commentId AS 'key', c.commentId, a.assetId, m.urlAlias, a.name AS modName, c.textShort,
		HEX(u.hash) AS 'userHash', u.name as 'userName'
	FROM comments c
	JOIN mods m ON m.assetId = c.assetId
	JOIN assets a ON a.assetId = m.assetId
	LEFT JOIN users u ON u.userId = c.userId
	WHERE c.commentId IN ($foldedCommentIds)
SQL) : [];

foreach($commentReports as $report) {
	$reportedComments[$report['referenceId']]['reports'][] = $report;
}

usort($reportedComments, fn($a, $b) => -(count($a['reports']) <=> count($b['reports']))); // sort by report count descending

/// Done

$view->assign('pagetitle', 'Open Moderation Requests - ');
$view->assign('reportedMods', $reportedMods, null, true);
$view->assign('reportedComments', $reportedComments, null, true);
$view->display('ticket-list-for-moderators');
