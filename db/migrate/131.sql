USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='comments' AND COLUMN_NAME='responseTo') ) THEN
	ALTER TABLE `comments` ADD COLUMN `responseTo` INT NULL AFTER `assetId`;
	ALTER TABLE `comments` ADD COLUMN `conversationRoot` INT NULL AFTER `responseTo`;
	ALTER TABLE `comments` ADD COLUMN `responseDepth` TINYINT UNSIGNED  NOT NULL DEFAULT 0 AFTER `conversationRoot`;
	ALTER TABLE `comments` ADD COLUMN `textShort` VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL AFTER `text`;
END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
