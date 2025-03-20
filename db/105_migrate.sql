DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN



IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='mod' AND COLUMN_NAME='logofileid') ) THEN
    ALTER TABLE moddb.`mod` CHANGE COLUMN logofileid logofileiddb INT NULL;
    ALTER TABLE moddb.`mod` ADD logofileidexternal INT NULL AFTER logofileiddb;

    UPDATE moddb.`mod` SET logofileidexternal = logofileiddb;

    UPDATE moddb.`assettype` SET maxfiles = 12; -- Increase limit since now up to two images are used up by logos
  COMMIT;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
