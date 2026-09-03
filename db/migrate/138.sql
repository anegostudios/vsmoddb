USE `moddb`;

CREATE TABLE IF NOT EXISTS `auditLogs` (
	`logId`            INT  UNSIGNED NOT NULL AUTO_INCREMENT,
	`kind`             INT1 UNSIGNED NOT NULL,
  `flags`            INT1 UNSIGNED NOT NULL,
	`referenceId`      INT           NOT NULL,
	`initiatorUserId`  INT           NOT NULL,
	`info`             TEXT CHARACTER SET 'utf8mb4' NULL,
	`created`          DATETIME      NOT NULL DEFAULT NOW(),
	PRIMARY KEY (logId),
	INDEX `referenced` (referenceId, kind)
)
ENGINE = InnoDB;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF EXISTS( (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='changelogs') ) THEN

RENAME TABLE `changelogs` TO `_changelogs`; -- delete once migration is completely done.

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
