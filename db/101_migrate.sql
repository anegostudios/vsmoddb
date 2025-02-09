


-- This migration is destructive and should only be run to completion as the last step of migration from a previous version.
-- This is a three step process:

-- Step 0: SQLdump the whole database as backup, then remove line below and run again.
KILL connection_id();


-- Step 1. Creating the "has thumbnail" column
DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='file'
	AND COLUMN_NAME='hasthumbnail') ) THEN
		ALTER TABLE moddb.file ADD hasthumbnail BOOL NOT NULL DEFAULT 0 AFTER `type`; -- could maybe be merged with type
END IF;

END $$
CALL upgrade_database__moderation() $$
DELIMITER ;

-- Step 2: Copy this migration php script into a php file in the project root and execute it.
-- This requires you to have set up the new cdn including the required $config variables. See cdn/bunny.cdn for config options.
/*********************

<?php

$config = array();
$config["basepath"] = getcwd() . '/';
include "lib/config.php";

if(CDN !== 'bunny' || empty($config['assetserver']) || empty($config['bunnyendpoint']) || empty($config['bunnyzone']) || empty($config['bunnykey'])) die('ERROR: CONFIGURE BUNNY!');

include "lib/core.php";


foreach($con->getRows("SELECT * FROM `file`") as $file) {
	$error_on_missing_thumb = true;
	$thumbnail_name = $file['thumbnailfilename'];
	splitOffExtension($file['filename'], $noext, $ext);
	if(!$thumbnail_name) {
		$thumbnail_name = "{$noext}_thumb.{$ext}";
		$error_on_missing_thumb = false;
	}

	if($file['assetid'])
		$localdir =  $config['basepath']."files/asset/{$file['assetid']}";
	else
		$localdir = $config['basepath']."tmp/{$file['userid']}";


	if(!is_dir($localdir)) {
		echo "ERROR: FileId {$file['fileid']}: Missing expected directory '$localdir', cannot upload.";
		continue;
	}

	$filepath = "$dir/{$file['filename']}";
	if(!is_file($filepath)) {
		echo "ERROR: FileId {$file['fileid']}: Missing file '$filepath', cannot upload.";
		continue;
	}

	$remote_basename = generateCdnFileBasenameWithPath(
		$file['userid'] ?? random_int(PHP_INT_MIN, PHP_INT_MAX),
		$filepath,
		$noext
	);
	$sql_where_clause = "WHERE fileid = ".$file['fileid'];

	$upload_error = uploadToCdn($filepath, $remote_basename.$ext);
	if($upload_error['error'])
		echo "ERROR: FileId {$file['fileid']}: Failed file upload: {$upload_error['error']}. Not converted.";
	else
		$con->Execute("UPDATE `file` SET thumbnailfilename = ? $sql_where_clause", [$remote_basename.$ext]);

	$thumb_filepath = "$dir/$thumbnail_name";
	if(!is_file($thumb_filepath)) {
		if($error_on_missing_thumb)
			echo "ERROR: FileId {$file['fileid']}: Missing thumb file '$thumb_filepath' even though a thumb filename was found in the database, incomplete upload.";

		continue;
	}

	$upload_error = uploadToCdn($thumb_filepath, $remote_basename.'_55_60'.$ext);
	if($upload_error['error'])
		echo "ERROR: FileId {$file['fileid']}: Failed thumb upload: {$upload_error['error']}. Incomplete upload.";
	else 
		$con->Execute("UPDATE `file` SET hasthumbnail = 1 $sql_where_clause");
}

******************/


-- Step 3: Remove the line below and run this script again. This will destructively change the sql columns to the new schema. Make sure the php script ran without errors before you do this !!
KILL connection_id();



DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN


IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='file'
	AND COLUMN_NAME='cdnpath') ) THEN
		ALTER TABLE moddb.file  RENAME COLUMN thumbnailfilename to cdnpath;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='mod'
	AND COLUMN_NAME='logofilename') ) THEN
		ALTER TABLE moddb.mod  DROP COLUMN logofilename;
END IF;

CREATE TABLE IF NOT EXISTS `moddb`.`modpeek_result` (
  `fileid` INT NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `detectedmodidstr` VARCHAR(255),
  `detectedmodversion` VARCHAR(255),
  PRIMARY KEY (`fileid`))
ENGINE = InnoDB;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='release'
	AND COLUMN_NAME='detectedmodidstr') ) THEN
    -- transfer over the modpeek info
    INSERT INTO modpeek_result (fileid, created, detectedmodidstr, detectedmodversion)
      SELECT file.fileid, release.created, release.detectedmodidstr, release.modversion
      FROM release
      JOIN `file` on file.assetid = release.assetid;

    -- remove the old columns
		ALTER TABLE moddb.release  DROP COLUMN detectedmodidstr;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
