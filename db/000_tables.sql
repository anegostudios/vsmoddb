-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE SCHEMA IF NOT EXISTS `moddb` DEFAULT CHARACTER SET utf8 ;
USE `moddb` ;

-- -----------------------------------------------------
-- Table `moddb`.`asset`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`asset` (
  `assetid` INT NOT NULL AUTO_INCREMENT,
  `createdbyuserid` INT NULL,
  `editedbyuserid` INT NULL,
  `statusid` INT NULL,
  `assettypeid` INT NULL,
  `name` VARCHAR(255) NULL,
  `text` TEXT NULL,
  `tagscached` TEXT NULL,
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `numsaved` INT NULL,
  PRIMARY KEY (`assetid`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `Users` (
  `userId`            INT          NOT NULL AUTO_INCREMENT,
  `hash`              BINARY(10)   NOT NULL,
  `roleId`            INT          NOT NULL DEFAULT 3,
  `uid`               BINARY(18)   NOT NULL,
  `name`              VARCHAR(255) NOT NULL,
  `email`             VARCHAR(255) NOT NULL,
  `actionToken`       BINARY(8)    NOT NULL,
  `sessionToken`      BINARY(32)   NOT NULL,
  `sessionValidUntil` DATETIME     NOT NULL,
  `timezone`          VARCHAR(255) NOT NULL,
  `lastOnline`        DATETIME     NOT NULL,
  `bannedUntil`       DATETIME         NULL,
  `bio`               TEXT             NULL,
  `created`           DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`userId`),
  UNIQUE INDEX `email_UNIQUE` (`email`),
  INDEX `uid` (`uid`),
  INDEX `sessionToken` (`sessionToken`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `ModerationRecords` (
  `actionId`     INT      NOT NULL AUTO_INCREMENT,
  `targetUserId` INT      NOT NULL,
  `kind`         INT      NOT NULL,
  `recordId`     INT      NOT NULL COMMENT 'The id of the corresponding record in the kind-specific table',
  `until`        DATETIME NOT NULL,
  `moderatorId`  INT      NOT NULL,
  `reason`       TEXT         NULL,
  `created`      DATETIME NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`actionId`),
  INDEX `id_until` (`targetUserId`, `kind`, `until`),
  INDEX `recordId` (`recordId`),
	INDEX `moderatorid_index` (`moderatorId`),
  FOREIGN KEY (`targetUserId`) REFERENCES `Users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (`moderatorId`)  REFERENCES `Users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `Files` (
  `fileId`       INT          NOT NULL AUTO_INCREMENT,
  `assetId`      INT              NULL, -- has to be nullable because hovering files are not initially attached to a specific asset
  `assetTypeId`  INT          NOT NULL, -- also required for hovering files
  `userId`       INT          NOT NULL,
  `downloads`    INT          NOT NULL DEFAULT 0,
  `name`         VARCHAR(255)     NULL,
  `cdnPath`      VARCHAR(255)     NULL,
  `created`      DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`fileId`),
  INDEX `assetid` (`assetId`),
  INDEX `tempuploadtoken` (`userId`),
  INDEX `cdnpathidx` (`cdnPath`),  -- TOOD(Rennorb) @cleanup: Unused?
  CONSTRAINT `FK_Files_assetId` FOREIGN KEY (`assetId`) REFERENCES `asset`(`assetid`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `FK_Files_userId` FOREIGN KEY (`userId`) REFERENCES `Users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `FileImageData` (
  `fileId`       INT   NOT NULL,
  `hasThumbnail` BOOL  NOT NULL DEFAULT 0,
  `size`         POINT     NULL,
  PRIMARY KEY (`fileId`),
  CONSTRAINT `FK_FileImageData_fileId` FOREIGN KEY (`fileId`) REFERENCES `Files`(`fileId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `ModPeekResult` (
  `fileId`           INT             NOT NULL,
  `errors`           TEXT                NULL,
  `modIdentifier`    VARCHAR(255)        NULL,
  `modVersion`       BIGINT UNSIGNED NOT NULL,
  `type`             ENUM('Theme', 'Content', 'Code') NULL,
  `side`             ENUM('Universal', 'Server', 'Client') NULL,
  `requiredOnServer` BOOLEAN,
  `requiredOnClient` BOOLEAN,
  `networkVersion`   BIGINT UNSIGNED NOT NULL,
  `description`      TEXT                NULL,
  `iconPath`         VARCHAR(255)        NULL,
  `website`          VARCHAR(255)        NULL,
  `rawAuthors`       TEXT                NULL,
  `rawContributors`  TEXT                NULL,
  `rawDependencies`  TEXT                NULL,
  PRIMARY KEY (`fileId`),
  CONSTRAINT `fileId` FOREIGN KEY (`fileId`) REFERENCES `Files`(`fileId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;

-- Idea for dependencies
-- CREATE TABLE IF NOT EXISTS `ReleaseFileDependencies` (
--   `fileId`               INT             NOT NULL,
--   `dependencyIdentifier` VARCHAR(255)        NULL,
--   `dependencyMinVersion` BIGINT UNSIGNED NOT NULL,
--   CONSTRAINT `fileId` FOREIGN KEY (`fileId`) REFERENCES `Files`(`fileId`) ON UPDATE CASCADE ON DELETE CASCADE
-- )
-- ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `Status` (
  `statusId` INT          NOT NULL AUTO_INCREMENT,
  `code`     VARCHAR(255) NOT NULL,
  `name`     VARCHAR(255) NOT NULL,
  PRIMARY KEY (`statusId`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `Comments` (
  `commentId`           INT       NOT NULL AUTO_INCREMENT,
  `assetId`             INT       NOT NULL,
  `userId`              INT       NOT NULL,
  `text`                TEXT      NOT NULL,
  `contentLastModified` DATETIME      NULL,
  `lastModaction`       INT           NULL,
  `deleted`             BOOL      NOT NULL DEFAULT 0,
  `created`             DATETIME  NOT NULL DEFAULT NOW(),
  `lastModified`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`commentId`),
  INDEX `assetid`(`assetId`),
  INDEX `created`(`created`), -- for the main page query that shows the latest 20 comments
  -- CONSTRAINT `FK_Comments_assetId` FOREIGN KEY (`assetId`) REFERENCES `asset`(`assetId`) ON UPDATE CASCADE ON DELETE CASCADE, -- TODO(Rennorb) @cleanup: For moderation reasons we allow comment asset references these to be dangling for now.
  CONSTRAINT `FK_Comments_userId` FOREIGN KEY (`userId`) REFERENCES `Users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_Comments_lastModaction` FOREIGN KEY (`lastModaction`) REFERENCES `ModerationRecords`(`actionId`) ON UPDATE CASCADE ON DELETE RESTRICT
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `ModReleases` (
  `releaseId`    INT             NOT NULL AUTO_INCREMENT,
  `assetId`      INT             NOT NULL,
  `modId`        INT             NOT NULL,
  `identifier`   VARCHAR(255)        NULL, -- TODO
  `version`      BIGINT UNSIGNED NOT NULL,
  `detailText`   TEXT                NULL,
  `created`      DATETIME        NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`releaseId`),
  -- This has to include identifier, as one mod can contain releases for multiple identifier's.
  -- This also has to include the modid, as tool/other mods dont need to have a identifier.
  UNIQUE INDEX `identifier` (`modId`, `identifier`, `version`),
  UNIQUE INDEX `assetid` (`assetId`),
  INDEX `modid` (`modId`),
  CONSTRAINT `FK_ModReleases_assetId` FOREIGN KEY (`assetId`) REFERENCES `asset`(`assetid`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_ModReleases_modId` FOREIGN KEY (`modId`) REFERENCES `mod`(`modid`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `Changelogs` (
  `changelogId`  INT       NOT NULL AUTO_INCREMENT,
  `assetId`      INT           NULL, -- null for file deletions as of now
  `userId`       INT       NOT NULL,
  `text`         TEXT      NOT NULL,
  `created`      DATETIME  NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`changelogId`),
  INDEX `assetId` (`assetId`),
  INDEX `userId` (`userId`),
  CONSTRAINT `FK_Changelogs_userId` FOREIGN KEY (`userId`) REFERENCES `Users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `Tags` (
  `tagId`        INT          NOT NULL AUTO_INCREMENT,
  `kind`         TINYINT      NOT NULL,
  `name`         VARCHAR(255) NOT NULL,
  `text`         TEXT         NOT NULL,
  `color`        INT UNSIGNED NOT NULL,
  `created`      DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tagId`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `ModTags` (
  `modId`        INT       NOT NULL,
  `tagId`        INT       NOT NULL,
  `created`      DATETIME  NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`modId`, `tagId`),
  CONSTRAINT `FK_Changelogs_modId` FOREIGN KEY (`modID`) REFERENCES `mod`(`modid`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_ModTags_tagId` FOREIGN KEY (`tagId`) REFERENCES `Tags`(`tagId`) ON UPDATE CASCADE ON DELETE CASCADE,
)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`mod`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`mod` (
  `modid` INT NOT NULL AUTO_INCREMENT,
  `assetid` INT NULL,
  `urlalias` VARCHAR(45) NULL,
  `cardlogofileid` INT NULL,
  `embedlogofileid` INT NULL,
  `homepageurl` VARCHAR(255) NULL,
  `sourcecodeurl` VARCHAR(255) NULL,
  `trailervideourl` VARCHAR(255) NULL,
  `issuetrackerurl` VARCHAR(255) NULL,
  `wikiurl` VARCHAR(255) NULL,
  `donateurl` VARCHAR(255) NULL,
  `summary` VARCHAR(100) NULL,
  `descriptionsearchable` TEXT NULL, -- No fulltext index for now, we didnt have one before. Might want to look into that at some point
  `downloads` INT NOT NULL DEFAULT 0,
  `follows` INT NULL DEFAULT 0,
  `trendingpoints` INT NOT NULL DEFAULT 0,
  `comments` INT NOT NULL DEFAULT 0,
  `side` ENUM('client', 'server', 'both') NULL,
  `type` ENUM('mod', 'externaltool', 'other') NULL DEFAULT 'mod',
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastreleased` DATETIME NULL,
  `supportedversions` TEXT NULL,
  PRIMARY KEY (`modid`),
  FULLTEXT INDEX `supportedversions` (`supportedversions`),
  INDEX `urlalias` (`urlalias`),
  INDEX `trendingpoints_id` (`trendingpoints`, `modid`),
  INDEX `lastreleased_id` (`lastreleased`, `modid`),
  INDEX `downloads_id` (`downloads`, `modid`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `Roles` (
  `roleId`       INT          NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(255) NOT NULL,
  `name`         VARCHAR(255) NOT NULL,
  `created`      DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`roleId`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `FileDownloadTracking` (
  `ipAddress`    VARCHAR(255) NOT NULL,
  `fileId`       INT          NOT NULL,
  `lastDownload` DATETIME     NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`ipAddress`, `fileId`),
  INDEX `ipaddress` (`ipAddress`),
  INDEX `fileid` (`fileId`),
  INDEX `lastDownload` (`lastDownload`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `Notifications` (
  `notificationId` INT      NOT NULL AUTO_INCREMENT,
  `read`           TINYINT  NOT NULL DEFAULT 0,
  `userId`         INT      NOT NULL,
  `type`           ENUM('newcomment', 'mentioncomment', 'newrelease', 'teaminvite', 'modownershiptransfer', 'modlocked', 'modunlockrequest', 'modunlocked') NOT NULL,
  `recordId`       INT      NOT NULL,
  `created`        DATETIME NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`notificationId`),
  INDEX `userid` (`userId`),
  CONSTRAINT `FK_Notifications_userId` FOREIGN KEY (`userId`) REFERENCES `Users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `GameVersions` (
  `version` BIGINT UNSIGNED NOT NULL,   -- compiled version
  `sortIndex` INT NOT NULL, -- sequential n+1 sort order to check for sequential sequences
  PRIMARY KEY (`version`)
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `ModReleaseCompatibleGameVersions` (
  `releaseId` INT NOT NULL,
  `gameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`releaseId`, `gameVersion`)
)
ENGINE = InnoDB;

-- same information as joining mod + rleease + ReleaseCompatGameversions, cached for searching
CREATE TABLE IF NOT EXISTS `ModCompatibleGameVersionsCached` (
  `modId` INT NOT NULL,
  `gameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`modId`, `gameVersion`),
  INDEX `version` (`gameVersion`)
)
ENGINE = InnoDB;

-- same information as unique floorToMajor(joining mod + rleease + ReleaseCompatGameversions), cached for searching
CREATE TABLE IF NOT EXISTS `ModCompatibleMajorGameVersionsCached` (
  `modId` INT NOT NULL,
  `majorGameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`majorGameVersion`, `modId`),
  INDEX `version` (`majorGameVersion`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `UserFollowedMods` (
  `modId`   INT      NOT NULL,
  `userId`  INT      NOT NULL,
  `created` DATETIME NOT NULL DEFAULT NOW(),
  `flags`   TINYINT  NOT NULL DEFAULT 1, -- by default follow with notifications
  UNIQUE INDEX `modiduserid` (`modId`, `userId`),
  INDEX `userid` (`userId`),
  CONSTRAINT `FK_UserFolowedMods_modId`  FOREIGN KEY (`modId`)  REFERENCES `mod`(`modid`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_UserFolowedMods_userId` FOREIGN KEY (`userId`) REFERENCES `Users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `ModTeamMembers` (
  `teamMemberId` INT(11)    NOT NULL AUTO_INCREMENT,
  `userId`       INT(11)    NOT NULL,
  `modId`        INT(11)    NOT NULL,
  `canEdit`      TINYINT(1) NOT NULL DEFAULT 0,
  `created`      DATETIME   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`teamMemberId`),
  INDEX `modid_userid` (`modId`, `userId`),
  INDEX `userid` (`userId`),
  INDEX `modid` (`modId`),
  CONSTRAINT `FK_ModTeamMembers_modId`  FOREIGN KEY (`modId`)  REFERENCES `mod`(`modid`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_ModTeamMembers_userId` FOREIGN KEY (`userId`) REFERENCES `Users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

START TRANSACTION;
INSERT INTO `Status` (`statusId`, `code`, `name`) VALUES (1, 'draft', 'Draft');
INSERT INTO `Status` (`statusId`, `code`, `name`) VALUES (2, 'published', 'Published');
INSERT INTO `Status` (`statusId`, `code`, `name`) VALUES (4, 'locked', 'Locked');
COMMIT;

START TRANSACTION;
INSERT INTO `Roles` (`roleId`, `code`, `name`) VALUES (1, 'admin', 'Admin');
INSERT INTO `Roles` (`roleId`, `code`, `name`) VALUES (2, 'moderator', 'Moderator');
INSERT INTO `Roles` (`roleId`, `code`, `name`) VALUES (3, 'player', 'Player');
INSERT INTO `Roles` (`roleId`, `code`, `name`) VALUES (4, 'player_nc', 'Player (commenting disabled)');
COMMIT;
