USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN



IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='moderationrecord' AND COLUMN_NAME='recordid') ) THEN
  INSERT INTO `status` (`statusid`, `code`, `name`, `created`, `sortorder`)
    VALUES (4, 'locked', 'Locked', NOW(), 4);

  ALTER TABLE `notification` MODIFY COLUMN  `type` ENUM('newcomment', 'mentioncomment', 'newrelease', 'teaminvite', 'modownershiptransfer', 'modlocked', 'modunlockrequest', 'modunlocked') NULL;

  ALTER TABLE `moderationrecord` 
    ADD COLUMN `recordid` INT NULL COMMENT 'The id of the corresponding record in the kind-specific table' AFTER `kind`;

  UPDATE `moderationrecord` 
    SET recordid = IFNULL((
      SELECT commentid
      FROM `comment`
      WHERE lastmodaction = actionid
    ), 0);

  UPDATE `moderationrecord` SET recordid = targetuserid WHERE kind in(1, 4); -- ban / redeem

  UPDATE `moderationrecord` SET `recordid` = 0 WHERE `recordid` IS NULL;
  ALTER TABLE `moderationrecord` 
    MODIFY COLUMN `recordid` INT NOT NULL COMMENT 'The id of the corresponding record in the kind-specific table' AFTER `kind`;


  COMMIT;
END IF;

END $$

CALL upgrade_database__moderation() $$


