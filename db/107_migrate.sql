DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN



IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='mod' AND COLUMN_NAME='lastreleased' AND COLUMN_KEY = '') ) THEN
    ALTER TABLE `mod` MODIFY `downloads` INT NOT NULL DEFAULT 0;
    UPDATE `mod` SET comments = 0 WHERE comments IS NULL;
    ALTER TABLE `mod` MODIFY `comments` INT NOT NULL DEFAULT 0;
    ALTER TABLE `mod` ADD INDEX `trendingpoints_id` (`trendingpoints`, `modid`);
    ALTER TABLE `mod` ADD INDEX `lastreleased_id` (`lastreleased`, `modid`);
    ALTER TABLE `mod` ADD INDEX `downloads_id` (`downloads`, `modid`);
  COMMIT;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
