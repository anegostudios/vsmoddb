DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='follow') ) THEN
    ALTER TABLE `follow` CHANGE COLUMN `modid` `modId` INT NOT NULL;
    ALTER TABLE `follow` CHANGE COLUMN `userid` `userId` INT NOT NULL;
    UPDATE `follow` SET `created` = '0000-00-00 00:00' WHERE `created` IS NULL;
    ALTER TABLE `follow` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW();

    DELETE f FROM `follow` f LEFT JOIN `mod` m ON m.modid = f.modId WHERE m.modid IS NULL;
    ALTER TABLE `follow` ADD CONSTRAINT `FK_UserFolowedMods_modId` FOREIGN KEY (`modId`) REFERENCES `mod`(`modid`) ON UPDATE CASCADE ON DELETE CASCADE;
    DELETE f FROM `follow` f LEFT JOIN `user` u ON u.userid = f.userId WHERE u.userid IS NULL;
    ALTER TABLE `follow` ADD CONSTRAINT `FK_UserFolowedMods_userId` FOREIGN KEY (`userId`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE CASCADE;


    ALTER TABLE `follow` RENAME TO `UserFollowedMods`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='teammember') ) THEN
    ALTER TABLE `teammember` CHANGE COLUMN `teammemberid` `teamMemberId` INT NOT NULL AUTO_INCREMENT;
    ALTER TABLE `teammember` CHANGE COLUMN `modid` `modId` INT NOT NULL;
    ALTER TABLE `teammember` CHANGE COLUMN `userid` `userId` INT NOT NULL;
    ALTER TABLE `teammember` CHANGE COLUMN `canedit` `canEdit` TINYINT(1) NOT NULL DEFAULT 0;
    UPDATE `teammember` SET `created` = '0000-00-00 00:00' WHERE `created` IS NULL;
    ALTER TABLE `teammember` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW();

    DELETE t FROM `teammember` t LEFT JOIN `mod` m ON m.modid = t.modId WHERE m.modid IS NULL;
    ALTER TABLE `teammember` ADD CONSTRAINT `FK_ModTeamMembers_modId` FOREIGN KEY (`modId`) REFERENCES `mod`(`modid`) ON UPDATE CASCADE ON DELETE CASCADE;
    DELETE t FROM `teammember` t LEFT JOIN `user` u ON u.userid = t.userId WHERE u.userid IS NULL;
    ALTER TABLE `teammember` ADD CONSTRAINT `FK_ModTeamMembers_userId` FOREIGN KEY (`userId`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE CASCADE;


    ALTER TABLE `teammember` RENAME TO `ModTeamMembers`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='downloadip') ) THEN
    ALTER TABLE `downloadip` CHANGE COLUMN `ipaddress` `ipAddress` VARCHAR(255) NOT NULL;
    ALTER TABLE `downloadip` CHANGE COLUMN `fileid` `fileId` INT NOT NULL;

    UPDATE `downloadip` SET `date` = '0000-00-00 00:00' WHERE `date` IS NULL;
    ALTER TABLE `downloadip` CHANGE COLUMN `date` `lastDownload` DATETIME NOT NULL DEFAULT NOW();
    ALTER TABLE `downloadip` ADD INDEX `lastDownload` (`lastDownload`);

    ALTER TABLE `release` ADD UNIQUE INDEX `assetid` (`assetid`); -- for some reason this does not yet exist, but is _required_ for performance with the tending points update
    -- ALTER TABLE `release` ADD UNIQUE INDEX `modid` (`modid`); -- already exists on the real server

    ALTER TABLE `downloadip` RENAME TO `FileDownloadTracking`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='notification') ) THEN
    ALTER TABLE `notification` CHANGE COLUMN `notificationid` `notificationId` INT NOT NULL AUTO_INCREMENT;
    DELETE FROM `notification` WHERE `userid` IS NULL;
    ALTER TABLE `notification` CHANGE COLUMN `userid` `userId` INT NOT NULL;

    DELETE FROM `notification` WHERE `type` IS NULL;
    ALTER TABLE `notification` CHANGE COLUMN `type` `kind` ENUM('newcomment', 'mentioncomment', 'newrelease', 'teaminvite', 'modownershiptransfer', 'modlocked', 'modunlockrequest', 'modunlocked') NOT NULL;

    ALTER TABLE `notification` CHANGE COLUMN `recordid` `recordId` INT NOT NULL;

    ALTER TABLE `notification` ADD CONSTRAINT `FK_Notifications_userId` FOREIGN KEY (`userId`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE CASCADE;

    ALTER TABLE `notification` RENAME TO `Notifications`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='language') ) THEN
    DROP TABLE `language`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='role') ) THEN
    ALTER TABLE `role` CHANGE COLUMN `roleid` `roleId` INT NOT NULL AUTO_INCREMENT;
    ALTER TABLE `role` MODIFY COLUMN `code` VARCHAR(255) NOT NULL;
    ALTER TABLE `role` MODIFY COLUMN `name` VARCHAR(255) NOT NULL;

    UPDATE `role` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `role` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW();

    UPDATE `role` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `role` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

    ALTER TABLE `role` RENAME TO `Roles`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='changelog') ) THEN
    ALTER TABLE `changelog` CHANGE COLUMN `changelogid` `changelogId` INT NOT NULL AUTO_INCREMENT;

    ALTER TABLE `changelog` CHANGE COLUMN `assetid` `assetId` INT NULL; -- null for file deletion events for now
    ALTER TABLE `changelog` ADD INDEX `assetId` (`assetId`);

    DELETE FROM `changelog` WHERE `userid` IS NULL;
    ALTER TABLE `changelog` CHANGE COLUMN `userid` `userId` INT NOT NULL;
    ALTER TABLE `changelog` ADD INDEX `userId` (`userId`);
    ALTER TABLE `changelog` ADD CONSTRAINT `FK_Changelogs_userId` FOREIGN KEY (`userId`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE CASCADE;

    ALTER TABLE `changelog` MODIFY COLUMN `text` TEXT NOT NULL;


    UPDATE `changelog` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `changelog` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW();

    UPDATE `changelog` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `changelog` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;


    ALTER TABLE `changelog` RENAME TO `Changelogs`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='tagtype') ) THEN
    DROP TABLE `tagtype`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='tag') ) THEN
    ALTER TABLE `tag` CHANGE COLUMN `tagid` `tagId` INT NOT NULL AUTO_INCREMENT;
    
    ALTER TABLE `tag` DROP COLUMN `assettypeid`;
    
    UPDATE `tag` SET `tagtypeid` = 2 WHERE `tagtypeid` IS NULL;
    ALTER TABLE `tag` CHANGE COLUMN `tagtypeid` `kind` TINYINT NOT NULL;

    ALTER TABLE `tag` MODIFY COLUMN `name` VARCHAR(255) NOT NULL;

    ALTER TABLE `tag` MODIFY COLUMN `text` TEXT NOT NULL;

    ALTER TABLE `tag` DROP COLUMN `color`;
    ALTER TABLE `tag` ADD COLUMN `color` INT UNSIGNED NULL AFTER `text`;
    UPDATE `tag` SET color = 0x92C96AFF; -- hardcoded, but all the current tag use that value
    ALTER TABLE `tag` MODIFY COLUMN `color` INT UNSIGNED NOT NULL;


    UPDATE `tag` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `tag` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW();

    UPDATE `tag` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `tag` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;


    ALTER TABLE `tag` RENAME TO `Tags`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='assettag') ) THEN
    ALTER TABLE `assettag` DROP COLUMN `assettagid`;

    UPDATE `assettag` t LEFT JOIN `mod` m ON m.assetid = t.assetid SET t.assetid = m.modid;
    DELETE FROM `assettag` WHERE `assetid` IS NULL;
    ALTER TABLE `assettag` CHANGE COLUMN `assetid` `modId` INT NOT NULL;

    DELETE FROM `assettag` WHERE `tagid` IS NULL;
    ALTER TABLE `assettag` CHANGE COLUMN `tagid` `tagId` INT NOT NULL;

    UPDATE `assettag` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `assettag` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW();

    UPDATE `assettag` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `assettag` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

    ALTER TABLE `assettag` ADD PRIMARY KEY (`modId`, `tagId`);
    ALTER TABLE `assettag` ADD INDEX `modId` (`modId`);
    ALTER TABLE `assettag` ADD INDEX `tagId` (`tagId`);

    -- DELETE t FROM `assettag` t LEFT JOIN `mod` m ON m.modid = t.modId WHERE m.modid IS NULL;
    ALTER TABLE `assettag` ADD CONSTRAINT `FK_Changelogs_modId` FOREIGN KEY (`modId`) REFERENCES `mod`(`modid`) ON UPDATE CASCADE ON DELETE CASCADE;
    DELETE t FROM `assettag` t LEFT JOIN `Tags` T ON T.tagId = t.tagId WHERE T.tagId IS NULL;
    ALTER TABLE `assettag` ADD CONSTRAINT `FK_Changelogs_tagId` FOREIGN KEY (`tagId`) REFERENCES `Tags`(`tagId`) ON UPDATE CASCADE ON DELETE CASCADE;


    ALTER TABLE `assettag` RENAME TO `ModTags`;
END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
