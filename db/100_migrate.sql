DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN


IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='user'
	AND COLUMN_NAME='banneduntil') ) THEN
		ALTER TABLE moddb.user ADD banneduntil DATETIME NULL;
END IF;

-- -----------------------------------------------------
-- Table `moddb`.`moderationrecord`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`moderationrecord` (
  `actionid` INT NOT NULL AUTO_INCREMENT,
	`created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `targetuserid` INT NOT NULL,
	`kind` INT NOT NULL,
  `until` DATETIME NULL,
  `moderatorid` INT NOT NULL,
  `reason` TEXT NULL,
  PRIMARY KEY (`actionid`),
  KEY id_until (`targetuserid`, `kind`, `until`),
  FOREIGN KEY (`targetuserid`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (`moderatorid`) REFERENCES `user`(`userid`) ON UPDATE CASCADE ON DELETE RESTRICT)
ENGINE = InnoDB;


IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='comment'
	AND COLUMN_NAME='lastmodaction') ) THEN
		ALTER TABLE moddb.comment ADD lastmodaction INT NULL;
		ALTER TABLE moddb.comment ADD deleted BOOL NOT NULL DEFAULT 0;
		ALTER TABLE moddb.comment ADD CONSTRAINT FOREIGN KEY (lastmodaction) REFERENCES moderationrecord(actionid) ON UPDATE CASCADE ON DELETE RESTRICT;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
