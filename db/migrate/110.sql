USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=(SELECT DATABASE()) AND
 TABLE_NAME='mod' AND COLUMN_NAME='descriptionsearchable') ) THEN
    ALTER TABLE `mod` ADD COLUMN `descriptionsearchable` TEXT NULL AFTER `summary`;
END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
