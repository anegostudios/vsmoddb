USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='fileDownloadTracking' AND COLUMN_NAME='ipAddress' and INDEX_NAME='identifier') ) THEN

ALTER TABLE `fileDownloadTracking`
  DROP PRIMARY KEY,
  DROP INDEX `ipaddress`,
  DROP INDEX `fileid`,
  MODIFY COLUMN `ipAddress` INET6 NOT NULL,
  ADD INDEX `identifier` (`fileId`, `ipAddress`, `lastDownload`);

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;


