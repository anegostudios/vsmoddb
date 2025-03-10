DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN



IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='file' AND COLUMN_NAME='imagesize') ) THEN
    ALTER TABLE moddb.file ADD imagesize POINT NULL;
  COMMIT;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
