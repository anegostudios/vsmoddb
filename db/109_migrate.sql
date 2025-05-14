USE `moddb`;

DELIMITER $$

CREATE OR REPLACE FUNCTION compile_semantic_version(versionStr VARCHAR(255)) RETURNS BIGINT UNSIGNED
BEGIN

DECLARE major, minor, rel, pre, preVal BIGINT UNSIGNED DEFAULT 0;
DECLARE ver, preKindStr, preValStr VARCHAR(255);
SET ver = IF(SUBSTR(versionStr, 1, 1) = 'v', SUBSTR(versionStr, 2), versionStr);

SET major = CAST(SUBSTRING_INDEX(ver, '.', 1) as UNSIGNED);
SET minor = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(ver, '.', 2), '.', -1) as UNSIGNED);
SET rel = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(ver, '.', 3), '.', -1), '-', 1) as UNSIGNED);

SET preKindStr = SUBSTRING_INDEX(SUBSTRING_INDEX(ver, '-', -1), '.', 1);
SET preValStr = SUBSTRING_INDEX(SUBSTRING_INDEX(ver, '-', -1), '.', -1);
SET preVal = IF(preKindStr = preValStr, 0, CAST(preValStr as UNSIGNED)); -- special case for 1.1.1-pre (without number suffix) because that exists in the data
CASE preKindStr
  WHEN 'dev' THEN SET pre = ( 4 << 12) | (preVal & 0x0fff);
  WHEN 'pre' THEN SET pre = ( 8 << 12) | (preVal & 0x0fff);
  WHEN  'rc' THEN SET pre = (12 << 12) | (preVal & 0x0fff);
  ELSE SET pre = 0xffff;
END CASE;


RETURN ((major & 0xffff) << 48) | ((minor & 0xffff) << 32) | ((rel & 0xffff) << 16) | ((pre & 0xffff) << 0);

END; $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN




CREATE TABLE IF NOT EXISTS `GameVersions` (
  `version` BIGINT UNSIGNED NOT NULL,   -- compiled version
  `sortIndex` INT NOT NULL, -- sequential n+1 sort order to check for sequential sequences
  PRIMARY KEY (`version`))
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `ModReleaseCompatibleGameVersions` (
  `releaseId` INT NOT NULL,
  `gameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`releaseId`, `gameVersion`))
ENGINE = InnoDB;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modversioncached') ) THEN
 START TRANSACTION;
    ALTER TABLE `modversioncached` ADD COLUMN `gameVersion` BIGINT UNSIGNED NOT NULL;
    UPDATE `modversioncached` mvc
      INNER JOIN `tag` t on t.tagid = mvc.tagid
      SET `gameVersion` = compile_semantic_version(t.name);

    ALTER TABLE `modversioncached` DROP COLUMN `tagid`;

    ALTER TABLE `modversioncached` DROP INDEX `modid`;
    ALTER TABLE `modversioncached` CHANGE COLUMN `modid` `modId` INT NOT NULL;
    ALTER TABLE `modversioncached` ADD PRIMARY KEY (`modId`, `gameVersion`);

    ALTER TABLE `modversioncached` RENAME TO `ModCompatibleGameVersionsCached`;
  COMMIT;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='majormodversioncached') ) THEN
 START TRANSACTION;
    ALTER TABLE `majormodversioncached` ADD COLUMN `majorGameVersion` BIGINT UNSIGNED NOT NULL;
    UPDATE `majormodversioncached` mmvc
      INNER JOIN `majorversion` mv on mv.majorversionid = mmvc.majorversionid
      SET `majorGameVersion` = compile_semantic_version(REPLACE(mv.name, 'x', '0') & 0xffffffff00000000);

    ALTER TABLE `majormodversioncached` DROP INDEX `majorversionid`;
    ALTER TABLE `majormodversioncached` DROP INDEX `index`;
    ALTER TABLE `majormodversioncached` DROP COLUMN `majorversionid`;

    ALTER TABLE `majormodversioncached` DROP INDEX `modid`;
    ALTER TABLE `majormodversioncached` CHANGE COLUMN `modid` `modId` INT NOT NULL;
    ALTER TABLE `majormodversioncached` ADD PRIMARY KEY (`modId`, `majorGameVersion`);

    ALTER TABLE `majormodversioncached` RENAME TO `ModCompatibleMajorGameVersionsCached`;
    
    DROP TABLE IF EXISTS `majorversion`;
  COMMIT;
END IF;


IF EXISTS( (SELECT 1 FROM `tag` WHERE `tagtypeid` = 1) ) THEN
 START TRANSACTION;

  INSERT INTO `ModReleaseCompatibleGameVersions` (`releaseId`, `gameVersion`)
    SELECT r.releaseid, compile_semantic_version(t.name)
    FROM `assettag` AS ast
    JOIN `asset` a ON a.assetid = ast.assetid AND a.assettypeid = 2 -- release
    JOIN `release` r ON r.assetid = a.assetid
    JOIN `tag` t ON t.tagid = ast.tagid
    WHERE t.tagtypeid = 1;

  DELETE FROM `tag` WHERE `tagtypeid` = 1; -- gameversion
  DELETE FROM `tagtype` WHERE `tagtypeid` = 1; -- gameversion
  
  COMMIT;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='release' AND COLUMN_NAME='modversion' AND DATA_TYPE='varchar') ) THEN
 START TRANSACTION;
    ALTER TABLE `release` CHANGE COLUMN `modversion` `_modversion` VARCHAR(50) NULL;

    ALTER TABLE `release` ADD COLUMN `modversion` BIGINT UNSIGNED NOT NULL AFTER `modidstr`;
    UPDATE `release` SET `modversion` = compile_semantic_version(`_modversion`);

    ALTER TABLE `release` DROP INDEX `modidstr`;
    ALTER TABLE `release` ADD UNIQUE INDEX `identifier` (`modId`, `modversion`);

    ALTER TABLE `release` DROP COLUMN `_modversion`;
  COMMIT;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modpeek_result' AND COLUMN_NAME='detectedmodversion' AND DATA_TYPE='varchar') ) THEN
 START TRANSACTION;
    ALTER TABLE `modpeek_result` CHANGE COLUMN `detectedmodversion` `_detectedmodversion` VARCHAR(255);

    ALTER TABLE `modpeek_result` ADD COLUMN `detectedmodversion` BIGINT UNSIGNED NOT NULL AFTER `detectedmodidstr`;
    UPDATE `modpeek_result` SET `detectedmodversion` = compile_semantic_version(`_detectedmodversion`);

    ALTER TABLE `modpeek_result` DROP COLUMN `_detectedmodversion`;
  COMMIT;
END IF;


END $$

CALL upgrade_database__moderation() $$

DROP FUNCTION compile_semantic_version $$

DELIMITER ;
