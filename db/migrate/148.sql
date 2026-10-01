USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modPeekResults' AND COLUMN_NAME='capabilities') ) THEN

  ALTER TABLE modPeekResults ADD COLUMN capabilities INT1 UNSIGNED   NOT NULL AFTER requiredOnClient;
  ALTER TABLE modPeekResults ADD COLUMN rawReflections TEXT CHARACTER SET latin1 NULL after rawDependencies;

  ALTER TABLE modReleases ADD COLUMN `status` INT1 UNSIGNED NOT NULL AFTER `version`;
  ALTER TABLE mods ADD COLUMN `status` INT1 UNSIGNED NOT NULL AFTER `category`;

  UPDATE mods m JOIN assets a ON a.assetId = m.assetId SET m.status = a.statusId;
  -- UPDATE modReleases m JOIN assets a a.assetId = r.assetId SET m.status = a.statusId; -- releases have not been tracking their status before this.

  ALTER TABLE assets DROP COLUMN statusId;
  DROP TABLE `status`;

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
