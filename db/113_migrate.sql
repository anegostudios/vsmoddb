USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN



IF NOT EXISTS( (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='comment' AND COLUMN_NAME='created' and INDEX_NAME='created') ) THEN
 ALTER TABLE `comment` ADD INDEX `created`(`created`);
END IF;

END $$

CALL upgrade_database__moderation() $$


