USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='mods' AND COLUMN_NAME='uploadLimitOverwrite') ) THEN
	ALTER TABLE `mods` ADD COLUMN `uploadLimitOverwrite` INT NULL AFTER `category`;
END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
