USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modReleases' AND COLUMN_NAME='retractionReason') ) THEN
	ALTER TABLE `modReleases` ADD COLUMN `retractionReason` TEXT CHARACTER SET utf8mb4 NULL AFTER `detailText`;
END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
