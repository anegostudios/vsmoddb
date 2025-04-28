DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN



IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='follow' AND COLUMN_NAME='flags') ) THEN
    ALTER TABLE moddb.`follow` ADD flags TINYINT  NOT NULL DEFAULT 1; -- by default follow with notifications
  COMMIT;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
