DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN



IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='notification' AND COLUMN_NAME='created' AND COLUMN_DEFAULT = 'NULL') ) THEN
    UPDATE `notification` SET `created` = '0000-00-00 00:00' WHERE `created` IS NULL;
    ALTER TABLE `notification` MODIFY `created` DATETIME NOT NULL DEFAULT NOW();
  COMMIT;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
