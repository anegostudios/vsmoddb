USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='mods' AND COLUMN_NAME='type' and DATA_TYPE='enum') ) THEN
	ALTER TABLE `mods` ADD COLUMN `category` TINYINT UNSIGNED NOT NULL NULL DEFAULT 0 AFTER `type`;
	UPDATE `mods`
		SET category = CASE `type`
			WHEN 'mod'          THEN 0
			WHEN 'externaltool' THEN 1
			WHEN 'other'        THEN 2
		END;

	ALTER TABLE `mods` DROP COLUMN `type`;
END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
