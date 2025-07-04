-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema moddb
-- -----------------------------------------------------
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
  PRIMARY KEY (`assetid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`user`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`user` (
  `userid` INT NOT NULL AUTO_INCREMENT,
  `roleid` INT NULL DEFAULT 3,
  `uid` VARCHAR(255) NULL,
  `name` VARCHAR(255) NULL,
  `password` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `actiontoken` VARCHAR(255) NULL,
  `sessiontoken` VARCHAR(255) NULL,
  `sessiontokenvaliduntil` DATETIME NULL,
  `timezone` VARCHAR(255) NULL,
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastonline` DATETIME NULL,
  `banneduntil` DATETIME NULL,
  `bio` TEXT NULL,
  PRIMARY KEY (`userid`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC),
  INDEX `uid` (`uid` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`moderationrecord`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`moderationrecord` (
  `actionid` INT NOT NULL AUTO_INCREMENT,
  `created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `targetuserid` INT NOT NULL,
  `kind` INT NOT NULL,
  `recordid` INT NOT NULL COMMENT 'The id of the corresponding record in the kind-specific table',
  `until` DATETIME NULL,
  `moderatorid` INT NOT NULL,
  `reason` TEXT NULL,
  PRIMARY KEY (`actionid`),
  KEY id_until (`targetuserid`, `kind`, `until`),
	INDEX `moderatorid_index` (`moderatorid`),
  FOREIGN KEY (`targetuserid`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (`moderatorid`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE RESTRICT)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`file`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`file` (
  `fileid` INT NOT NULL AUTO_INCREMENT,
  `assetid` INT NULL,
  `assettypeid` INT NULL COMMENT 'Required for assets that don''t exist yet, otherwise we cannot verify if the right assettypeid was passed on during asset creation',
  `userid` INT NULL,
  `downloads` INT NULL DEFAULT 0,
  `filename` VARCHAR(255) NULL,
  `cdnpath` VARCHAR(255) NULL,
  `type` ENUM('portrait', 'asset', 'shape', 'texture', 'sound') NULL,
  `hasthumbnail` BOOL NOT NULL DEFAULT 0, -- could maybe be merged with type
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `imagesize` POINT NULL, -- :ImageSizeMigration
  PRIMARY KEY (`fileid`),
  INDEX `assetid` (`assetid` ASC),
  INDEX `tempuploadtoken` (`userid` ASC),
  INDEX `cdnpathidx` (`cdnpath`)) -- used for fast download pingback
ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `moddb`.`modpeek_result`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`modpeek_result` (
  `fileid` INT NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `detectedmodidstr` VARCHAR(255),
  `detectedmodversion` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`fileid`))
ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `moddb`.`status`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`status` (
  `statusid` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NULL,
  `name` VARCHAR(255) NULL,
  `created` DATETIME NULL DEFAULT NULL,
  `sortorder` INT NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`statusid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`comment`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`comment` (
  `commentid` INT NOT NULL AUTO_INCREMENT,
  `assetid` INT NULL,
  `userid` INT NULL,
  `text` TEXT NULL,
  `created` DATETIME NULL,
  `modifieddate` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastmodaction` INT NULL,
  `deleted` BOOL NOT NULL DEFAULT 0,
  PRIMARY KEY (`commentid`),
  FOREIGN KEY (`lastmodaction`) REFERENCES `moderationrecord`(`actionid`) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX `created`(`created`) -- for the main page query that shows the latest 20 comments
)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`assettype`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`assettype` (
  `assettypeid` INT NOT NULL AUTO_INCREMENT,
  `maxfiles` TINYINT NULL DEFAULT 10,
  `maxfilesizekb` INT NULL DEFAULT 2000,
  `allowedfiletypes` VARCHAR(255) NULL DEFAULT 'png|jpg|gif',
  `code` VARCHAR(255) NULL,
  `name` VARCHAR(255) NULL,
  PRIMARY KEY (`assettypeid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`release`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`release` (
  `releaseid` INT NOT NULL AUTO_INCREMENT,
  `assetid` INT NULL,
  `modid` INT NULL,
  `modidstr` VARCHAR(255) NULL,
  `modversion` BIGINT UNSIGNED NOT NULL,
  `releasedate` VARCHAR(255) NULL,
  `inprogress` TINYINT NULL,
  `detailtext` TEXT NULL,
  `releaseorder` INT NULL,
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`releaseid`),
  -- This has to include modidstr, as one mod can contain releases for multiple modidstr's.
  -- This also has to include the modid, as tool/other mods dont need to have a modidstr.
  UNIQUE INDEX `identifier` (`modid`, `modidstr`, `modversion`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`changelog`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`changelog` (
  `changelogid` INT NOT NULL AUTO_INCREMENT,
  `assetid` INT NULL,
  `userid` INT NULL,
  `text` TEXT NULL,
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`changelogid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`tag`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`tag` (
  `tagid` INT NOT NULL AUTO_INCREMENT,
  `assettypeid` INT NULL,
  `tagtypeid` INT NULL,
  `name` VARCHAR(255) NULL,
  `text` TEXT NULL,
  `color` VARCHAR(255) NULL,
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tagid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`assettag`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`assettag` (
  `assettagid` INT NOT NULL AUTO_INCREMENT,
  `assetid` INT NULL,
  `tagid` INT NULL,
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assettagid`))
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
  INDEX `urlalias` (`urlalias` ASC),
  INDEX `trendingpoints_id` (`trendingpoints`, `modid`),
  INDEX `lastreleased_id` (`lastreleased`, `modid`),
  INDEX `downloads_id` (`downloads`, `modid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`language`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`language` (
  `languageid` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NULL,
  `name` VARCHAR(255) NULL,
  PRIMARY KEY (`languageid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`tagtype`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`tagtype` (
  `tagtypeid` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NULL,
  `name` VARCHAR(255) NULL,
  `text` TEXT NULL,
  `created` DATETIME NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tagtypeid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`role`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`role` (
  `roleid` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NULL,
  `name` VARCHAR(255) NULL,
  `created` DATETIME NULL DEFAULT NULL,
  `sortorder` INT NULL,
  `lastmodified` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`roleid`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`downloadip`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`downloadip` (
  `ipaddress` VARCHAR(255) NOT NULL,
  `fileid` INT NOT NULL,
  `date` DATETIME NULL,
  PRIMARY KEY (`ipaddress`, `fileid`),
  INDEX `ipaddress` (`ipaddress` ASC),
  INDEX `fileid` (`fileid` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`notification`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`notification` (
  `notificationid` INT NOT NULL AUTO_INCREMENT,
  `read` TINYINT NOT NULL DEFAULT 0,
  `userid` VARCHAR(255) NULL,
  `type` ENUM('newcomment', 'mentioncomment', 'newrelease', 'teaminvite', 'modownershiptransfer', 'modlocked', 'modunlockrequest', 'modunlocked') NULL,
  `recordid` INT NULL,
  `created` DATETIME NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`notificationid`),
  INDEX `userid` (`userid` ASC))
ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS `GameVersions` (
  `version` BIGINT UNSIGNED NOT NULL,   -- compiled version
  `sortIndex` INT NOT NULL, -- sequential n+1 sort order to check for sequential sequences
  PRIMARY KEY (`version`))
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `ModReleaseCompatibleGameVersions` (
  `releaseId` INT NOT NULL,
  `gameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`releaseId`, `gameVersion`))
ENGINE = InnoDB;

-- same information as joining mod + rleease + ReleaseCompatGameversions, cached for searching
CREATE TABLE IF NOT EXISTS `ModCompatibleGameVersionsCached` (
  `modId` INT NOT NULL,
  `gameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`modId`, `gameVersion`),
  INDEX `version` (`gameVersion` ASC))
ENGINE = InnoDB;

-- same information as unique floorToMajor(joining mod + rleease + ReleaseCompatGameversions), cached for searching
CREATE TABLE IF NOT EXISTS `ModCompatibleMajorGameVersionsCached` (
  `modId` INT NOT NULL,
  `majorGameVersion` BIGINT UNSIGNED NOT NULL, -- compiled version
  PRIMARY KEY (`majorGameVersion`, `modId`),
  INDEX `version` (`majorGameVersion` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `moddb`.`follow`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`follow` (
  `modid` INT NULL,
  `userid` INT NULL,
  `created` DATETIME NULL DEFAULT NULL,
  `flags` TINYINT NOT NULL DEFAULT 1, -- by default follow with notifications
  UNIQUE INDEX `modiduserid` (`modid` ASC, `userid` ASC),
  INDEX `userid` (`userid` ASC))
ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `moddb`.`teammember`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `teammember` (
  `teammemberid` INT(11) NOT NULL AUTO_INCREMENT,
  `userid` INT(11) NOT NULL,
  `modid` INT(11) NOT NULL,
  `canedit` TINYINT(1) NOT NULL DEFAULT '0',
  `created` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`teammemberid`),
  INDEX `modid_userid` (`modid` ASC, `userid` ASC),
  INDEX `userid` (`userid` ASC),
  INDEX `modid` (`modid` ASC)
) ENGINE = InnoDB;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- -----------------------------------------------------
-- Data for table `moddb`.`status`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`status` (`statusid`, `code`, `name`, `created`, `sortorder`) VALUES (1, 'draft', 'Draft', NOW(), 1);
INSERT INTO `moddb`.`status` (`statusid`, `code`, `name`, `created`, `sortorder`) VALUES (2, 'published', 'Published', NOW(), 2);
INSERT INTO `moddb`.`status` (`statusid`, `code`, `name`, `created`, `sortorder`) VALUES (4, 'locked', 'Locked', NOW(), 4);

COMMIT;


-- -----------------------------------------------------
-- Data for table `moddb`.`assettype`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`assettype` (`assettypeid`, `maxfiles`, `maxfilesizekb`, `allowedfiletypes`, `code`, `name`) VALUES (1, 12, 2048, 'png|jpg|gif', 'mod', 'Mod');
INSERT INTO `moddb`.`assettype` (`assettypeid`, `maxfiles`, `maxfilesizekb`, `allowedfiletypes`, `code`, `name`) VALUES (2, 1, 40960, 'dll|zip|cs', 'release', 'Release');

COMMIT;


-- -----------------------------------------------------
-- Data for table `moddb`.`language`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`language` (`languageid`, `code`, `name`) VALUES (1, 'en', 'English');
INSERT INTO `moddb`.`language` (`languageid`, `code`, `name`) VALUES (2, 'ar', 'Arabic');
INSERT INTO `moddb`.`language` (`languageid`, `code`, `name`) VALUES (3, 'nl', 'Dutch');

COMMIT;


-- -----------------------------------------------------
-- Data for table `moddb`.`tagtype`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`tagtype` (`tagtypeid`, `code`, `name`, `text`, `created`, `lastmodified`) VALUES (2, 'category', 'Category', NULL, NULL, NULL);

COMMIT;

-- -----------------------------------------------------
-- Data for table `moddb`.`role`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`role` (`roleid`, `code`, `name`, `created`, `sortorder`, `lastmodified`) VALUES (1, 'admin', 'Admin', NULL, NULL, NULL);
INSERT INTO `moddb`.`role` (`roleid`, `code`, `name`, `created`, `sortorder`, `lastmodified`) VALUES (2, 'moderator', 'Moderator', NULL, NULL, NULL);
INSERT INTO `moddb`.`role` (`roleid`, `code`, `name`, `created`, `sortorder`, `lastmodified`) VALUES (3, 'player', 'Player', NULL, NULL, NULL);
INSERT INTO `moddb`.`role` (`roleid`, `code`, `name`, `created`, `sortorder`, `lastmodified`) VALUES (4, 'player_nc', 'Player (commenting disabled)', NULL, NULL, NULL);

COMMIT;
