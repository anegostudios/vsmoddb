DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN



IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='file' AND COLUMN_NAME='created' AND COLUMN_DEFAULT = 'NULL') ) THEN
    ALTER TABLE `file` MODIFY `created` DATETIME NOT NULL DEFAULT NOW();
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modpeek_result') ) THEN
    ALTER TABLE `modpeek_result` CHANGE COLUMN `fileid` `fileId` INT NOT NULL;
    ALTER TABLE `modpeek_result` ADD COLUMN `errors` TEXT NULL AFTER `fileId`;
    ALTER TABLE `modpeek_result` DROP COLUMN `created`; -- this is always identical to the file created column
    ALTER TABLE `modpeek_result` CHANGE COLUMN `detectedmodidstr` `modIdentifier` VARCHAR(255) NULL;
    ALTER TABLE `modpeek_result` CHANGE COLUMN `detectedmodversion` `modVersion` BIGINT UNSIGNED NOT NULL;
    ALTER TABLE `modpeek_result` ADD COLUMN `type` ENUM('Theme', 'Content', 'Code') NULL,
    ALTER TABLE `modpeek_result` ADD COLUMN `networkVersion` BIGINT UNSIGNED NOT NULL;
    ALTER TABLE `modpeek_result` ADD COLUMN `description` TEXT NULL;
    ALTER TABLE `modpeek_result` ADD COLUMN `website` VARCHAR(255) NULL;
    ALTER TABLE `modpeek_result` ADD COLUMN `rawAuthors` TEXT NULL;
    ALTER TABLE `modpeek_result` ADD COLUMN `rawContributors` TEXT NULL;
    ALTER TABLE `modpeek_result` ADD COLUMN `rawDependencies` TEXT NULL;

    -- We somehow managed to get stale modpeek entries in the tables, so we delete them.
    CREATE TEMPORARY TABLE BadModPeekIds AS (
        SELECT mpr.fileId
        FROM `ModPeekResult` mpr 
        LEFT OUTER JOIN file ON file.fileid = mpr.fileId
        WHERE file.fileid IS NULL
    );
    DELETE FROM `ModPeekResult` WHERE fileId in (SELECT * FROM BadModPeekIds);
    ALTER TABLE `modpeek_result` ADD CONSTRAINT `fileId` FOREIGN KEY (`fileId`) REFERENCES `file` (`fileid`) ON UPDATE CASCADE ON DELETE CASCADE;

    ALTER TABLE `modpeek_result` RENAME TO `ModPeekResult`;
END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
