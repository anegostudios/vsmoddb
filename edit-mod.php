<?php

include_once $config['basepath'].'lib/mod.php';

const SAVE_MSG_DEFAULT = '1';
const SAVE_MSG_REVERTED = '2';
const SAVE_MSG_TRANSFER_INITIATED = '3';
const SAVE_MSG_TRANSFER_REVOKED = '4';


$teamMembers = [];

if(isset($_GET['assetid'])) {
	$assetId = filter_input(INPUT_GET, 'assetid', FILTER_VALIDATE_INT);
	if(!$assetId) showErrorPage(HTTP_BAD_REQUEST, 'Missing assetid');

	// @security: $assetId is known to be an integer and therefore sql inert.
	$mod = $con->getRow(<<<SQL
		SELECT m.modId, m.assetId, a.name, m.urlAlias, m.summary, a.text, a.statusId, m.type, m.side, a.createdByUserId,
			m.homepageUrl, m.sourceCodeUrl, m.trailerVideoUrl, m.issueTrackerUrl, m.wikiUrl, m.donateUrl, m.created,
			m.cardLogoFileId, fileDb.cdnPath AS logoCdnPath,
			m.embedLogoFileId, fileExternal.cdnPath AS logoCdnPathExternal
		FROM mods m
		JOIN assets a ON a.assetId = m.assetId
		LEFT JOIN files AS fileDb ON fileDb.fileId = m.cardLogoFileId
		LEFT JOIN files AS fileExternal ON fileExternal.fileId = m.embedLogoFileId
		WHERE m.assetId = $assetId
	SQL);

	if(!$mod) showErrorPage(HTTP_NOT_FOUND);

	$mod['assetTypeId'] = ASSETTYPE_MOD;
	if(!canEditAsset($mod, $user)) showErrorPage(HTTP_FORBIDDEN);

	$mod['tags'] = $con->getAssoc('SELECT t.tagId, t.name, t.color FROM modTags mt JOIN tags t ON t.tagId = mt.tagId WHERE modId = ?', $mod['modId']);

	$canEditAsOwner = canEditAsset($mod, $user, false);
	if($canEditAsOwner) {
		$teamMembers = $con->getAll('
			SELECT u.*, HEX(u.hash) AS hash, t.canEdit, 0 AS pending
			FROM modTeamMembers t
			JOIN users u ON u.userId = t.userId
			WHERE t.modId = ? AND u.userId != ?
		UNION
			SELECT u.*, HEX(u.hash) AS hash, (n.recordId & 1 << 30) AS canEdit, 1 AS pending
			FROM notifications n
			JOIN users u ON u.userId = n.userId
			WHERE n.kind = '.NOTIFICATION_TEAM_INVITE.' AND !n.`read` AND (n.recordId & ((1 << 30) - 1)) = ? -- :InviteEditBit
		', [$mod['modId'], $user['userId'], $mod['modId']]);

		// We always want the data if we can edit this so we can show the name in the revocation screen (and show that revocation screen).
		$currentlyBeingTransferredTo = modCurrentlyBeingTransferredTo($mod['modId']);
	}

	$filesInOrder = $con->getAll(<<<SQL
		SELECT f.*, i.hasThumbnail, CONCAT(ST_X(i.size), 'x', ST_Y(i.size)) AS imageSize
		FROM files f
		LEFT JOIN fileImageData i ON i.fileId = f.fileId
		WHERE assetId = ?
		ORDER BY `order` ASC
	SQL, [$mod['assetId']]);
}
else { // New mod
	$assetId = 0;
	$mod = [
		'assetId'         => 0,
		'modId'           => 0,
		'assetTypeId'     => ASSETTYPE_MOD,
		'statusId'        => STATUS_DRAFT,
		'type'            => 'mod',
		'side'            => 'both',
		'name'            => '',
		'summary'         => '',
		'text'            => '',
		'urlAlias'        => null,
		'homepageUrl'     => null,
		'sourceCodeUrl'   => null,
		'trailerVideoUrl' => null,
		'issueTrackerUrl' => null,
		'wikiUrl'         => null,
		'donateUrl'       => null,
		'createdByUserId' => $user['userId'],
		'cardLogoFileId'  => null,
		'embedLogoFileId' => null,
		'tags'            => [],
	];
	$canEditAsOwner = true;
	$currentlyBeingTransferredTo = [];
	$filesInOrder = getHoveringFilesOfUser($user['userId'], ASSETTYPE_MOD);
}

$stati = [
	STATUS_DRAFT     => 'Draft',
	STATUS_RELEASED  => 'Published',
	//STATUS_LOCKED    => 'Locked',
];
if($mod['statusId'] == STATUS_LOCKED) $stati[STATUS_LOCKED] = 'Locked';

$modTypes = [
	'mod'          => 'Game mod',
	'externaltool' => 'External tool',
	'other'        => 'Other',
];

$modSidedness = [
	'both'   => 'Client and Server side mod',
	'server' => 'Server side only mod',
	'client' => 'Client side only mod',
];

//
// Validate
//

// Check revokenewownership first because its lumped in with the other fields, including submit=1:
if(isset($_POST['revokenewownership'])) {
	validateActionToken();
	$oldMsgCount = count($messages /* global */);

	if(!$currentlyBeingTransferredTo) {
		addMessage(MSG_CLASS_ERROR, 'Ownership transfer revocation requested but this mod is not currently being transferred.');
	}
}
else if(!empty($_POST['save'])) {
	validateActionToken();
	$oldMsgCount = count($messages /* global */);
	//NOTE(Rennorb): We will be reusing the $mod array so we can present the input to the user when a mistake is made.
	// This way they don't have to re-input everything, and can just adjust their input.
	$oldModData = $mod;

	$mod['statusId'] = intval($_POST['statusId']);
	if(!in_array($mod['statusId'], array_keys($stati), true)) {
		addMessage(MSG_CLASS_WARN, "The new status is not valid and has been reset.");
		$mod['statusId'] = $oldModData['statusId'];
	}
	if($oldModData['statusId'] == STATUS_LOCKED) {
		if($mod['statusId'] !== STATUS_LOCKED) {
			if(!canModerate(null, $user)) {
				addMessage(MSG_CLASS_ERROR, "Only moderators may change the state of a locked mod.");
				$mod['statusId'] = STATUS_LOCKED;
			}
		}
	}

	$mod['type'] = $_POST['type'];
	if(!in_array($mod['type'], array_keys($modTypes), true)) {
		addMessage(MSG_CLASS_WARN, "The new mod type is not valid and has been reset.");
		$mod['type'] = $oldModData['type'];
	}

	$mod['side'] = $_POST['side'];
	if(!in_array($mod['side'], array_keys($modSidedness), true)) {
		addMessage(MSG_CLASS_WARN, "The new mod side is not valid and has been reset.");
		$mod['type'] = $oldModData['side'];
	}

	$tags = getInputArrayOfInts(INPUT_POST, 'tagids');
	if($tags === null) { $tags = []; }
	if($tags === false) {
		addMessage(MSG_CLASS_WARN, "The new mod tags contain invalid tags.");
	}
	else {
		//NOTE(Rennorb): This is missing the name and color fields, but for new values we only need the keys to be correct so don't bother getting those.
		$mod['tags'] = array_flip($tags);
	}

	$mod['name'] = $_POST['name'];
	if(strlen($mod['name']) > 255) { // matches db setup (VARCHAR(255))
		//NOTE(Rennorb): No mb_strlen here, the database uses latin1, so the byte length counts....
		//TODO(Rennorb) @cleanup @correctness: :UpgradeDatabaseToUTF8
		addMessage(MSG_CLASS_WARN, "The name was too long and was truncated.");
		$mod['name'] = substr($_POST['name'], 0, 255);
	}

	$mod['summary'] = mb_substr($_POST['summary'], 0, 100);
	if(mb_strlen($_POST['summary']) > 100)  addMessage(MSG_CLASS_WARN, "The summary was to long and was truncated.");

	$wasChanged = false; // Used to not spam errors when chaining automatic changes.
	$mod['urlAlias'] = preg_replace("/[^0-9a-z]+/", "", strtolower($_POST['urlAlias']));
	if($mod['urlAlias'] !== $_POST['urlAlias']) {
		addMessage(MSG_CLASS_WARN, "The url alias contained invalid characters that were automatically stripped or converted. Only lower-case alphanumerics (0-9a-z) are allowed.");
		$wasChanged = true;
	}
	if(strlen($mod['urlAlias']) > 45) { // matches database setup (VARCHAR(45))
		addMessage(MSG_CLASS_WARN, "The url alias was too long and was truncated.");
		$mod['urlAlias'] = substr($mod['urlAlias'], 0, 45);
		$wasChanged = true;
	}
	if($mod['urlAlias'] === '') $mod['urlAlias'] = null;
	if(!$wasChanged && in_array($mod['urlAlias'], RESERVED_URL_PREFIXES)) {
		addMessage(MSG_CLASS_ERROR, 'This url alias is reserved word. Please choose another.');
	}
	else if(!$wasChanged && $mod['urlAlias']) {
		$collidingMod = $con->getOne('SELECT urlAlias FROM mods WHERE urlAlias = ? and modId != ?', [$mod['urlAlias'], $mod['modId']]);
		if($collidingMod) {
			// @security: urlAlias must only contain alphanumeric characters, so its safe to output.
			addMessage(MSG_CLASS_ERROR, "Not saved. This url alias is <a href='/{$collidingMod['urlAlias']}' target='_blank'>already taken</a>. Please choose another.");
		}
	}

	//TODO(Rennorb) @ux: Feedback
	$mod['text'] = trim(sanitizeHtml($_POST['text']));

	$textLen = strlen($mod['text']);
	if($textLen > 65535) { // TEXT column max length in assets.text
		$sizeKb = floor($textLen / 1024);
		$reason = "Excessive size ({$sizeKb}KB).";
		if(contains($mod['text'], 'src="data:image')) $reason .= " You cannot paste large images directly. If you need a large image, upload it to an external site and link to that.";
		addMessage(MSG_CLASS_ERROR, $reason);
	}

	// We don't want to revert the urls here, that would be rather inconvenient.
	$url = $mod['homepageUrl'] = trim($_POST['homepageUrl']);
	if($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false)
		addMessage(MSG_CLASS_ERROR, 'Hompage Url is not valid.');

	$url = $mod['sourceCodeUrl'] = trim($_POST['sourceCodeUrl']);
	if($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false)
		addMessage(MSG_CLASS_ERROR, 'Source Code Url is not valid.');

	$url = $mod['trailerVideoUrl'] = trim($_POST['trailerVideoUrl']);
	if($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false)
		addMessage(MSG_CLASS_ERROR, 'Trailer Video Url is not valid.');

	$url = $mod['issueTrackerUrl'] = trim($_POST['issueTrackerUrl']);
	if($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false)
		addMessage(MSG_CLASS_ERROR, 'Issue Tracker Url is not valid.');

	$url = $mod['wikiUrl'] = trim($_POST['wikiUrl']);
	if($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false)
		addMessage(MSG_CLASS_ERROR, 'Wiki Url is not valid.');

	$url = $mod['donateUrl'] = trim($_POST['donateUrl']);
	if($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false)
		addMessage(MSG_CLASS_ERROR, 'Donate Url is not valid.');


	// Team Members:
	if($canEditAsOwner) {
		$newMemberHashes = filter_input(INPUT_POST, 'teammemberids', FILTER_UNSAFE_RAW, FILTER_FORCE_ARRAY | FILTER_FLAG_STRIP_LOW) ?? [];
		if(empty($newMemberHashes)) {
			$newMembers = [];
		}
		else{
			$placeholders = implode(',', array_fill(0, count($newMemberHashes), '?'));
			$newMembers = $con->getAssoc("SELECT HEX(hash) AS hash, userId FROM users where HEX(users.hash) IN ($placeholders)", $newMemberHashes);
			if(count($newMembers) !== count($newMemberHashes))  addMessage(MSG_CLASS_WARN, "One ore more specified team-members were not found.");
			foreach($newMembers as $memberId) {
				if($memberId == $mod['createdByUserId']) {
					addMessage(MSG_CLASS_WARN, "You cannot add the owner of the mod as a team member.");
					break;
				}
			} 
		}

		$newEditorMemberHashes = filter_input(INPUT_POST, 'teammembereditids', FILTER_UNSAFE_RAW, FILTER_FORCE_ARRAY | FILTER_FLAG_STRIP_LOW) ?? [];
		$newEditorMemberHashes = array_flip($newEditorMemberHashes);
	}
	else {
		$newMembers = [];
		$newEditorMemberHashes = [];
	}

	$fileOrder = getInputArrayOfInts(INPUT_POST, 'fileIds');
	if($fileOrder) do {
		if(count($fileOrder) !== count($filesInOrder)) {
			addMessage(MSG_CLASS_ERROR, "Error trying to set image order, mismatch between client and server.");
			break;
		}

		// Validate that the image ids supplied for ordering actually belong to this mod:
		$filtered = [];
		foreach($fileOrder as $i => $fileId) {
			foreach($filesInOrder as &$file) {
				if($file['fileId'] == $fileId) {
					$file['order'] = intval($i);
					$filtered[] = $fileId;
					break;
				}
			}
			unset($file);
		}

		if(count($filtered) !== count($fileOrder)) {
			addMessage(MSG_CLASS_ERROR, "Error trying to set image order, mismatch between client and server.");
			break;
		}

		usort($filesInOrder, fn($f1, $f2) => $f1['order'] - $f2['order']);
	} while(0);


	// Images:
	$mod['cardLogoFileId']  = intval($_POST['cardLogoFileId'])  ?: null;
	$mod['embedLogoFileId'] = intval($_POST['embedLogoFileId']) ?: null;

	$wasChanged = false; // Used to prevent auto-cropping in case we adjusted the user selection.
	 // If nothing is selected we don't need to find it.
	$foundDbImage = !$mod['cardLogoFileId'];
	$foundExternalImage = !$mod['embedLogoFileId'];
	$dbImage = [];
	foreach($filesInOrder as $file) {
		if($file['fileId'] === $mod['cardLogoFileId']) {
			$foundDbImage = true;

			if($file['imageSize'] !== '480x320' && $file['imageSize'] !== '480x480') {
				addMessage(MSG_CLASS_WARN, 'The selected ModDB Logo image has the wrong dimension, your selection has been reverted.');
				$mod['cardLogoFileId'] = $oldModData['cardLogoFileId'];
				$wasChanged = true;
			}
			else {
				$dbImage = $file;
			}

			if($foundExternalImage) break;
			// In case both images are the same we don't need to check that again, but we do need to flag it as found.
			if($file['fileId'] === $mod['embedLogoFileId']) $foundExternalImage = true;
		}
		else if($file['fileId'] === $mod['embedLogoFileId']) {
			$foundExternalImage = true;

			if($file['imageSize'] !== '480x320' && $file['imageSize'] !== '480x480') {
				addMessage(MSG_CLASS_WARN, 'The selected External Logo image has the wrong dimension, your selection has been reverted.');
				$mod['embedLogoFileId'] = $oldModData['embedLogoFileId'];
				$wasChanged = true;
			}

			if($foundDbImage) break;
		}
	}
	if(!$foundDbImage) {
		addMessage(MSG_CLASS_WARN, 'The selected ModDB Logo image is not associated with this mod, your selection has been reverted.');
		$mod['cardLogoFileId'] = $oldModData['cardLogoFileId'];
		$wasChanged = true;
	}
	if(!$foundExternalImage) {
		addMessage(MSG_CLASS_WARN, 'The selected External Logo image is not associated with this mod, your selection has been reverted.');
		$mod['embedLogoFileId'] = $oldModData['embedLogoFileId'];
		$wasChanged = true;
	}

	// If the modder doesn't explicitly select a external logo, but we have a moddb logo they want the auto-cropped image:
	if(!$wasChanged && !$mod['embedLogoFileId'] && $mod['cardLogoFileId']) do {
		if($dbImage['imageSize'] === '480x320') {
			// No cropping needed, can just use the image
			$mod['embedLogoFileId'] = $dbImage['fileId'];
			break;
		}

		splitOffExtension($dbImage['name'], $filebasename, $ext);
		$croppedFilename = "{$filebasename}_480_320.{$ext}";

		// Test for existing cropped image, so we don't create duplicates:
		if($mod['assetId']) {
			$candidate = $con->getOne(<<<SQL
				SELECT f.fileId
				FROM files f
				JOIN fileImageData i ON i.fileId = f.fileId
				WHERE f.assetId = ? AND i.size = POINT(480, 320) AND f.name = ?
			SQL, [$mod['assetId'], $croppedFilename]);
			if($candidate) {
				$mod['embedLogoFileId'] = $candidate;
				break;
			}
		}
		
		// We have to do the actual crop.

		// Since we don't have the files locally anymore we unfortunately have to do this stunt and re-download the image thats supposed to be used as a logo.
		// Upload happens asynchronously during drag-n-drop, so when the user saves the asset the files already don't exist locally anymore.
		// Since changing the logo is not a action repeated very often this is ok for now, especially since the alternative would be to keep files around, but not abandon them if the user just navigates away from the asset editor, which is non-trivial.

		$originalFileLocalPath = tempnam(sys_get_temp_dir(), '');
		$originalFileContents = @file_get_contents(formatCdnUrl($dbImage));
		if (!file_put_contents($originalFileLocalPath, $originalFileContents)) {
			@unlink($originalFileLocalPath);
			addMessage(MSG_CLASS_ERROR, 'The selected ModDB Logo image seems to no longer exist.');
			break;
		}

		$croppedFileLocalPath = tempnam(sys_get_temp_dir(), '');
		$cropResult = cropImage($originalFileLocalPath, $croppedFileLocalPath, 0, 0, 480, 320);
		unlink($originalFileLocalPath);

		if(!$cropResult) {
			unlink($croppedFileLocalPath);
			addMessage(MSG_CLASS_ERROR, 'Failed to crop image.');
			break;
		}

		splitOffExtension($dbImage['cdnPath'], $ogCdnBasePath, $ext);
		$cdnBasePath = "{$ogCdnBasePath}_480_320";

		$thumbStatus = createThumbnailAndUploadToCDN($croppedFileLocalPath, $cdnBasePath, $ext);

		if($thumbStatus['status'] !== 'ok') {
			unlink($croppedFileLocalPath);
			addMessage(MSG_CLASS_ERROR, 'CDN Error while uploading cropped image thumbnail: '.$thumbStatus['errormessage'], true);
			break;
		}


		$cdnPath = "$cdnBasePath.$ext";
		$uploadResult = uploadToCdn($croppedFileLocalPath, $cdnPath);
		unlink($croppedFileLocalPath);

		if($uploadResult['error']) {
			addMessage(MSG_CLASS_ERROR, 'CDN Error while uploading cropped image: '.$uploadResult['error'], true);
			break;
		}

		$con->startTrans();

		$con->execute(
			'INSERT INTO files (assetId, assetTypeId, userId, name, cdnPath, `order`) VALUES (?, ?, ?, ?, ?, ?)',
			[$dbImage['assetId'], ASSETTYPE_MOD, $dbImage['userId'], $croppedFilename, $cdnPath, count($filesInOrder)]
		);
		$fileId = $con->Insert_ID();
		$con->execute('INSERT INTO fileImageData (fileId, hasThumbnail, size) VALUES (?, 1, POINT(480, 320))', [$fileId]);

		$con->completeTrans();

		$mod['embedLogoFileId'] = $fileId;
	} while(0);

	$saveCookie = SAVE_MSG_DEFAULT;

	// Don't release without files:
	if($mod['statusId'] === STATUS_RELEASED) {
		if(!$oldModData['modId'] || !$con->getOne('SELECT releaseId FROM modReleases WHERE modId = ?', [$mod['modId']])) {
			$mod['statusId'] = STATUS_DRAFT;
			// Using a cookie here because the message needs to be displayed at the next load of the page after the bounce.
			// No message here, as this would get picked up as a hard error.
			$saveCookie = SAVE_MSG_REVERTED;
		}
	}

	// Ownership Transfers:
	if($oldModData['modId'] && $canEditAsOwner) { // Can only transfer if the mod already existed before this.
		$newOwnerId = filter_input(INPUT_POST, 'newownerid', FILTER_VALIDATE_INT);
		if($newOwnerId) {
			if($currentlyBeingTransferredTo) {
				addMessage(MSG_CLASS_ERROR, 'An invitation to transfer ownership has already been sent to '.($currentlyBeingTransferredTo['userId'] == $newOwnerId ? 'this user.' : "'{$currentlyBeingTransferredTo['name']}'."), true);
			}
			else if(!isTeamMember($mod['modId'], $newOwnerId)) {
				addMessage(MSG_CLASS_ERROR, 'The user selected for ownership transfer is not a team member.');
			}
			else {
				$mod['createdByUserId'] = $newOwnerId;
				$saveCookie = SAVE_MSG_TRANSFER_INITIATED;
			}
		}
	}
}
else if(!empty($_POST["delete"])) {
	validateActionToken();
}

//
// Execute
//

// Check revokenewownership first because its lumped in with the other fields, including submit=1:
if(isset($_POST['revokenewownership'])) {
	if(count($messages /* global */) === $oldMsgCount) { // no errors occurred
		revokeModOwnershipTransfer($mod['assetId'], $currentlyBeingTransferredTo['notificationId']);
		
		setcookie('saved', SAVE_MSG_TRANSFER_REVOKED);
		forceRedirectAfterPOST();
		exit();
	}
}
else if(!empty($_POST['save'])) {
	if(count($messages /* global */) === $oldMsgCount) { // no errors occurred
		if($mod['modId']) {
			updateMod($oldModData, $mod, $filesInOrder, $newMembers, $newEditorMemberHashes);

			setcookie('saved', $saveCookie);
			forceRedirectAfterPOST();
			exit();
		}
		else {
			$assetId = createNewMod($mod, $filesInOrder, $newMembers, $newEditorMemberHashes);

			setcookie('saved', $saveCookie);
			forceRedirect('/edit/mod/?assetid='.$assetId);
			exit();
		}
	}
	else {
		addMessage(MSG_CLASS_ERROR, 'There were issues when saving the mod (see previous messages). Any changes to team members have also been reset. Review the data and save again.');
	}
}
else if(!empty($_POST['delete'])) {
	deleteMod($mod['modId']);
	forceRedirect('/list/mod?deleted=1');
	exit();
}


//
// View
//

switch($_COOKIE['saved'] ?? '') {
	case SAVE_MSG_DEFAULT: {
		if($mod['statusId'] == STATUS_LOCKED && !canModerate(null, $user)) {
			addMessage(MSG_CLASS_OK, "Submitted for review!");
		}
		else {
			addMessage(MSG_CLASS_OK, "Saved!");
		}

		setcookie('saved', ''); // clear message cookie
	} break;

	case SAVE_MSG_REVERTED: {
		$dbPath = formatModPath($mod);
		addMessage(MSG_CLASS_WARN, "Changes saved, but your mod remains hidden as a 'Draft'.</br>To make your mod public you need to first create at least one <a href='{$dbPath}#tab-files'>release</a>.");

		setcookie('saved', ''); // clear message cookie
	}; break;

	case SAVE_MSG_TRANSFER_INITIATED: {
		addMessage(MSG_CLASS_OK, "Saved. Ownership transfer has also been initiated, the new owner may now accept or reject your offer.");

		setcookie('saved', ''); // clear message cookie
	}; break;

	case SAVE_MSG_TRANSFER_REVOKED: {
		addMessage(MSG_CLASS_OK, "Ownership transfer successfully revoked.");

		setcookie('saved', ''); // clear message cookie
	}; break;
}


if($mod['statusId'] == STATUS_LOCKED) {
	$lockInfo = $con->getRow('
		SELECT rec.reason, n.notificationId
		FROM moderationRecords rec
		LEFT JOIN notifications n ON n.kind = '.NOTIFICATION_MOD_UNLOCK_REQUEST.' AND n.recordId = ? AND n.created >= rec.created
		WHERE rec.kind = '.MODACTION_KIND_LOCK.' AND rec.until >= NOW() AND rec.recordId = ?
		ORDER BY rec.until DESC, rec.actionId DESC
	', [$mod['modId'], $mod['modId']]);

	$lockReason = htmlspecialchars($lockInfo['reason']);
	if($lockInfo['notificationId']) {
		$nextStepHint = !canModerate(null, $user) 
			? 'You have submitted for review and will receive a notification once the review has concluded.'
			: 'The author has submitted the current state for review.';
		$callToAction = '';
	}
	else {
		$nextStepHint = '';
		$callToAction = '<p>Address these issues and submit for a review to get your mod published again.</p>';
	}

	addMessage(MSG_CLASS_ERROR.' permanent', <<<HTML
		<h3 style='text-align: center;'>This mod has been locked by a moderator.</h3>
		<p>
			<h4 style='margin-bottom: 0.25em;'>Reason:</h4>
			<blockquote>{$lockReason}</blockquote>
		</p>
		$callToAction
	HTML);
	if($nextStepHint) addMessage(MSG_CLASS_OK.' permanent', $nextStepHint);
}

foreach ($filesInOrder as &$file) {
	$file['created'] = date('M jS Y, H:i:s', strtotime($file['created']));

	$file['ext'] = pathinfo($file['name'], PATHINFO_EXTENSION);
	$file['url'] = maybeFormatDownloadTrackingUrlDependingOnFileExt($file);
}
unset($file);

// @legacy: Migrate image sizes
{
	// 1. Mods only have image attachments.
	// 2. Images uploaded before the strict logo change do not have their dimensions stored.
	// :ImageSizeMigration

	// To address this we re-download any such images on opening the editor, and figure out the dimensions.
	// This should be a relatively sparse process, and therefore not overwhelm the server.
	// In the future we can run a derivate of this function to clean up any remaining files, potentially using the still existing backup files on the drive from before we used a CDN.

	foreach($filesInOrder as &$file) {
		if($file['imageSize'] === null) {
			$localpath = tempnam(sys_get_temp_dir(), '');
			$originalfile = @file_get_contents(formatCdnUrl($file));
			if ($originalfile === false || file_put_contents($localpath, $originalfile) === false) {
				unlink($localpath);
				continue;
			}
	
			list($w, $h) = getimagesize($localpath);

			unlink($localpath);

			$file['imageSize'] = "{$w}x{$h}";
			$con->execute(<<<SQL
				INSERT INTO fileImageData (fileId, size)
					VALUES (?, POINT(?, ?))
				ON DUPLICATE KEY UPDATE
					size = VALUES(size)
			SQL, [$file['fileId'], $w, $h]);
		}
	}
	unset($file);
}

// Filling out fields for preview card
{
	$mod['hasLegacyLogo'] = false;
	// In theory this could be problematic because it might mismatch with the id, but the 'code' is not actually used by the editor.
	$mod['statusCode']    = 'draft';
	$mod['dbPath']        = '#';
	$mod['downloads']     = 123456;
	$mod['comments']      = 123456;
}


$screenshotsDisclaimer = '';
//NOTE(Rennorb): Mobile doesn't really have working drag and drop, nor does it make sense on non-pointer devices (TVs or consoles).
// The js still gets attached, but we don't put the hint here so users don't get confused if it doesn't work.
if(!isTVPlatform() && !isTouchPlatform()) $screenshotsDisclaimer .= 'drag to reorder';
if($mod['modId']) { if($screenshotsDisclaimer) $screenshotsDisclaimer .= ', '; $screenshotsDisclaimer .= 'upload / delete changes apply immediately!'; }
if($screenshotsDisclaimer) $screenshotsDisclaimer = "<small>($screenshotsDisclaimer)</small>";
$view->assign('screenshotsDisclaimer', $screenshotsDisclaimer, null, true);


$view->assign('modTypes', $modTypes, null, true);
$view->assign('modSidedness', $modSidedness, null, true);
$view->assign('stati', $stati, null, true);

$allTags = $con->getAssoc('SELECT tagId, name, text FROM tags ORDER BY name');
$view->assign('tags', $allTags);

$view->assign('mod', $mod);
$view->assign('asset', ['assetId' => $mod['assetId'], 'assetTypeId' => ASSETTYPE_MOD], null, true); //TODO(Rennorb) @cleanup: only here for the footer js / file upload code
$view->assign('teamMembers', $teamMembers);
if($canEditAsOwner && $currentlyBeingTransferredTo)  $view->assign("ownershipTransferUser", $currentlyBeingTransferredTo['name']);
$view->assign('files', $filesInOrder);
$view->assign('headerHighlight', HEADER_HIGHLIGHT_SUBMIT_MOD, null, true);
$view->display('edit-mod');
