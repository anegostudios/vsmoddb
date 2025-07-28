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


    ALTER TABLE `follow` RENAME TO `userFollowedMods`;
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
    ALTER TABLE `teammember` ADD CONSTRAINT `FK_modTeamMembers_modId` FOREIGN KEY (`modId`) REFERENCES `mod`(`modid`) ON UPDATE CASCADE ON DELETE CASCADE;
    DELETE t FROM `teammember` t LEFT JOIN `user` u ON u.userid = t.userId WHERE u.userid IS NULL;
    ALTER TABLE `teammember` ADD CONSTRAINT `FK_modTeamMembers_userId` FOREIGN KEY (`userId`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE CASCADE;


    ALTER TABLE `teammember` RENAME TO `modTeamMembers`;
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

    ALTER TABLE `downloadip` RENAME TO `fileDownloadTracking`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='notification') ) THEN
    ALTER TABLE `notification` CHANGE COLUMN `notificationid` `notificationId` INT NOT NULL AUTO_INCREMENT;
    DELETE FROM `notification` WHERE `userid` IS NULL;
    ALTER TABLE `notification` CHANGE COLUMN `userid` `userId` INT NOT NULL;

    DELETE FROM `notification` WHERE `type` IS NULL;
    ALTER TABLE `notification` CHANGE COLUMN `type` `kind` ENUM('newcomment', 'mentioncomment', 'newrelease', 'teaminvite', 'modownershiptransfer', 'modlocked', 'modunlockrequest', 'modunlocked') NOT NULL;

    ALTER TABLE `notification` CHANGE COLUMN `recordid` `recordId` INT NOT NULL;

    ALTER TABLE `notification` ADD CONSTRAINT `FK_notifications_userId` FOREIGN KEY (`userId`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE CASCADE;

    ALTER TABLE `notification` RENAME TO `notifications`;
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

    ALTER TABLE `role` RENAME TO `roles`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='changelog') ) THEN
    ALTER TABLE `changelog` CHANGE COLUMN `changelogid` `changelogId` INT NOT NULL AUTO_INCREMENT;

    ALTER TABLE `changelog` CHANGE COLUMN `assetid` `assetId` INT NULL; -- null for file deletion events for now
    ALTER TABLE `changelog` ADD INDEX `assetId` (`assetId`);

    DELETE FROM `changelog` WHERE `userid` IS NULL;
    ALTER TABLE `changelog` CHANGE COLUMN `userid` `userId` INT NOT NULL;
    ALTER TABLE `changelog` ADD INDEX `userId` (`userId`);
    ALTER TABLE `changelog` ADD CONSTRAINT `FK_changelogs_userId` FOREIGN KEY (`userId`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE CASCADE;

    ALTER TABLE `changelog` MODIFY COLUMN `text` TEXT NOT NULL;


    UPDATE `changelog` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `changelog` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW();

    UPDATE `changelog` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `changelog` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;


    ALTER TABLE `changelog` RENAME TO `changelogs`;
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


    ALTER TABLE `tag` RENAME TO `tags`;
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
    ALTER TABLE `assettag` ADD CONSTRAINT `FK_modTags_modId` FOREIGN KEY (`modId`) REFERENCES `mod`(`modid`) ON UPDATE CASCADE ON DELETE CASCADE;
    DELETE t FROM `assettag` t LEFT JOIN `tags` T ON T.tagId = t.tagId WHERE T.tagId IS NULL;
    ALTER TABLE `assettag` ADD CONSTRAINT `FK_modTags_tagId` FOREIGN KEY (`tagId`) REFERENCES `tags`(`tagId`) ON UPDATE CASCADE ON DELETE CASCADE;


    ALTER TABLE `assettag` RENAME TO `modTags`;
END IF;



IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='comment') ) THEN
    ALTER TABLE `comment` CHANGE COLUMN `commentid` `commentId` INT NOT NULL AUTO_INCREMENT;

    DELETE FROM `comment` WHERE `assetid` IS NULL;
    ALTER TABLE `comment` CHANGE COLUMN `assetid` `assetId` INT NOT NULL;

    DELETE FROM `comment` WHERE `userid` IS NULL;
    ALTER TABLE `comment` CHANGE COLUMN `userid` `userId` INT NOT NULL;

    DELETE FROM `comment` WHERE `text` IS NULL;
    ALTER TABLE `comment` MODIFY COLUMN `text` TEXT;

    ALTER TABLE `comment` CHANGE COLUMN `modifieddate` `contentLastModified` DATETIME NULL;

    ALTER TABLE `comment` CHANGE COLUMN `lastmodaction` `lastModaction` INT NULL;

    UPDATE `comment` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `comment` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW() AFTER `deleted`;

    UPDATE `comment` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `comment` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created`;


    DELETE c FROM `comment` c LEFT JOIN `user` u ON u.userid = c.userId WHERE u.userid IS NULL;
    ALTER TABLE `comment` ADD CONSTRAINT `FK_comments_userId` FOREIGN KEY (`userId`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE CASCADE;

    ALTER TABLE `comment` ADD CONSTRAINT `FK_comments_lastModaction` FOREIGN KEY (`lastModaction`) REFERENCES `moderationrecord`(`actionid`) ON UPDATE CASCADE ON DELETE RESTRICT;


    ALTER TABLE `comment` RENAME TO `comments`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='user') ) THEN
    ALTER TABLE `user` CHANGE COLUMN `userid` `userId` INT NOT NULL AUTO_INCREMENT;

    ALTER TABLE `user` CHANGE COLUMN `roleid` `roleId` INT NOT NULL DEFAULT 3;

    DELETE FROM `user` WHERE `uid` IS NULL;
    ALTER TABLE `user` CHANGE COLUMN `uid` `_uid` VARCHAR(255) NULL;
    ALTER TABLE `user` ADD COLUMN `uid` BINARY(18) NOT NULL DEFAULT 0 AFTER `_uid`;
    UPDATE `user` SET `uid` = FROM_BASE64(`_uid`) WHERE `_uid` IS NOT NULL;
    ALTER TABLE `user` MODIFY COLUMN `uid` BINARY(18) NOT NULL; -- remove default
    ALTER TABLE `user` DROP COLUMN `_uid`;

    ALTER TABLE `user` MODIFY COLUMN `name` VARCHAR(255) NOT NULL;

    ALTER TABLE `user` DROP COLUMN `password`;

    ALTER TABLE `user` MODIFY COLUMN `email` VARCHAR(255) NOT NULL;

    -- NOTE(Rennorb): The actiontoken is base64 encoded, but then stripped of some of the characters.
    -- In theory this inverse conversion cannot be performed, but in practice the tokens are not stored and it should therefore not cause issues, even if some of them get invalidated.
    ALTER TABLE `user` CHANGE COLUMN `actiontoken` `_actiontoken` VARCHAR(255) NULL;
    ALTER TABLE `user` ADD COLUMN `actionToken` BINARY(8) NOT NULL DEFAULT 0 AFTER `_actiontoken`;
    UPDATE IGNORE `user` SET `actionToken` = IFNULL(
        FROM_BASE64(RPAD(_actiontoken, FLOOR((LENGTH(_actiontoken) + 3) / 4) * 4, '=')),
        SUBSTRING(UNHEX(MD5(RAND())), 1, 8)
    );
    ALTER TABLE `user` MODIFY COLUMN `actionToken` BINARY(8) NOT NULL; -- remove default
    ALTER TABLE `user` DROP COLUMN `_actiontoken`;

    ALTER TABLE `user` CHANGE COLUMN `sessiontoken` `_sessiontoken` VARCHAR(255) NULL;
    ALTER TABLE `user` ADD COLUMN `sessionToken` BINARY(32) NOT NULL DEFAULT 0 AFTER `_sessiontoken`;
    UPDATE `user` SET `sessionToken` = FROM_BASE64(`_sessiontoken`) WHERE `_sessiontoken` IS NOT NULL;
    ALTER TABLE `user` MODIFY COLUMN `sessionToken` BINARY(32) NOT NULL; -- remove default
    ALTER TABLE `user` DROP COLUMN `_sessiontoken`;

    ALTER TABLE `user` CHANGE COLUMN `sessiontokenvaliduntil` `sessionValidUntil` DATETIME NULL; -- TODO

    UPDATE `user` SET `timezone` = '(GMT) London' WHERE `timezone` IS NULL;
    ALTER TABLE `user` MODIFY COLUMN `timezone` VARCHAR(255) NOT NULL;

    ALTER TABLE `user` CHANGE COLUMN `lastonline` `lastOnline` DATETIME NULL; -- TODO

    ALTER TABLE `user` CHANGE COLUMN `banneduntil` `bannedUntil` DATETIME NULL;



    UPDATE `user` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `user` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW() AFTER `bio`;

    UPDATE `user` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `user` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created`;


    ALTER TABLE `user` ADD COLUMN `hash` BINARY(10) NULL AFTER `userId`;
    UPDATE `user` SET `hash` = UNHEX(SUBSTRING(SHA2(CONCAT(userId, created), 512), 1, 20));
    ALTER TABLE `user` MODIFY COLUMN `hash` BINARY(10) NOT NULL; -- remove default



    ALTER TABLE `user` ADD INDEX `sessionToken`(`sessionToken`);
    ALTER TABLE `user` ADD INDEX `hash`(`hash`);


    ALTER TABLE `user` RENAME TO `users`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='status') ) THEN
    ALTER TABLE `status` CHANGE COLUMN `statusid` `statusId` INT NOT NULL AUTO_INCREMENT;

    ALTER TABLE `status` MODIFY COLUMN `code` VARCHAR(255) NOT NULL;
    ALTER TABLE `status` MODIFY COLUMN `name` VARCHAR(255) NOT NULL;

    ALTER TABLE `status` DROP COLUMN `sortorder`;
    ALTER TABLE `status` DROP COLUMN `created`;
    ALTER TABLE `status` DROP COLUMN `lastmodified`;

    -- ALTER TABLE `status` RENAME TO `status`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='moderationrecord') ) THEN
    ALTER TABLE `moderationrecord` CHANGE COLUMN `actionid` `actionId` INT NOT NULL AUTO_INCREMENT;

    ALTER TABLE `moderationrecord` CHANGE COLUMN `targetuserid` `targetUserId` INT NOT NULL;

    ALTER TABLE `moderationrecord` CHANGE COLUMN `recordid` `recordId` INT NOT NULL COMMENT 'The id of the corresponding record in the kind-specific table';

    ALTER TABLE `moderationrecord` MODIFY COLUMN `until` DATETIME NOT NULL;

    ALTER TABLE `moderationrecord` CHANGE COLUMN `moderatorid` `moderatorId` INT NOT NULL;

    ALTER TABLE `moderationrecord` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW() AFTER `reason`;


    ALTER TABLE `moderationrecord` ADD INDEX `id_until` (`targetUserId`, `kind`, `until`);
    ALTER TABLE `moderationrecord` ADD INDEX `recordId` (`recordId`);

    ALTER TABLE `moderationrecord` ADD INDEX `targetUserId`(`targetUserId`);
    ALTER TABLE `moderationrecord` ADD CONSTRAINT `FK_moderationRecords_targetUserId` FOREIGN KEY (`targetUserId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT;

    ALTER TABLE `moderationrecord` ADD INDEX `moderatorid_index`(`moderatorId`);
    ALTER TABLE `moderationrecord` ADD CONSTRAINT `FK_moderationRecords_moderatorId` FOREIGN KEY (`moderatorId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT;


    ALTER TABLE `moderationrecord` RENAME TO `moderationRecords`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='release') ) THEN
    ALTER TABLE `release` CHANGE COLUMN `releaseid` `releaseId` INT NOT NULL AUTO_INCREMENT;

    ALTER TABLE `release` CHANGE COLUMN `assetid` `assetId` INT NOT NULL;

    DELETE a FROM `asset` a JOIN `release` r ON r.assetId = a.assetid WHERE a.assettypeid = 2 AND r.modid IS NULL;
    DELETE FROM `release` WHERE modid IS NULL;
    DELETE r FROM `release` r LEFT JOIN `mod` m ON m.modid = r.modid WHERE m.assetid IS NULL;
    ALTER TABLE `release` CHANGE COLUMN `modid` `modId` INT NOT NULL;

    ALTER TABLE `release` CHANGE COLUMN `modidstr` `identifier` VARCHAR(255) NULL;

    ALTER TABLE `release` CHANGE COLUMN `modversion` `version` BIGINT UNSIGNED NOT NULL;

    ALTER TABLE `release` DROP COLUMN `releasedate`;

    ALTER TABLE `release` DROP COLUMN `inprogress`;

    ALTER TABLE `release` CHANGE COLUMN `detailtext` `detailText` TEXT NULL;

    ALTER TABLE `release` DROP COLUMN `releaseorder`;

    UPDATE `release` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `release` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW();

    UPDATE `release` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `release` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created`;

    ALTER TABLE `release` ADD CONSTRAINT `FK_modReleases_assetId` FOREIGN KEY (`assetId`) REFERENCES `asset`(`assetid`) ON UPDATE CASCADE ON DELETE CASCADE;

    ALTER TABLE `release` ADD CONSTRAINT `FK_modReleases_modId` FOREIGN KEY (`modId`) REFERENCES `mod`(`modid`) ON UPDATE CASCADE ON DELETE CASCADE;


    ALTER TABLE `release` RENAME TO `modReleases`;
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='file') ) THEN
    UPDATE `file` f JOIN asset a ON a.assetid = f.assetid SET f.assettypeid = a.assettypeid; 
    UPDATE `file` f1 
        JOIN `file` f2 ON f2.cdnpath = REPLACE(f1.cdnpath, '_480_320.', '.') AND f2.assetid IS NOT NULL
        SET f1.assetid = f2.assetid, f1.assettypeid = 1, f1.imagesize = POINT(480, 320)
        where f1.assetid is null and f1.cdnpath like '%_480_320.%'; -- fill out auto-resized versions
    UPDATE `file` SET assettypeid = 1 WHERE assettypeid IS NULL AND cdnpath LIKE '%.png';
    UPDATE `file` SET assettypeid = 1 WHERE assettypeid IS NULL AND cdnpath LIKE '%.jpg';
    UPDATE `file` SET assettypeid = 1 WHERE assettypeid IS NULL AND cdnpath LIKE '%.gif';
    UPDATE `file` SET assettypeid = 2 WHERE assettypeid IS NULL AND cdnpath LIKE '%.dll';
    UPDATE `file` SET assettypeid = 2 WHERE assettypeid IS NULL AND cdnpath LIKE '%.cs';
    UPDATE `file` SET assettypeid = 2 WHERE assettypeid IS NULL AND cdnpath LIKE '%.zip';
    UPDATE file f
        JOIN asset a on a.assetid = f.assetid
        SET f.userid = a.createdbyuserid
        WHERE f.userid is null; -- try pulling userid from asset if not set
    DELETE f FROM `file` f
        LEFT JOIN asset a ON a.assetid = f.assetid
        WHERE f.assetid IS NOT NULL AND a.assetid IS NULL; -- remove entries which had an asset that got deleted



    CREATE TABLE IF NOT EXISTS `fileImageData` (
        `fileId`       INT   NOT NULL,
        `hasThumbnail` BOOL  NOT NULL DEFAULT 0,
        `size`         POINT     NULL,
        PRIMARY KEY (`fileId`),
        CONSTRAINT `FK_fileImageData_fileId` FOREIGN KEY (`fileId`)  REFERENCES `file`(`fileid`) ON UPDATE CASCADE ON DELETE CASCADE
    )
    ENGINE = InnoDB;


    INSERT INTO fileImageData (fileId, hasThumbnail, size)
        SELECT fileid, hasthumbnail, imagesize
        FROM `file`
        WHERE hasthumbnail OR imagesize IS NOT NULL;

    ALTER TABLE `file` DROP COLUMN `hasthumbnail`;
    ALTER TABLE `file` DROP COLUMN `imagesize`;

    ALTER TABLE `file` CHANGE COLUMN `fileid` `fileId` INT NOT NULL AUTO_INCREMENT;
    ALTER TABLE `file` CHANGE COLUMN `assetid` `assetId` INT NULL;
    ALTER TABLE `file` CHANGE COLUMN `assettypeid` `assetTypeId` INT NOT NULL;
    ALTER TABLE `file` CHANGE COLUMN `userid` `userId` INT NOT NULL;
    ALTER TABLE `file` MODIFY COLUMN `downloads` INT NOT NULL DEFAULT 0;
    ALTER TABLE `file` CHANGE COLUMN `filename` `name` VARCHAR(255) NULL;
    ALTER TABLE `file` CHANGE COLUMN `cdnpath`  `cdnPath` VARCHAR(255) NULL;
    ALTER TABLE `file` DROP COLUMN `type`;



    UPDATE `file` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `file` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW() AFTER `cdnPath`;

    UPDATE `file` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `file` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created`;

    ALTER TABLE `file` ADD CONSTRAINT `FK_files_assetId` FOREIGN KEY (`assetId`) REFERENCES `asset`(`assetid`) ON UPDATE CASCADE ON DELETE RESTRICT;

    ALTER TABLE `file` ADD CONSTRAINT `FK_files_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT;


    ALTER TABLE `file` RENAME TO `files`;
END IF;



DROP TABLE IF EXISTS `assettype`;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='asset') ) THEN
    ALTER TABLE `asset` CHANGE COLUMN `assetid` `assetId` INT NOT NULL AUTO_INCREMENT;

    DELETE FROM `asset` WHERE `createdbyuserid` IS NULL;
    ALTER TABLE `asset` CHANGE COLUMN `createdbyuserid` `createdByUserId` INT NOT NULL;

    ALTER TABLE `asset` CHANGE COLUMN `editedbyuserid` `editedByUserId` INT NULL;

    UPDATE `asset` SET `statusid` = 2 WHERE `statusid` IS NULL AND assettypeid = 2; -- set releases without status to released, releases can't really be drafted right now
    UPDATE `asset` SET `statusid` = 1 WHERE `statusid` IS NULL AND assettypeid = 1;  -- set mods without status to draft
    ALTER TABLE `asset` CHANGE COLUMN `statusid` `statusId` INT NOT NULL;

    ALTER TABLE `asset` CHANGE COLUMN `assettypeid` `assetTypeId` INT NOT NULL;

    ALTER TABLE `asset` CHANGE COLUMN `tagscached` `tagsCached` TEXT NULL;

    UPDATE `asset` SET numsaved = 1 WHERE numsaved IS NULL;
    ALTER TABLE `asset` CHANGE COLUMN `numsaved` `numSaved` INT NOT NULL DEFAULT 1;


    UPDATE `asset` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `asset` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW() AFTER `numSaved`;

    UPDATE `asset` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `asset` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created`;

    ALTER TABLE `asset` ADD CONSTRAINT `FK_assets_createdByUserId` FOREIGN KEY (`createdByUserId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT;

    ALTER TABLE `asset` ADD CONSTRAINT `FK_assets_editedByUserId` FOREIGN KEY (`editedByUserId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT;


    ALTER TABLE `asset` RENAME TO `assets`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='mod') ) THEN
    ALTER TABLE `mod` CHANGE COLUMN `modid` `modId` INT NOT NULL AUTO_INCREMENT;

    ALTER TABLE `mod` CHANGE COLUMN `assetid` `assetId` INT NOT NULL;

    ALTER TABLE `mod` CHANGE COLUMN `urlalias` `urlAlias` VARCHAR(45) NULL;

    ALTER TABLE `mod` CHANGE COLUMN `cardlogofileid` `cardLogoFileId` INT NULL;
    ALTER TABLE `mod` CHANGE COLUMN `embedlogofileid` `embedLogoFileId` INT NULL;

    ALTER TABLE `mod` CHANGE COLUMN `homepageurl`     `homepageUrl`      VARCHAR(255) NULL;
    ALTER TABLE `mod` CHANGE COLUMN `sourcecodeurl`   `sourceCodeUrl`    VARCHAR(255) NULL;
    ALTER TABLE `mod` CHANGE COLUMN `trailervideourl` `trailerVideoUrl`  VARCHAR(255) NULL;
    ALTER TABLE `mod` CHANGE COLUMN `issuetrackerurl` `issueTrackerUrl`  VARCHAR(255) NULL;
    ALTER TABLE `mod` CHANGE COLUMN `wikiurl`         `wikiUrl`          VARCHAR(255) NULL;
    ALTER TABLE `mod` CHANGE COLUMN `donateurl`       `donateUrl`        VARCHAR(255) NULL;

    UPDATE `mod` m
        SET m.summary = IF(LENGTH(m.descriptionsearchable) <= 100, m.descriptionsearchable, CONCAT(SUBSTRING(m.descriptionsearchable, 1, 97), '...'))
        WHERE m.summary IS NULL;
    ALTER TABLE `mod` MODIFY COLUMN `summary` VARCHAR(100) NOT NULL;

    ALTER TABLE `mod` CHANGE COLUMN `descriptionsearchable` `descriptionSearchable` TEXT NULL;

    ALTER TABLE `mod` MODIFY COLUMN `follows` INT NOT NULL DEFAULT 0;

    ALTER TABLE `mod` CHANGE COLUMN `trendingpoints` `trendingPoints` INT NOT NULL DEFAULT 0;

    ALTER TABLE `mod` MODIFY COLUMN `type` ENUM('mod', 'externaltool', 'other') NULL DEFAULT 'mod';

    ALTER TABLE `mod` CHANGE COLUMN `lastreleased` `lastReleased` DATETIME NULL;

    ALTER TABLE `mod` DROP COLUMN `supportedversions`;

    UPDATE `mod` SET created = '0000-00-00' WHERE created IS NULL;
    ALTER TABLE `mod` MODIFY COLUMN `created` DATETIME NOT NULL DEFAULT NOW() AFTER `lastReleased`;

    UPDATE `mod` SET lastmodified = created WHERE lastmodified IS NULL;
    ALTER TABLE `mod` CHANGE COLUMN `lastmodified` `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created`;

    ALTER TABLE `mod` ADD CONSTRAINT `FK_mods_assetId` FOREIGN KEY (`assetId`) REFERENCES `assets`(`assetId`) ON UPDATE CASCADE ON DELETE CASCADE;
    UPDATE `mod` m 
        LEFT JOIN files f ON f.fileId = m.cardLogoFileId
        SET m.cardLogoFileId = NULL
        WHERE f.fileId IS NULL AND m.cardLogoFileId IS NOT NULL;
    ALTER TABLE `mod` ADD CONSTRAINT `FK_mods_cardLogoFileId` FOREIGN KEY (`cardLogoFileId`) REFERENCES `files`(`fileId`) ON UPDATE CASCADE ON DELETE SET NULL;
    UPDATE `mod` m 
        LEFT JOIN files f ON f.fileId = m.embedLogoFileId
        SET m.embedLogoFileId = NULL
        WHERE f.fileId IS NULL AND m.embedLogoFileId IS NOT NULL;
    ALTER TABLE `mod` ADD CONSTRAINT `FK_mods_embedLogoFileId` FOREIGN KEY (`embedLogoFileId`) REFERENCES `files`(`fileId`) ON UPDATE CASCADE ON DELETE SET NULL;


    ALTER TABLE `mod` RENAME TO `mods`;
END IF;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='GameVersions') ) THEN
 ALTER TABLE `ModPeekResult` RENAME TO `modPeekResults`;
 ALTER TABLE `GameVersions` RENAME TO `gameVersions`;
 ALTER TABLE `ModReleaseCompatibleGameVersions` RENAME TO `modReleaseCompatibleGameVersions`;
 ALTER TABLE `ModCompatibleGameVersionsCached` RENAME TO `modCompatibleGameVersionsCached`;
 ALTER TABLE `ModCompatibleMajorGameVersionsCached` RENAME TO `modCompatibleMajorGameVersionsCached`;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
