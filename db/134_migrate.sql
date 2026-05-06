USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='fmodReleases' AND COLUMN_NAME='identifier' and INDEX_NAME='hybrid_identifier') ) THEN

	ALTER TABLE `modReleases` RENAME INDEX `identifier` TO `hybrid_identifier`;
	ALTER TABLE `modReleases` ADD INDEX `identifier` (`identifier`); -- For search by identifier

END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;


