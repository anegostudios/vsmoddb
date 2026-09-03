USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='auditLogs' AND COLUMN_NAME='info' AND DATA_TYPE = 'text') ) THEN

  ALTER TABLE auditLogs MODIFY COLUMN `info`             MEDIUMTEXT CHARACTER SET 'utf8mb4' NULL;

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;


