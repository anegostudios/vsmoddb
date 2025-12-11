-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE SCHEMA IF NOT EXISTS `moddb` DEFAULT CHARACTER SET utf8 ;
USE `moddb` ;

CREATE TABLE IF NOT EXISTS `assets` (
  `assetId`         INT          NOT NULL AUTO_INCREMENT,
  `createdByUserId` INT          NOT NULL,
  `editedByUserId`  INT              NULL,
  `statusId`        INT          NOT NULL,
  `assetTypeId`     INT          NOT NULL,
  `name`            VARCHAR(255) CHARACTER SET utf8mb4 NULL,
  `text`            TEXT CHARACTER SET utf8mb4 NULL,
  `tagsCached`      TEXT             NULL, -- same data as LEFT JOIN modTags, LEFT JOIN tags, GROUP_CONCAT(CONCAT(tag...) DELIMITER '\r\n')
  `numSaved`        INT          NOT NULL DEFAULT 1,
  `created`         DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assetId`),
  INDEX `createdByUserId` (`createdByUserId`),
  CONSTRAINT `FK_assets_createdByUserId` FOREIGN KEY (`createdByUserId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `FK_assets_editedByUserId` FOREIGN KEY (`editedByUserId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT,
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `users` (
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
  `bio`               TEXT CHARACTER SET utf8mb4 NULL,
  `created`           DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`userId`),
  UNIQUE INDEX `email_UNIQUE` (`email`),
  INDEX `uid` (`uid`),
  INDEX `sessionToken` (`sessionToken`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `moderationRecords` (
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
  CONSTRAINT `FK_moderationRecords_targetUserId` FOREIGN KEY (`targetUserId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `FK_moderationRecords_moderatorId` FOREIGN KEY (`moderatorId`)  REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `files` (
  `fileId`       INT          NOT NULL AUTO_INCREMENT,
  `assetId`      INT              NULL, -- has to be nullable because hovering files are not initially attached to a specific asset
  `assetTypeId`  INT          NOT NULL, -- also required for hovering files
  `userId`       INT          NOT NULL,
  `downloads`    INT          NOT NULL DEFAULT 0,
  `name`         VARCHAR(255)     NULL,
  `cdnPath`      VARCHAR(255)     NULL,
  `order`        TINYINT      NOT NULL,
  `created`      DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`fileId`),
  INDEX `assetid` (`assetId`),
  INDEX `tempuploadtoken` (`userId`),
  INDEX `cdnpathidx` (`cdnPath`),  -- TOOD(Rennorb) @cleanup: Unused?
  CONSTRAINT `FK_files_assetId` FOREIGN KEY (`assetId`) REFERENCES `assets`(`assetId`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `FK_files_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `fileImageData` (
  `fileId`       INT   NOT NULL,
  `hasThumbnail` BOOL  NOT NULL DEFAULT 0,
  `size`         POINT     NULL,
  PRIMARY KEY (`fileId`),
  CONSTRAINT `FK_fileImageData_fileId` FOREIGN KEY (`fileId`) REFERENCES `files`(`fileId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `modPeekResults` (
  `fileId`           INT             NOT NULL,
  `errors`           TEXT CHARACTER SET utf8mb4 NULL,
  `modIdentifier`    VARCHAR(255)        NULL,
  `modVersion`       BIGINT UNSIGNED NOT NULL,
  `type`             ENUM('Theme', 'Content', 'Code') NULL,
  `side`             ENUM('Universal', 'Server', 'Client') NULL,
  `requiredOnServer` BOOLEAN,
  `requiredOnClient` BOOLEAN,
  `networkVersion`   BIGINT UNSIGNED NOT NULL,
  `description`      TEXT CHARACTER SET utf8mb4 NULL,
  `iconPath`         VARCHAR(255)        NULL,
  `website`          VARCHAR(255)        NULL,
  `rawAuthors`       TEXT CHARACTER SET utf8mb4 NULL,
  `rawContributors`  TEXT CHARACTER SET utf8mb4 NULL,
  `rawDependencies`  TEXT                NULL,
  PRIMARY KEY (`fileId`),
  CONSTRAINT `fileId` FOREIGN KEY (`fileId`) REFERENCES `files`(`fileId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;

-- Idea for dependencies
-- CREATE TABLE IF NOT EXISTS `releaseFileDependencies` (
--   `fileId`               INT             NOT NULL,
--   `dependencyIdentifier` VARCHAR(255)        NULL,
--   `dependencyMinVersion` BIGINT UNSIGNED NOT NULL,
--   CONSTRAINT `fileId` FOREIGN KEY (`fileId`) REFERENCES `files`(`fileId`) ON UPDATE CASCADE ON DELETE CASCADE
-- )
-- ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `status` (
  `statusId` INT          NOT NULL AUTO_INCREMENT,
  `code`     VARCHAR(255) NOT NULL,
  `name`     VARCHAR(255) NOT NULL,
  PRIMARY KEY (`statusId`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `comments` (
  `commentId`           INT       NOT NULL AUTO_INCREMENT,
  `assetId`             INT       NOT NULL,
  `userId`              INT       NOT NULL,
  `text`                TEXT CHARACTER SET utf8mb4 NOT NULL,
  `contentLastModified` DATETIME      NULL,
  `lastModaction`       INT           NULL,
  `deleted`             BOOL      NOT NULL DEFAULT 0,
  `created`             DATETIME  NOT NULL DEFAULT NOW(),
  `lastModified`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`commentId`),
  INDEX `assetid`(`assetId`), -- :NoCommentAssetFK
  INDEX `created`(`created`), -- for the main page query that shows the latest 20 comments
  -- CONSTRAINT `FK_Comments_assetId` FOREIGN KEY (`assetId`) REFERENCES `assets`(`assetId`) ON UPDATE CASCADE ON DELETE CASCADE, -- TODO(Rennorb) @cleanup: For moderation reasons we allow comment asset references these to be dangling for now.
  CONSTRAINT `FK_comments_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_comments_lastModaction` FOREIGN KEY (`lastModaction`) REFERENCES `moderationRecords`(`actionId`) ON UPDATE CASCADE ON DELETE RESTRICT
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `changelogs` (
  `changelogId`  INT       NOT NULL AUTO_INCREMENT,
  `assetId`      INT           NULL, -- null for file deletions as of now
  `userId`       INT       NOT NULL,
  `text`         TEXT CHARACTER SET utf8mb4 NOT NULL,
  `created`      DATETIME  NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`changelogId`),
  INDEX `assetId` (`assetId`),
  INDEX `userId` (`userId`),
  CONSTRAINT `FK_changelogs_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `tags` (
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


CREATE TABLE IF NOT EXISTS `mods` (
  `modId`                 INT          NOT NULL AUTO_INCREMENT,
  `assetId`               INT          NOT NULL,
  `urlAlias`              VARCHAR(45)      NULL,
  `cardLogoFileId`        INT              NULL,
  `embedLogoFileId`       INT              NULL,
  `homepageUrl`           VARCHAR(255)     NULL,
  `sourceCodeUrl`         VARCHAR(255)     NULL,
  `trailerVideoUrl`       VARCHAR(255)     NULL,
  `issueTrackerUrl`       VARCHAR(255)     NULL,
  `wikiUrl`               VARCHAR(255)     NULL,
  `donateUrl`             VARCHAR(255)     NULL,
  `summary`               VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL,
  `descriptionSearchable` TEXT CHARACTER SET utf8mb4 NULL, -- No fulltext index for now, we didnt have one before. Might want to look into that at some point
  `downloads`             INT          NOT NULL DEFAULT 0,
  `follows`               INT          NOT NULL DEFAULT 0,
  `trendingPoints`        INT          NOT NULL DEFAULT 0,
  `comments`              INT          NOT NULL DEFAULT 0,
  `side`                  ENUM('client', 'server', 'both') NULL,
  `category`              TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `uploadLimitOverwrite`  INT              NULL,
  `lastReleased`          DATETIME         NULL,
  `created`               DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`modId`),
  INDEX `urlalias` (`urlAlias`),
  INDEX `trendingpoints_id` (`trendingPoints`, `modId`),
  INDEX `lastreleased_id` (`lastReleased`, `modId`),
  INDEX `downloads_id` (`downloads`, `modId`),
  CONSTRAINT `FK_mods_assetId` FOREIGN KEY (`assetId`) REFERENCES `assets`(`assetId`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_mods_cardLogoFileId` FOREIGN KEY (`cardLogoFileId`) REFERENCES `files`(`fileId`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `FK_mods_embedLogoFileId` FOREIGN KEY (`embedLogoFileId`) REFERENCES `files`(`fileId`) ON UPDATE CASCADE ON DELETE SET NULL
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `modTags` (
  `modId`        INT       NOT NULL,
  `tagId`        INT       NOT NULL,
  `created`      DATETIME  NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`modId`, `tagId`),
  CONSTRAINT `FK_modTags_modId` FOREIGN KEY (`modId`) REFERENCES `mods`(`modId`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_modTags_tagId` FOREIGN KEY (`tagId`) REFERENCES `tags`(`tagId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `modReleases` (
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
  -- This also has to include the modId, as tool/other mods dont need to have a identifier.
  UNIQUE INDEX `identifier` (`modId`, `identifier`, `version`),
  UNIQUE INDEX `assetid` (`assetId`),
  INDEX `modid` (`modId`),
  CONSTRAINT `FK_modReleases_assetId` FOREIGN KEY (`assetId`) REFERENCES `assets`(`assetId`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_modReleases_modId` FOREIGN KEY (`modId`) REFERENCES `mods`(`modId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `modReleaseRetractions` (
  `releaseId`      INT       NOT NULL,
  `reason`         TEXT CHARACTER SET utf8mb4 NOT NULL,
  `created`        DATETIME  NOT NULL DEFAULT NOW(),
  `lastModified`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastModifiedBy` INT       NOT NULL,
  PRIMARY KEY (`releaseId`),
  CONSTRAINT `FK_FK_modReleaseRetractions_releaseId` FOREIGN KEY (`releaseId`) REFERENCES `modReleases`(`releaseId`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_modReleaseRetractions_lastModifiedBy` FOREIGN KEY (`lastModifiedBy`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE,
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `roles` (
  `roleId`       INT          NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(255) NOT NULL,
  `name`         VARCHAR(255) NOT NULL,
  `created`      DATETIME     NOT NULL DEFAULT NOW(),
  `lastModified` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`roleId`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `fileDownloadTracking` (
  `ipAddress`    VARCHAR(255) NOT NULL,
  `fileId`       INT          NOT NULL,
  `lastDownload` DATETIME     NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`ipAddress`, `fileId`),
  INDEX `ipaddress` (`ipAddress`),
  INDEX `fileid` (`fileId`),
  INDEX `lastDownload` (`lastDownload`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `notifications` (
  `notificationId` INT      NOT NULL AUTO_INCREMENT,
  `read`           TINYINT  NOT NULL DEFAULT 0,
  `userId`         INT      NOT NULL,
  `kind`           TINYINT  NOT NULL,
  `recordId`       INT      NOT NULL,
  `created`        DATETIME NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`notificationId`),
  INDEX `userid` (`userId`),
  CONSTRAINT `FK_notifications_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `gameVersions` (
  `version` BIGINT UNSIGNED NOT NULL,   -- compiled version
  `sortIndex` INT NOT NULL, -- sequential n+1 sort order to check for sequential sequences
  PRIMARY KEY (`version`)
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `modReleaseCompatibleGameVersions` (
  `releaseId` INT NOT NULL,
  `gameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`releaseId`, `gameVersion`)
)
ENGINE = InnoDB;

-- same information as joining mod + release + ReleaseCompatGameVersions, cached for searching
CREATE TABLE IF NOT EXISTS `modCompatibleGameVersionsCached` (
  `modId` INT NOT NULL,
  `gameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`modId`, `gameVersion`),
  INDEX `version` (`gameVersion`)
)
ENGINE = InnoDB;

-- same information as unique floorToMajor(joining mod + release + ReleaseCompatGameVersions), cached for searching
CREATE TABLE IF NOT EXISTS `modCompatibleMajorGameVersionsCached` (
  `modId` INT NOT NULL,
  `majorGameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`majorGameVersion`, `modId`),
  INDEX `version` (`majorGameVersion`)
)
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `userFollowedMods` (
  `modId`   INT      NOT NULL,
  `userId`  INT      NOT NULL,
  `created` DATETIME NOT NULL DEFAULT NOW(),
  `flags`   TINYINT  NOT NULL DEFAULT 1, -- by default follow with notifications
  UNIQUE INDEX `modiduserid` (`modId`, `userId`),
  INDEX `userid` (`userId`),
  CONSTRAINT `FK_userFolowedMods_modId`  FOREIGN KEY (`modId`)  REFERENCES `mods`(`modId`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_userFolowedMods_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `modTeamMembers` (
  `teamMemberId` INT(11)    NOT NULL AUTO_INCREMENT,
  `userId`       INT(11)    NOT NULL,
  `modId`        INT(11)    NOT NULL,
  `canEdit`      TINYINT(1) NOT NULL DEFAULT 0,
  `created`      DATETIME   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`teamMemberId`),
  INDEX `modid_userid` (`modId`, `userId`),
  INDEX `userid` (`userId`),
  INDEX `modid` (`modId`),
  CONSTRAINT `FK_modTeamMembers_modId`  FOREIGN KEY (`modId`)  REFERENCES `mods`(`modId`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_modTeamMembers_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

START TRANSACTION;
INSERT INTO `status` (`statusId`, `code`, `name`) VALUES (1, 'draft', 'Draft');
INSERT INTO `status` (`statusId`, `code`, `name`) VALUES (2, 'published', 'Published');
INSERT INTO `status` (`statusId`, `code`, `name`) VALUES (4, 'locked', 'Locked');
COMMIT;

START TRANSACTION;
INSERT INTO `roles` (`roleId`, `code`, `name`) VALUES (1, 'admin', 'Admin');
INSERT INTO `roles` (`roleId`, `code`, `name`) VALUES (2, 'moderator', 'Moderator');
INSERT INTO `roles` (`roleId`, `code`, `name`) VALUES (3, 'player', 'Player');
INSERT INTO `roles` (`roleId`, `code`, `name`) VALUES (4, 'player_nc', 'Player (commenting disabled)');
COMMIT;
