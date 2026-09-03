USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modPeekResults' AND COLUMN_NAME='rawBackgroundPaths') ) THEN

  ALTER TABLE modPeekResults ADD COLUMN `rawBackgroundPaths` TEXT CHARACTER SET utf8mb4 NULL AFTER rawContributors;

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;


