USE `moddb`;

DELIMITER $$

CREATE OR REPLACE FUNCTION compile_semantic_version(versionStr VARCHAR(255)) RETURNS BIGINT UNSIGNED
BEGIN

  DECLARE major, minor, rel, pre, preVal BIGINT UNSIGNED DEFAULT 0;
  DECLARE preKindStr, preValStr VARCHAR(255);

  SET major = CAST(SUBSTRING_INDEX(versionStr, '.', 1) as UNSIGNED);
  SET minor = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(versionStr, '.', 2), '.', -1) as UNSIGNED);
  SET rel = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(versionStr, '.', 3), '.', -1), '-', 1) as UNSIGNED);

  SET preKindStr = SUBSTRING_INDEX(SUBSTRING_INDEX(versionStr, '-', -1), '.', 1);
  SET preValStr = SUBSTRING_INDEX(SUBSTRING_INDEX(versionStr, '-', -1), '.', -1);
  SET preVal = IF(preKindStr = preValStr, 0, CAST(preValStr as UNSIGNED)); -- special case for 1.1.1-pre (without number suffix) because that exists in the data
  CASE preKindStr
    WHEN 'dev' THEN SET pre = ( 4 << 12) | (preVal & 0x0fff);
    WHEN 'pre' THEN SET pre = ( 8 << 12) | (preVal & 0x0fff);
    WHEN  'rc' THEN SET pre = (12 << 12) | (preVal & 0x0fff);
    ELSE SET pre = 0xffff;
  END CASE;


  RETURN ((major & 0xffff) << 48) | ((minor & 0xffff) << 32) | ((rel & 0xffff) << 16) | ((pre & 0xffff) << 0);

END; $$



CREATE OR REPLACE FUNCTION fix_semantic_version(versionStr VARCHAR(255)) RETURNS VARCHAR(255)
BEGIN
  DECLARE ver VARCHAR(255);

  IF versionStr IS NULL OR versionStr = '' THEN
    RETURN '0.0.0';
  END IF;

  SET ver = IF(SUBSTR(versionStr, 1, 1) = 'v', SUBSTR(versionStr, 2), versionStr);

  if ver REGEXP '-vs.+$' THEN    -- 1.2.3-vs1.2 -> 1.2.3-dev.123 (surely this will not overflow...)
    SET ver = CONCAT(SUBSTRING_INDEX(ver, '-vs', 1), '-dev.', REGEXP_REPLACE(SUBSTRING_INDEX(ver, '-vs', -1), '[a-zA-Z]', ''));
  END IF;

  IF ver REGEXP '^\\..+$' THEN --   .1 -> 0.1
    SET ver = CONCAT('0', ver);
  END IF;

  SET ver = REGEXP_REPLACE(ver, '-[aA](lpha)?$', '-pre.1');
  SET ver = REGEXP_REPLACE(ver, '-[aA](lpha)?\\.', '-pre.');
  IF ver REGEXP '\\d[aA]$' THEN --   1a -> 1-pre.1
    SET VER = CONCAT(SUBSTR(ver, 1, LENGTH(ver) - 1), '-pre.1');
  END IF;

  SET ver = REGEXP_REPLACE(ver, '-b-linux$', '-pre.22');
  SET ver = REGEXP_REPLACE(ver, '-[bB](eta)?$', '-pre.2');
  SET ver = REGEXP_REPLACE(ver, '-[bB](eta)?\\.', '-pre.');
  IF ver REGEXP '\\d[bB]$' THEN --   1b -> 1-pre.2
    SET VER = CONCAT(SUBSTR(ver, 1, LENGTH(ver) - 1), '-pre.2');
  END IF;

  SET ver = REGEXP_REPLACE(ver, '-native$', '');

  IF ver REGEXP '^\\d+$' THEN --   1 -> 1.0.0
    SET ver = CONCAT(ver, '.0.0');
  ELSEIF ver REGEXP '^\\d+-.+$' THEN --   1-x -> 1.0.0-x
    SET ver = CONCAT(SUBSTRING_INDEX(ver, '-', 1), '.0.0-', SUBSTRING_INDEX(ver, '-', -1));
  ELSEIF ver REGEXP '^\\d+\\.\\d+$' THEN --   1.2 -> 1.2.0
    SET ver = CONCAT(ver, '.0');
  ELSEIF ver REGEXP '^\\d+\\.\\d+-.+$' THEN --   1.2-x -> 1.2.0-x
    SET ver = CONCAT(SUBSTRING_INDEX(ver, '-', 1), '.0-', SUBSTRING_INDEX(ver, '-', -1));
  ELSEIF ver REGEXP '^\\d+\\.\\d+.\\d+.\\d+$' THEN -- 1.2.3.4 -> 1.2.3-dev.4
    SET ver = CONCAT(SUBSTRING_INDEX(ver, '.', 3), '-dev.', SUBSTRING_INDEX(SUBSTRING_INDEX(ver, '.', 4), '.', -1));
  END IF;

  IF ver REGEXP '-\\d+$' THEN --   1-1 -> 1-pre.1
    SET VER = CONCAT(SUBSTRING_INDEX(ver, '-', 1), '-pre.', SUBSTRING_INDEX(ver, '-', -1));
  END IF;

  SET ver = REGEXP_REPLACE(ver, '-\\d.+$', ''); --  1.14.0-1.2-B -> 1.14.0
  SET ver = REGEXP_REPLACE(ver, '_.+$', '');  -- 25.01.12_1.20.0-rc.8 -> 25.01.12


  RETURN ver;
END; $$

CREATE OR REPLACE FUNCTION compile_semantic_version_fuzzy(versionStr VARCHAR(255)) RETURNS BIGINT UNSIGNED
BEGIN
  RETURN compile_semantic_version(fix_semantic_version(versionStr));
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
      SET `gameVersion` = compile_semantic_version_fuzzy(t.name);

    ALTER TABLE `modversioncached` DROP COLUMN `tagid`;

    ALTER TABLE `modversioncached` DROP INDEX `modid`;
    ALTER TABLE `modversioncached` CHANGE COLUMN `modid` `modId` INT NOT NULL;
    ALTER TABLE `modversioncached` ADD PRIMARY KEY (`modId`, `gameVersion`);
    ALTER TABLE `modversioncached` ADD KEY `version` (`gameVersion`);

    ALTER TABLE `modversioncached` RENAME TO `ModCompatibleGameVersionsCached`;
  COMMIT;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='majormodversioncached') ) THEN
 START TRANSACTION;
    DELETE FROM `majormodversioncached` WHERE majorversionid IS NULL; -- there are some dead entries that wll cause issues when adding the PK
    ALTER TABLE `majormodversioncached` ADD COLUMN `majorGameVersion` BIGINT UNSIGNED NOT NULL;
    UPDATE `majormodversioncached` mmvc
      INNER JOIN `majorversion` mv on mv.majorversionid = mmvc.majorversionid
      SET `majorGameVersion` = compile_semantic_version_fuzzy(REPLACE(mv.name, 'x', '0')) & 0xffffffff00000000;

    ALTER TABLE `majormodversioncached` DROP INDEX `majorversionid`;
    ALTER TABLE `majormodversioncached` DROP INDEX `index`;
    ALTER TABLE `majormodversioncached` DROP COLUMN `majorversionid`;
    ALTER TABLE `majormodversioncached` ADD KEY `version` (`majorGameVersion`);

    ALTER TABLE `majormodversioncached` DROP INDEX `modid`;
    ALTER TABLE `majormodversioncached` CHANGE COLUMN `modid` `modId` INT NOT NULL;
    ALTER TABLE `majormodversioncached` ADD PRIMARY KEY (`modId`, `majorGameVersion`);

    ALTER TABLE `majormodversioncached` RENAME TO `ModCompatibleMajorGameVersionsCached`;
    
    DROP TABLE IF EXISTS `majorversion`;
  COMMIT;
END IF;


IF EXISTS( (SELECT 1 FROM `tag` WHERE `assettypeid` = 2) ) THEN
 START TRANSACTION;

  INSERT INTO `GameVersions` (`version`, `sortIndex`)
    SELECT compile_semantic_version_fuzzy(t.name), 0
    FROM `tag` t
    WHERE t.assettypeid = 2
  ON DUPLICATE KEY UPDATE `version` = `version`;

  set @i := 0;
  UPDATE `GameVersions` SET `sortIndex` = @i := @i + 1 ORDER BY `version`;

  INSERT INTO `ModReleaseCompatibleGameVersions` (`releaseId`, `gameVersion`)
    SELECT r.releaseid, compile_semantic_version_fuzzy(t.name)
    FROM `assettag` AS ast
    JOIN `asset` a ON a.assetid = ast.assetid AND a.assettypeid = 2 -- release
    JOIN `release` r ON r.assetid = a.assetid
    JOIN `tag` t ON t.tagid = ast.tagid;

  DELETE FROM `tag` WHERE `assettypeid` = 2; -- gameversion
  DELETE FROM `tagtype` WHERE `tagtypeid` = 1; -- gameversion
  
  COMMIT;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='release' AND COLUMN_NAME='modversion' AND DATA_TYPE='varchar') ) THEN
 START TRANSACTION;
    ALTER TABLE `release` CHANGE COLUMN `modversion` `_modversion` VARCHAR(50) NULL;

    ALTER TABLE `release` ADD COLUMN `modversion` BIGINT UNSIGNED NOT NULL AFTER `modidstr`;
    UPDATE `release` SET `modversion` = compile_semantic_version_fuzzy(`_modversion`);

    ALTER TABLE `release` DROP INDEX `modidstr`;
    -- NOTE(Rennorb): The actual server does not have a unique key on the (modidstr, modversion) tuple, so of course there not only are invalid entries,
    -- but also multiple of those which violate the uniqueness even if we treat them as valid...
    -- This removes duplicate null entries, since those cause problems and can never be pulled automatically either way.
    -- In the worst case we loose download access to some malformed mods here.

    -- This has to include modidstr, as one mod can contain releases for multiple modidstr's.
    -- This also has to include the modid, as tool/other mods dont need to have a modidstr.
    ALTER IGNORE TABLE `release` ADD UNIQUE INDEX `identifier` (`modid`, `modidstr`, `modversion`);

    ALTER TABLE `release` DROP COLUMN `_modversion`;
  COMMIT;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modpeek_result' AND COLUMN_NAME='detectedmodversion' AND DATA_TYPE='varchar') ) THEN
 START TRANSACTION;
    ALTER TABLE `modpeek_result` CHANGE COLUMN `detectedmodversion` `_detectedmodversion` VARCHAR(255);

    ALTER TABLE `modpeek_result` ADD COLUMN `detectedmodversion` BIGINT UNSIGNED NOT NULL AFTER `detectedmodidstr`;
    UPDATE `modpeek_result` SET `detectedmodversion` = compile_semantic_version_fuzzy(`_detectedmodversion`);

    ALTER TABLE `modpeek_result` DROP COLUMN `_detectedmodversion`;
  COMMIT;
END IF;


END $$

CALL upgrade_database__moderation() $$

DROP FUNCTION compile_semantic_version $$
DROP FUNCTION fix_semantic_version $$
DROP FUNCTION compile_semantic_version_fuzzy $$

DELIMITER ;
