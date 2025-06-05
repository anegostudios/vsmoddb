USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='mod' AND COLUMN_NAME='descriptionsearchable') ) THEN
    ALTER TABLE `mod` ADD COLUMN `descriptionsearchable` TEXT NULL AFTER `summary`;
END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
