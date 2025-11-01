<?php

include_once $config['basepath'].'lib/file.php';

const RESERVED_URL_PREFIXES = ['api', 'home', 'terms', 'accountsettings', 'login', 'logout', 'edit-uploadfile', 'edit-deletefile', 'download', 'notifications', 'updateversiontags', 'notification', 'list', 'show', 'edit', 'moderate', 'cmd']; // :ReservedUrlPrefixes

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
			summary, descriptionSearchable, side, `type`)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
	SQL, [
		$assetId, $mod['urlAlias'], $mod['cardLogoFileId'], $mod['embedLogoFileId'],
		$mod['homepageUrl'], $mod['sourceCodeUrl'], $mod['trailerVideoUrl'], $mod['issueTrackerUrl'], $mod['wikiUrl'], $mod['donateUrl'],
		$mod['summary'], textContent($mod['text']), $mod['side'], $mod['type']
	]);
	$modId = intval($con->Insert_ID());
	logAssetChanges(["Created mod '{$mod['name']}'"], $assetId);

	// Attach hovering files to this mod. Needs to be done for new mods, as it cannot happen during upload because at that point the asset doesn't yet exist to have files attached to it.
	// Not that the attaching should happen during upload in the first place...
	foreach($filesInOrder as $i => $file) {
		$con->execute("UPDATE files SET assetId = ?, `order` = ? WHERE fileId = ?", [$assetId, $i, $file['fileId']]);
	}

	$tagsChangelog = updateModTags($modId, [], array_keys($mod['tags'])); // @perf: This could use a simpler path
	logAssetChanges($tagsChangelog, $assetId);

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

	$con->startTrans();
	
	$con->execute(
		'UPDATE assets SET statusId = ?, name = ?, text = ?, editedByUserId = ?, numSaved = numSaved + 1 WHERE assetId = ?',
		[$mod['statusId'], $mod['name'], $mod['text'], $user['userId'], $mod['assetId']]
	);
	$con->execute(<<<SQL
		UPDATE mods
		SET urlAlias = ?, cardLogoFileId = ?, embedLogoFileId = ?, 
			homepageUrl = ?, sourceCodeUrl = ?, trailerVideoUrl = ?, issueTrackerUrl = ?, wikiUrl = ?, donateUrl = ?,
			summary = ?, descriptionSearchable = ?, side = ?, `type` = ?
		WHERE modId = ?
	SQL, [
		$mod['urlAlias'], $mod['cardLogoFileId'], $mod['embedLogoFileId'],
		$mod['homepageUrl'], $mod['sourceCodeUrl'], $mod['trailerVideoUrl'], $mod['issueTrackerUrl'], $mod['wikiUrl'], $mod['donateUrl'],
		$mod['summary'], textContent($mod['text']), $mod['side'], $mod['type'],
		$mod['modId']
	]);
	logAssetChanges(["Modified mod '{$mod['name']}'"], $mod['assetId']);

	if($oldModData['statusId'] == STATUS_LOCKED) {
		$modId = intval($mod['modId']);
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

	$tagsChangelog = updateModTags($mod['modId'], $oldModData['tags'], array_keys($mod['tags']));
	logAssetChanges($tagsChangelog, $mod['assetId']);

	foreach($filesInOrder as $i => $file) {
		$con->execute("UPDATE files SET `order` = ? WHERE fileId = ?", [$i, $file['fileId']]);
	}
	
	if(canEditAsset($oldModData, $user, false)) {
		updateModTeamMembers($mod, $newMembers, $newEditorMemberHashes);

		if($mod['createdByUserId'] != $oldModData['createdByUserId']) {
			// Initiate ownership transfer:
			$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES (?, ?, ?)', [NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST, $mod['createdByUserId'], $mod['modId']]);
			logAssetChanges(["User #{$user['userId']} initiated a ownership transfer to user #{$mod['createdByUserId']}"], $mod['assetId']);
		}
	}

	$con->completeTrans();
}

/**
 * @param int $modId
 * @param array<int, array{name:string, color:string}> $oldTags
 * @param array<int> $newTagIds
 * @return array<string> changelog
 */
function updateModTags($modId, $oldTags, $newTagsIds)
{
	global $con;

	$changes = [];
	$tagData = [];

	if (!empty($newTagsIds)) {
		$addedNamesFolded = '';

		foreach ($newTagsIds as $tagId) {
			$tag = $oldTags[$tagId] ?? null;

			if($tag === null) {
				$con->execute('INSERT INTO modTags (modId, tagId) VALUES (?, ?)', [$modId, $tagId]);

				$tag = $con->getRow('SELECT name, color FROM tags WHERE tagId = ?', [$tagId]);

				if ($addedNamesFolded) $addedNamesFolded .= "', '";
				$addedNamesFolded .= $tag['name'];
			}
			else {
				unset($oldTags[$tagId]);
			}

			$tagData[] = $tag['name'] . ',#' . str_pad(dechex($tag['color']), 8, '0') . ',' . $tagId;
		}

		if ($addedNamesFolded) {
			$s = contains($addedNamesFolded, ',') ? 's' : '';
			$changes[] = "Added tag{$s} '$addedNamesFolded'.";
		}
	}

	if (!empty($oldTags)) {
		$removedTagIdsFolded = implode(',', array_keys($oldTags));
		// @security: $oldTags and its keys are obtained form the database, are numeric and therefore sql inert.
		$con->Execute("DELETE FROM modTags WHERE modId = ? AND tagId IN ($removedTagIdsFolded)", [$modId]);

		$removedTagNamesFolded = implode("', '", array_map(fn ($t) => $t['name'], $oldTags));
		$s = count($oldTags) !== 1 ? 's' : '';
		$changes[] = "Deleted tag{$s} '$removedTagNamesFolded'.";
	}

	// TODO(Rennorb) @cleanup @perf: Is tagscached really needed ?
	$con->execute('UPDATE assets a JOIN mods m ON m.assetId = a.assetId SET a.tagsCached = ? WHERE m.modId = ?', [implode("\r\n", $tagData), $modId]);

	return $changes;
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

	$changes = array();

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

				$changes[] = "User #{$user['userId']} invited user #{$newMemberId} to join the team".($editBit ? ' with edit permissions' : '').'.';
			}
			else if ($invitation['recordId'] != $mergedId) {
				$con->execute('UPDATE notifications SET recordId = ? WHERE notificationId = ?', [$mergedId, $invitation['notificationId']]);

				$changes[] = $editBit
					? "User #{$user['userId']} promoted invitation to user #{$newMemberId} to editor."
					: "User #{$user['userId']} demoted invitation to user #{$newMemberId} to normal member.";
			}
		}
		else if (boolval($oldMembers[$newMemberId]['canEdit']) !== boolval($editBit)) {
			$con->execute('UPDATE modTeamMembers SET canEdit = ? WHERE teamMemberId = ?', [$editBit ? 1 : 0, $oldMembers[$newMemberId]['teamMemberId']]);

			$changes[] = $editBit
				? "User #{$user['userId']} promoted teammember user #{$newMemberId} to editor."
				: "User #{$user['userId']} demoted teammember user #{$newMemberId} to normal member.";
		}

		unset($oldMembers[$newMemberId]);
	}

	foreach ($oldMembers as $member) {
		$con->Execute('DELETE FROM modTeamMembers WHERE teamMemberId = ?', [$member['teamMemberId']]);
		$changes[] = "User #{$user['userId']} removed teammember user #{$member['userId']}.";
	}

	logAssetChanges($changes, $mod['assetId']);
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
			kind = ".NOTIFICATION_MOD_OWNERSHIP_TRANSFER_RESOLVED." AND (recordId & ((1 << 31) - 1)) = $modId -- :PackedTransferSuccess
		) OR (
			kind IN (".NOTIFICATION_ONEOFF_MALFORMED_RELEASE.") AND recordId = $assetId
		)
	");
	$con->execute('
		UPDATE notifications n
		JOIN comments c on c.commentId = n.recordId AND kind IN ('.NOTIFICATION_NEW_COMMENT.','.NOTIFICATION_MENTIONED_IN_COMMENT.") AND c.assetId = $assetId
		SET n.`read` = 1
	");

	// FK on mods.modId takes care of modTags
	// FK on mods.modId takes care of modTeamMembers
	// FK on mods.modId takes care of userFollowedMods
	// FK on mods.modId takes care of modReleases

	$con->execute("DELETE FROM mods WHERE modId = $modId");

	//NOTE(Rennorb): We purposefully don't delete comments, as those might be interesting to moderators / audits. :NoCommentAssetFK

	$con->execute("DELETE FROM assets WHERE assetId = $assetId");

	logAssetChanges(["Deleted mod '{$mod['name']}' and all connected assets and releases."], $assetId);

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
 * @param int $assetID
 * @param int $notificationId
 */
function revokeModOwnershipTransfer($assetId, $notificationId)
{
	global $con;

	$con->startTrans();

	$con->execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$notificationId]);
	logAssetchanges(['Ownership transfer aborted'], $assetId);

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
