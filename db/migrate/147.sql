USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='fileDownloadTracking' AND COLUMN_NAME='userId') ) THEN

  ALTER TABLE fileDownloadTracking ADD COLUMN `userId` INT NOT NULL AFTER `ipAddress`;

  ALTER TABLE fileDownloadTracking DROP INDEX `identifier`;
  ALTER TABLE fileDownloadTracking ADD  INDEX `identifier` (`fileId`, `ipAddress`, `userId`, `lastDownload`);

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
