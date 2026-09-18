USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='users' AND COLUMN_NAME='consentFlags') ) THEN

  ALTER TABLE users ADD COLUMN `consentFlags` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `genAiTolerance`;

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
