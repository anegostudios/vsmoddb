<?php

include_once $config['basepath'].'lib/file.php';

const RESERVED_URL_PREFIXES = ['api', 'home', 'terms', 'accountsettings', 'login', 'logout', 'edit-uploadfile', 'edit-deletefile', 'download', 'notifications', 'updateversiontags', 'notification', 'list', 'show', 'edit', 'moderate', 'cmd', 't', 'webhooks']; // :ReservedUrlPrefixes

/**
 * @param array<string, int> $newMembers
 * @param array<string, 1> $newEditorMemberHashes
 * @return int createdAssetId or zero on failure
 */
function createNewMod($mod, $filesInOrder, $newMembers, $newEditorMemberHashes)
{
	global $con, $user;

	$con->startTrans();
	
	$con->execute(
		'INSERT INTO assets (createdByUserId, statusId, assetTypeId, name, text) VALUES (?,?,?,?,?)',
		[$user['userId'], $mod['statusId'], ASSETTYPE_MOD, $mod['name'], $mod['text']]
	);
	$assetId = intval($con->Insert_ID());

	$con->execute(<<<SQL
		INSERT INTO mods
			(assetId, urlAlias, cardLogoFileId, embedLogoFileId, 
			homepageUrl, sourceCodeUrl, trailerVideoUrl, issueTrackerUrl, wikiUrl, donateUrl,
			summary, descriptionSearchable, side, category)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
	SQL, [
		$assetId, $mod['urlAlias'], $mod['cardLogoFileId'], $mod['embedLogoFileId'],
		$mod['homepageUrl'], $mod['sourceCodeUrl'], $mod['trailerVideoUrl'], $mod['issueTrackerUrl'], $mod['wikiUrl'], $mod['donateUrl'],
		$mod['summary'], textContent($mod['text']), $mod['side'], $mod['category']
	]);
	$modId = intval($con->Insert_ID());
	logAuditEvent(AUDIT_LOG_KIND_MOD_CREATE, $modId);

	// Attach hovering files to this mod. Needs to be done for new mods, as it cannot happen during upload because at that point the asset doesn't yet exist to have files attached to it.
	// Not that the attaching should happen during upload in the first place...
	foreach($filesInOrder as $i => $file) {
		$con->execute("UPDATE files SET assetId = ?, `order` = ? WHERE fileId = ?", [$assetId, $i, $file['fileId']]);
	}

	updateModTags($modId, [], array_keys($mod['tags'])); // @perf: This could use a simpler path

	updateModTeamMembers(['modId' => $modId, 'assetId' => $assetId], $newMembers, $newEditorMemberHashes);

	$ok = $con->completeTrans();

	return $ok ? $assetId : 0;
}

/**
 * @param array<string, int> $newMembers
 * @param array<string, 1> $newEditorMemberHashes
 */
function updateMod($oldModData, $mod, $filesInOrder, $newMembers, $newEditorMemberHashes)
{
	global $con, $user;

	$modId = intval($mod['modId']);

	$con->startTrans();
	
	$con->execute(
		'UPDATE assets SET statusId = ?, name = ?, text = ?, editedByUserId = ?, numSaved = numSaved + 1 WHERE assetId = ?',
		[$mod['statusId'], $mod['name'], $mod['text'], $user['userId'], $mod['assetId']]
	);
	$con->execute(<<<SQL
		UPDATE mods
		SET urlAlias = ?, cardLogoFileId = ?, embedLogoFileId = ?, 
			homepageUrl = ?, sourceCodeUrl = ?, trailerVideoUrl = ?, issueTrackerUrl = ?, wikiUrl = ?, donateUrl = ?,
			summary = ?, descriptionSearchable = ?, side = ?, category = ?
		WHERE modId = $modId
	SQL, [
		$mod['urlAlias'], $mod['cardLogoFileId'], $mod['embedLogoFileId'],
		$mod['homepageUrl'], $mod['sourceCodeUrl'], $mod['trailerVideoUrl'], $mod['issueTrackerUrl'], $mod['wikiUrl'], $mod['donateUrl'],
		$mod['summary'], textContent($mod['text']), $mod['side'], $mod['category'],
	]);

	$logFlagsGeneral = canModerate(null, $user) ? AUDIT_LOG_FLAG_MODACTION : 0; // @correctness: filter out team members.
	$logValues = [];
	if($diff = createAuditLogDiff($oldModData['urlAlias'], $mod['urlAlias'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_URL_ALIAS, $diff, $logFlagsGeneral);
	if($oldModData['cardLogoFileId'] != $mod['cardLogoFileId']) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_THUMBNAIL, null, $logFlagsGeneral);
	if($oldModData['embedLogoFileId'] != $mod['embedLogoFileId']) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_THUMBNAIL_WEB, null, $logFlagsGeneral);
	if($diff = createAuditLogDiff($oldModData['homepageUrl'], $mod['homepageUrl'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_LINK, $diff, AUDIT_LOG_FLAG_LINK_CHANGE_HOMEPAGE | $logFlagsGeneral);
	if($diff = createAuditLogDiff($oldModData['sourceCodeUrl'], $mod['sourceCodeUrl'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_LINK, $diff, AUDIT_LOG_FLAG_LINK_CHANGE_SOURCE | $logFlagsGeneral);
	if($diff = createAuditLogDiff($oldModData['trailerVideoUrl'], $mod['trailerVideoUrl'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_LINK, $diff, AUDIT_LOG_FLAG_LINK_CHANGE_TRAILER | $logFlagsGeneral);
	if($diff = createAuditLogDiff($oldModData['issueTrackerUrl'], $mod['issueTrackerUrl'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_LINK, $diff, AUDIT_LOG_FLAG_LINK_CHANGE_ISSUES | $logFlagsGeneral);
	if($diff = createAuditLogDiff($oldModData['wikiUrl'], $mod['wikiUrl'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_LINK, $diff, AUDIT_LOG_FLAG_LINK_CHANGE_WIKI | $logFlagsGeneral);
	if($diff = createAuditLogDiff($oldModData['donateUrl'], $mod['donateUrl'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_LINK, $diff, AUDIT_LOG_FLAG_LINK_CHANGE_DONATE | $logFlagsGeneral);
	if($diff = createAuditLogDiff($oldModData['summary'], $mod['summary'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_SUMMARY, $diff, $logFlagsGeneral);
	if($diff = createAuditLogDiff($oldModData['text'], $mod['text'])) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_DESCRIPTION, $diff, $logFlagsGeneral);
	if($oldModData['category'] != $mod['category']) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_CATEGORY, createAuditLogDiff(stringifyCategory($oldModData['category']), stringifyCategory($mod['category'])), $logFlagsGeneral);
	if($oldModData['statusId'] != $mod['statusId']) array_push($logValues, AUDIT_LOG_KIND_MOD_CHANGE_CATEGORY, createAuditLogDiff(stringifyStatus($oldModData['status']), stringifyStatus($mod['status'])), $logFlagsGeneral);

	if($logValues) {
		$logPlaceholders = substr(str_repeat("({$modId}, {$user['userId']}, ?, ?, ?),", count($logValues) / 3), 0, -1);
		$con->execute('INSERT INTO auditLogs (referenceId, initiatorUserId, kind, info, flags) VALUES '.$logPlaceholders, $logValues);
	}


	if($oldModData['statusId'] == STATUS_LOCKED) {
		if($mod['statusId'] != STATUS_LOCKED) {
			// Send unlock notification to the modder:
			$con->execute("INSERT INTO notifications (kind, recordId, userId) values (?, ?, ?)", [NOTIFICATION_MOD_UNLOCKED, $modId, $mod['createdByUserId']]);
			
			// Read the unlock request just in case we didn't before and only published the mod again:
			$con->execute('UPDATE notifications SET `read` = 1 WHERE kind = '.NOTIFICATION_MOD_UNLOCK_REQUEST.' AND userId = ? AND recordId = ?', [$user['userId'], $modId]);
		}
		else {
			// Send unlock request notification to the moderator:
			$moderatorUserId = $con->getOne('SELECT moderatorId FROM moderationRecords WHERE kind = '.MODACTION_KIND_LOCK." and until >= NOW() and recordId = $modId", []);
			// @security: $modId and $moderatorUserId are known to be integers and therefore sql inert.
			$requestExists = $con->getOne("SELECT 1 FROM notifications WHERE kind = ".NOTIFICATION_MOD_UNLOCK_REQUEST." AND !`read` AND recordId = $modId AND userId = $moderatorUserId");
			if(!$requestExists) { // prevent spam :BlockedUnlockRequest
				$con->execute("INSERT INTO notifications (kind, recordId, userId) VALUES (".NOTIFICATION_MOD_UNLOCK_REQUEST.", $modId, $moderatorUserId)");
			}

			// Read the lock notifications just in case we didn't before and only submitted the review-request:
			$con->execute('UPDATE notifications SET `read` = 1 WHERE kind = '.NOTIFICATION_MOD_LOCKED.' AND userId = ? AND recordId = ?', [$user['userId'], $modId]);
		}
	}

	updateModTags($modId, $oldModData['tags'], array_keys($mod['tags']));

	foreach($filesInOrder as $i => $file) {
		$con->execute("UPDATE files SET `order` = ? WHERE fileId = ?", [$i, $file['fileId']]);
	}
	
	if(canEditAsset($oldModData, $user, false)) {
		updateModTeamMembers($mod, $newMembers, $newEditorMemberHashes);

		if($mod['createdByUserId'] != $oldModData['createdByUserId']) {
			// Initiate ownership transfer:
			$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES (?, ?, ?)', [NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST, $mod['createdByUserId'], $modId]);
			logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_OWNER_INITIATED, $modId, "{$mod['createdByUserId']}");
		}
	}

	$con->completeTrans();
}

/**
 * @param int $modId
 * @param array<int, array{name:string, color:string}> $oldTags
 * @param array<int> $newTagIds
 */
function updateModTags($modId, $oldTags, $newTagsIds)
{
	global $con, $user;

	$oldNames = array_column($oldTags, 'name'); sort($oldNames);
	if($newTagsIds) {
		$idsFolded = implode(',', array_map('intval', $newTagsIds));
		$newNames = $con->getCol("SELECT name FROM tags WHERE tagId IN ($idsFolded) ORDER BY name ASC");
		$logNew = formatGrammaticallyCorrectEnumeration($newNames);
	}
	else {
		$logNew = '';
	}
	$diff = createAuditLogDiff(formatGrammaticallyCorrectEnumeration($oldNames), $logNew);
	if(!$diff) return;

	foreach ($newTagsIds as $tagId) {
		if(!array_key_exists($tagId, $oldTags)) {
			$con->execute(<<<SQL
				INSERT INTO modTags (modId, tagId, votes)
					VALUES (?, ?, ?)
				ON DUPLICATE KEY UPDATE
					votes = votes + VALUES(votes)
			SQL, [$modId, $tagId, TAG_MODAUTHOR_VOTES]);

			$con->execute(<<<SQL
				INSERT INTO modTagVotes (modId, tagId, userId, vote)
					VALUES (?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					vote = VALUES(vote)
			SQL, [$modId, $tagId, $user['userId'], TAG_MODAUTHOR_VOTES]);
		}
		else {
			unset($oldTags[$tagId]);
		}
	}

	if (!empty($oldTags)) {
		$removedTagIdsFolded = implode(',', array_keys($oldTags));
		// @security: $oldTags and its keys are obtained form the database, are numeric and therefore sql inert.
		$con->Execute("DELETE FROM modTags WHERE modId = ? AND tagId IN ($removedTagIdsFolded)", [$modId]);
		$con->Execute("DELETE FROM modTagVotes WHERE modId = ? AND tagId IN ($removedTagIdsFolded)", [$modId]);
	}

	$logFlags = canModerate(null, $user) ? AUDIT_LOG_FLAG_MODACTION : 0; // @correctness: needs to ignore team members
	logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_TAGS, $modId, $diff, $logFlags);
}

/**
 * @param array{modId:int, assetId:int} $mod
 * @param array<string, int> $newMembers
 * @param array<string, 1> $newEditorMemberHashes
 */
function updateModTeamMembers($mod, $newMembers, $newEditorMemberHashes)
{
	global $con, $user;

	$oldMembers = $con->getAll('SELECT HEX(u.hash) AS hash, t.userId, t.canEdit, t.teamMemberId FROM modTeamMembers t JOIN users u ON u.userId = t.userId WHERE t.modId = ?', [$mod['modId']]);
	$oldMembers = array_combine(array_column($oldMembers, 'userId'), $oldMembers);

	$logCommonFlags = canModerate(null, $user) ? AUDIT_LOG_FLAG_MODACTION : 0;
	$logValues = [];

	foreach ($newMembers as $newMemberHash => $newMemberId) {
		//NOTE(Rennorb) @hack: We use the highest possible bit (#31) to indicate that this invitation should resolve with editor permissions.
		// We do this to simplify the teammebers table, as there currently is not complex permission system and we would otherwise need several more columns to keep track of this.
		// :InviteEditBit
		$editBit = array_key_exists($newMemberHash, $newEditorMemberHashes) ? 1 << 30 : 0;
		$mergedId = intval($mod['modId']) | $editBit;

		if (!array_key_exists($newMemberId, $oldMembers)) {
			$invitation = $con->getRow('SELECT notificationId, recordId FROM notifications WHERE kind = '.NOTIFICATION_TEAM_INVITE.' AND !`read` AND userId = ? AND (recordId & ((1 << 30) - 1)) = ?', [$newMemberId, $mod['modId']]);
			if(empty($invitation)) {
				$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES ('.NOTIFICATION_TEAM_INVITE.', ?, ?)', [$newMemberId, $mergedId]);

				array_push($logValues, AUDIT_LOG_KIND_MOD_MEMBER_INVITE_INITIATED, "$newMemberId", ($editBit ? AUDIT_LOG_FLAG_WITH_EDIT_PERMISSIONS : 0) | $logCommonFlags);
			}
			else if ($invitation['recordId'] != $mergedId) {
				$con->execute('UPDATE notifications SET recordId = ? WHERE notificationId = ?', [$mergedId, $invitation['notificationId']]);

				array_push($logValues, AUDIT_LOG_KIND_MOD_MEMBER_INVITE_CHANGED, "$newMemberId", ($editBit ? AUDIT_LOG_FLAG_WITH_EDIT_PERMISSIONS : 0) | $logCommonFlags);
			}
		}
		else if (boolval($oldMembers[$newMemberId]['canEdit']) !== boolval($editBit)) {
			$con->execute('UPDATE modTeamMembers SET canEdit = ? WHERE teamMemberId = ?', [$editBit ? 1 : 0, $oldMembers[$newMemberId]['teamMemberId']]);

			array_push($logValues, AUDIT_LOG_KIND_MOD_MEMBER_PERMISSION_CHANGED, "$newMemberId", ($editBit ? AUDIT_LOG_FLAG_WITH_EDIT_PERMISSIONS : 0) | $logCommonFlags);
		}

		unset($oldMembers[$newMemberId]);
	}

	foreach ($oldMembers as $member) {
		$con->Execute('DELETE FROM modTeamMembers WHERE teamMemberId = ?', [$member['teamMemberId']]);

		array_push($logValues, AUDIT_LOG_KIND_MOD_MEMBER_REMOVED, "{$member['userId']}", ($member['canEdit'] ? AUDIT_LOG_FLAG_WITH_EDIT_PERMISSIONS : 0) | $logCommonFlags);
	}

	if($logValues) {
		$placeholders = substr(str_repeat("({$mod['modId']}, {$user['userId']}, ?, ?, ?),", count($logValues) / 3), 0, -1);
		$con->Execute("INSERT INTO auditLogs (referenceId, initiatorUserId, kind, info, flags) VALUES $placeholders", $logValues);
	}
}

/** Deletes the given mod and all attached assets and releases
 * @param array{assetId:int, modId:int, name:string} $assetId
 * @return bool success
 */
function deleteMod($mod)
{
	global $con;

	// @security: Make sure these ids are inert so we can use them directly in the queries
	// -> no need to prepare all those delete queries and wrangle multiple bind parameters.
	$modId = intval($mod['modId']);
	$assetId = intval($mod['assetId']);

	$con->startTrans();

	// Remove any attached files:
	$filesInOrder = $con->getAll(<<<SQL
		SELECT f.fileId, f.name, f.assetId, f.cdnPath, d.hasThumbnail, f.assetTypeId
		FROM files f
		LEFT JOIN fileImageData d ON d.fileId = f.fileId
		WHERE f.assetId = $assetId
	UNION
		SELECT f.fileId, f.name, f.assetId, f.cdnPath, d.hasThumbnail, f.assetTypeId
		FROM files f
		LEFT JOIN fileImageData d ON d.fileId = f.fileId
		JOIN modReleases r ON r.assetId = f.assetId AND r.modId = $modId
	SQL);
	tryDeleteFiles($filesInOrder);

	$con->execute("DELETE FROM modCompatibleGameVersionsCached WHERE modId = $modId");
	$con->execute("DELETE FROM modCompatibleMajorGameVersionsCached WHERE modId = $modId");
	$releaseIds = $con->getCol("SELECT releaseID FROM modReleases WHERE modId = $modId");
	if($releaseIds) {
		$idsFolded = '('.implode(',', $releaseIds).')';
		// @security: $idsFolded comes form the database and is known to be integers, therefore sql inert.
		$con->execute("DELETE FROM modReleaseCompatibleGameVersions WHERE releaseId in $idsFolded");
	}

	// Read all notifications that directly link to this mod being deleted, or its comments:
	$con->execute('
		UPDATE notifications
		SET `read` = 1
		WHERE (
			kind IN ('.
		NOTIFICATION_MOD_LOCKED.','.NOTIFICATION_MOD_UNLOCK_REQUEST.','.NOTIFICATION_MOD_UNLOCKED.','.
		NOTIFICATION_NEW_RELEASE
			.") AND recordId = $modId
		) OR (
			kind IN (".NOTIFICATION_TEAM_INVITE.','.NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST.") AND (recordId & ((1 << 30) - 1)) = $modId -- :InviteEditBit
		) OR (
			kind = ".NOTIFICATION_MOD_OWNERSHIP_TRANSFER_RESOLVED." AND (recordId & ((1 << 30) - 1)) = $modId -- :PackedTransferSuccess
		) OR (
			kind IN (".NOTIFICATION_ONEOFF_MALFORMED_RELEASE.") AND recordId = $assetId
		)
	");
	$con->execute('
		UPDATE notifications n
		JOIN comments c on c.commentId = n.recordId AND kind IN ('.NOTIFICATION_NEW_COMMENT.','.NOTIFICATION_MENTIONED_IN_COMMENT.','.NOTIFICATION_RESPONDED_TO_COMMENT.") AND c.assetId = $assetId
		SET n.`read` = 1
	");

	// FK on mods.modId takes care of modTags
	// FK on mods.modId takes care of modTeamMembers
	// FK on mods.modId takes care of userFollowedMods
	// FK on mods.modId takes care of modReleases

	$con->execute("DELETE FROM mods WHERE modId = $modId");

	//NOTE(Rennorb): We purposefully don't delete comments, as those might be interesting to moderators / audits. :NoCommentAssetFK

	$con->execute("DELETE FROM assets WHERE assetId = $assetId");

	logAuditEvent(AUDIT_LOG_KIND_MOD_DELETE, $modId);

	return $con->completeTrans();
}

/**
 * @param int $modId
 * @return array{userId:int, name:string, notificationId:int} user - Empty if not being transferred.
 */
function modCurrentlyBeingTransferredTo($modId)
{
	global $con;
	return $con->getRow(<<<SQL
		SELECT u.userId, u.name, n.notificationId
		FROM notifications AS n
		JOIN users u ON u.userId = n.userId
		WHERE n.kind = ? AND n.recordId = ? AND !n.`read`
	SQL, [NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST, $modId]);
}

/**
 * @param int $modId
 * @param int $notificationId
 */
function revokeModOwnershipTransfer($modId, $notificationId)
{
	global $con;

	$con->startTrans();

	$con->execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$notificationId]);
	logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_OWNER_RESOLVED, $modId, null, AUDIT_LOG_FLAG_ABORTED);

	$con->completeTrans();
}

/**
 * @param int $modId
 * @param int $userId
 * @return bool
 */
function isTeamMember($modId, $userId)
{
	global $con;
	return (bool)$con->getOne('SELECT 1 FROM modTeamMembers WHERE modId = ? AND userId = ?', [$modId, $userId]);
}
