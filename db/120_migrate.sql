USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='files' AND COLUMN_NAME='order') ) THEN
	ALTER TABLE `files` ADD COLUMN `order` TINYINT NOT NULL DEFAULT 0 AFTER `cdnPath`;
	ALTER TABLE `files` MODIFY COLUMN `order` TINYINT NOT NULL;
END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
