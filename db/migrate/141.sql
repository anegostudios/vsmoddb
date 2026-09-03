USE `moddb`;

CREATE TABLE IF NOT EXISTS `moderationRequests` (
  `requestId`        INT           NOT NULL AUTO_INCREMENT,
  `kind`             INT1 UNSIGNED NOT NULL,
  `category`         INT1 UNSIGNED NOT NULL,
  `stateFlags`       INT1 UNSIGNED NOT NULL DEFAULT 0,
	`referenceId`      INT           NOT NULL,
	`initiatorUserId`  INT           NOT NULL,
	`resolverUserId`   INT           NOT NULL DEFAULT 0,
	`request`          TEXT          NOT NULL,
  `requestSearchable`TEXT          NOT NULL,
	`resolution`       TEXT              NULL,
	`created`          DATETIME      NOT NULL DEFAULT NOW(),
  `resolved`         DATETIME          NULL,
  PRIMARY KEY (`requestId`),
  INDEX `reference` (`referenceId`, `kind`, `category`),
  INDEX `initiator` (`initiatorUserId`, `created`, `referenceId`, `kind`, `category`),
  INDEX `dedupe` (`referenceId`, `kind`, `category`, `initiatorUserId`, `resolved`)
);

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='users' AND COLUMN_NAME='genAiTolerance') ) THEN

ALTER TABLE `users` ADD COLUMN `genAiTolerance` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `hash`;

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
