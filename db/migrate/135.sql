USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='files' AND COLUMN_NAME='size') ) THEN

  ALTER TABLE files ADD COLUMN `size` INT UNSIGNED NOT NULL AFTER cdnPath;

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;


