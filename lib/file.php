<?php

const FILE_TYPE_CS          = 1;
const FILE_TYPE_DLL         = 2;
const FILE_TYPE_ZIP         = 3;
const FILE_TYPE_PNG         = 4;
const FILE_TYPE_JPEG        = 5;
const FILE_TYPE_GIF         = 6;
const FILE_TYPE_WEBP_STATIC = 7;
const FILE_TYPE_WEBP_ANIM   = 8;

/**
 * @param array{name:string, tmp_name:string} $file
 * @param resource $fileStream
 * @return int FILE_TYPE_* or zero
 */
function extractFileType($file, $fileStream)
{
	$fileHeader = fread($fileStream, 12);

	switch($first4bytes = substr($fileHeader, 0, 4)) {
		case "\xFF\xD8\xFF\xE0":
		case "\xFF\xD8\xFF\xDB":
		case "\xFF\xD8\xFF\xEE":
			$lowerName = strtolower($file['name']);
			if(str_ends_with($lowerName, '.jpg')) return FILE_TYPE_JPEG;
			if(str_ends_with($lowerName, '.jpeg')) return FILE_TYPE_JPEG;

			return 0;

		case "\xFF\xD8\xFF\xE1":
			if(substr($fileHeader, 6, 6) !== "\x45\x78\x69\x66\x00\x00")
				return 0;

			$lowerName = strtolower($file['name']);
			if(str_ends_with($lowerName, '.jpg')) return FILE_TYPE_JPEG;
			if(str_ends_with($lowerName, '.jpeg')) return FILE_TYPE_JPEG;

			return 0;

		case "\x89\x50\x4e\x47":
			$fullMagic = substr($fileHeader, 0, 8);
			$lowerName = strtolower($file['name']);
			if($fullMagic === "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a" && str_ends_with($lowerName, '.png')) return FILE_TYPE_PNG;

			return 0;

		case "RIFF": // https://developers.google.com/speed/webp/docs/riff_container#webp_file_header
			$secondMagic = substr($fileHeader, 8, 4);
			$lowerName = strtolower($file['name']);
			if($secondMagic === "WEBP" && str_ends_with($lowerName, '.webp')) {
				$end = filesize($file['tmp_name']);

				for($o = ftell($fileStream); $o + 8 < $end; ) {
					$chunkHeader = unpack('a4magic/Vlength', fread($fileStream, 8));
					switch($chunkHeader['magic']) {
						case 'ANIM':
							return FILE_TYPE_WEBP_ANIM;
						case 'VP8X':
							if($o + 12 <= $end) {
								if(ord(fread($fileStream, 1)) & 0x02) return FILE_TYPE_WEBP_ANIM;
							}
							break;
					}

					$o += 8 + $chunkHeader['length'];
					if($chunkHeader['length'] % 2 !== 0) $o += 1;
					fseek($fileStream, $o);
				}
				return FILE_TYPE_WEBP_STATIC;
			}

			return 0;

		case "PK\x03\x04": // zip """header""" https://en.wikipedia.org/wiki/ZIP_(file_format)#Structure
			return FILE_TYPE_ZIP;

		default:
			if(unpack('v', $first4bytes)[0] === 0x5A4D) { // dos header
				return FILE_TYPE_DLL;
			}
			else if(str_ends_with($file['name'], '.cs')) {
				fseek($fileStream, 0);
				$data = fread($fileStream, 200 * MB); //TODO(Rennorb) @correctness @hack
				if(ctype_print($data)) {
					return FILE_TYPE_CS;
				}
			}

			return 0;
	}
}

/**
 * Try to delete a set of files. This will check if the file happens to still be used somewhere, and issue a deletion if its not.
 * 
 * @param array{fileId: int, assetId: int, assetTypeId: int, name: string, cdnPath: string, hasThumbnail: bool}[] $files - @security: must be sql safe. Will not validate ownership.
 */
function tryDeleteFiles($files)
{
	//TODO(Rennorb) @refactor: Split this into two parts.
	// This operation usually happens as part of a larger delete, so in that case we want to do all the database stuff first, and only delete files form the cdn at a later point when all database operations are finished.
	// Right now we might delete things from the cdn, then roll back our database deletion because of an error.
	global $con;

	if(!$files) return;

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

		//NOTE(Rennorb): We already deleted the file(s) from the files table, so the remaining usage needs to be zero, not one.
		if($countOfFilesUsingThisCDNPath === 0) {
			//TODO(Rennorb) @correctness: Could try and figure out if there is a difference between a "generic error" response and "this file does not exist" and then decided on whether or not this should be an error.
			// For now we ignore errors here, even if we fail to delete from cdn we still deleted the table entry because we otherwise block user interaction because of third party issues (no-go).
			deleteFromCdn($file['cdnPath']);
			if($file['hasThumbnail']) deleteFromCdn("{$noext}_55_60.{$ext}"); // thumbnail
		
			logAuditEvent(AUDIT_LOG_KIND_FILE_DELETE, $file['fileId'], "'{$file['name']}' and underlying resources");
		}
		else {
			$others = $countOfFilesUsingThisCDNPath - 1;
			logAuditEvent(AUDIT_LOG_KIND_FILE_DELETE, $file['fileId'], "'{$file['name']}', $others other file(s) are still using the underlying resource");
		}
	}

	$con->completeTrans();
}
