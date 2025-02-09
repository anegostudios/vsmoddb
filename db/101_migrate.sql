DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN


IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='file'
	AND COLUMN_NAME='cdnpath') ) THEN
		ALTER TABLE moddb.file  RENAME COLUMN thumbnailfilename to cdnpath;
		ALTER TABLE moddb.file ADD hasthumbnail BOOL NOT NULL DEFAULT 0 AFTER type; -- could maybe be merged with type
END IF;

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='mod'
	AND COLUMN_NAME='logofilename') ) THEN
		ALTER TABLE moddb.mod  DROP COLUMN logofilename;
END IF;

-- -----------------------------------------------------
-- Table `moddb`.`modpeek_result`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `moddb`.`modpeek_result` (
  `fileid` INT NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastmodified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `detectedmodidstr` VARCHAR(255),
  `detectedmodversion` VARCHAR(255),
  PRIMARY KEY (`fileid`))
ENGINE = InnoDB;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
