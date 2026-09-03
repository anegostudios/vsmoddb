USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='assets' AND COLUMN_NAME='tagsCached') ) THEN
	ALTER TABLE `assets` DROP COLUMN `tagsCached`;

	ALTER TABLE `modTags` ADD COLUMN `votes` INT NOT NULL DEFAULT 0 AFTER `tagId`;

	CREATE TABLE IF NOT EXISTS `modTagVotes` (
		`modId`        INT        NOT NULL,
		`tagId`        INT        NOT NULL,
		`userId`       INT        NOT NULL,
		`vote`         TINYINT    NOT NULL,
		`created`      DATETIME   NOT NULL DEFAULT NOW(),
		`lastModified` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`modId`, `tagId`, `userId`),
		CONSTRAINT `FK_modTagVotes_modId` FOREIGN KEY (`modId`) REFERENCES `mods`(`modId`) ON UPDATE CASCADE ON DELETE CASCADE,
		CONSTRAINT `FK_modTagVotes_tagId` FOREIGN KEY (`tagId`) REFERENCES `tags`(`tagId`) ON UPDATE CASCADE ON DELETE CASCADE,
		CONSTRAINT `FK_modTagVotes_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
	)
	ENGINE = InnoDB;

	ALTER TABLE `tags` ADD UNIQUE INDEX `name` (`name`);
	ALTER TABLE `tags` MODIFY `name` VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL;
  ALTER TABLE `tags` MODIFY `text` TEXT         CHARACTER SET utf8mb4 NOT NULL;
END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
