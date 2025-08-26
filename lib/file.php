<?php

/**
 * Try to delete a file. This will check if the file happens to still be used somewhere, and issue a deletion if its not.
 * 
 * @param array{fileId: int, assetId: int, assetTypeId: int, name: string, cdnPath: string, hasThumbnail: bool}[] $files - @security: must be sql safe. Will not validate ownership.
 */
function tryDeleteFiles($files)
{
	global $con;

	$ids = array_map(fn($file) => intval($file['fileId']), $files);
	$idsFolded = '('.implode(',', $ids).')';

	$con->startTrans();

	$con->Execute("UPDATE mods SET cardLogoFileId = NULL WHERE cardLogoFileId in $idsFolded");
	$con->Execute("UPDATE mods SET embedLogoFileId = NULL WHERE embedLogoFileId in $idsFolded");
	$con->Execute("DELETE FROM files WHERE fileId in $idsFolded");

	foreach($files as $file) {
		$assetId = $file['assetId'];
	
		if($assetId && $file['assetTypeId'] === ASSETTYPE_RELEASE) {
			// Remove potential outstanding "check this file" notification. :LegacyMalformedModInfo
			$con->Execute('UPDATE notifications SET `read` = 1 WHERE kind = ? AND recordId = ?', [NOTIFICATION_ONEOFF_MALFORMED_RELEASE, $assetId]);
		}

		splitOffExtension($file['cdnPath'], $noext, $ext);

		$countOfFilesUsingThisCDNPath = $con->getOne('SELECT COUNT(*) FROM files WHERE cdnPath = ?', [$file['cdnPath']]);
		if($countOfFilesUsingThisCDNPath < 2) {
			$con->Execute('DELETE FROM files WHERE cdnPath = ?', ["{$noext}_480_320.{$ext}"]); // legacy logo
			//TODO(Rennorb) @correctness: Could try and figure out if there is a difference between a "generic error" response and "this file does not exist" and then decided on whether or not this should be an error.
			// For now we ignore errors here, even if we fail to delete from cdn we still deleted the table entry because we otherwise block user interaction because of third party issues (no-go).
			deleteFromCdn($file['cdnPath']);
			if($file['hasThumbnail']) deleteFromCdn("{$noext}_55_60.{$ext}"); // thumbnail
			deleteFromCdn("{$noext}_480_320.{$ext}"); // legacy logo
		
			logAssetChanges(["Deleted file '{$file['name']}' and underlying resources"], $assetId);
		}
		else {
			$others = $countOfFilesUsingThisCDNPath - 1;
			logAssetChanges(["Deleted file entry '{$file['name']}', $others other file(s) are still using the underlying resource"], $assetId);
		}
	}

	$con->completeTrans();
}