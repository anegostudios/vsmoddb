DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN



IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='user' AND COLUMN_NAME='created' AND COLUMN_DEFAULT = 'NULL') ) THEN
    ALTER TABLE `user` MODIFY `roleid`                 INT          NOT NULL DEFAULT 3;
    DELETE FROM `user` WHERE `uid` IS NULL; -- There are some completely null users that do not have any data set but the defaults. They cannot successfully interact with most parts of the database, and i therefore opted to just remove them. 
    ALTER TABLE `user` MODIFY `uid`                    VARCHAR(255) NOT NULL;
    ALTER TABLE `user` MODIFY `name`                   VARCHAR(255) NOT NULL;
    ALTER TABLE `user` MODIFY `email`                  VARCHAR(255) NOT NULL;
    ALTER IGNORE TABLE `user` MODIFY `actiontoken`     BIGINT       NOT NULL; -- signed for reasons of php compatibility
    UPDATE `user` SET `actiontoken` = `userid` ^ UNIX_TIMESTAMP(`created`); -- Most actiontokens would be set to 0 from the conversion. This is here to at least generate somewhat arbitrary tokens to not leave most of them open for an easy attack.
    ALTER TABLE `user` MODIFY `sessiontokenvaliduntil` DATETIME     NOT NULL;
    ALTER TABLE `user` MODIFY `created`                DATETIME     NOT NULL DEFAULT NOW();
    ALTER TABLE `user` MODIFY `lastmodified`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    ALTER TABLE `user` DROP COLUMN `password`;
    -- ALTER TABLE `user` MODIFY `lastonline`             DATETIME     NOT NULL DEFAULT NOW(); -- unused for now, but i can see the appeal. Since the value does not get updated as of yet i oped to keep it and let it be null for now.
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
