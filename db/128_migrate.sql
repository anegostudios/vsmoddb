USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modReleases' AND COLUMN_NAME='retractionReason') ) THEN
	CREATE TABLE `modReleaseRetractions` (
		`releaseId`      INT       NOT NULL,
		`reason`         TEXT CHARACTER SET utf8mb4 NOT NULL,
		`created`        DATETIME  NOT NULL DEFAULT NOW(),
		`lastModified`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		`lastModifiedBy` INT       NOT NULL,
		PRIMARY KEY (`releaseId`),
		CONSTRAINT `FK_FK_modReleaseRetractions_releaseId` FOREIGN KEY (`releaseId`) REFERENCES `modReleases`(`releaseId`) ON UPDATE CASCADE ON DELETE CASCADE,
		CONSTRAINT `FK_modReleaseRetractions_lastModifiedBy` FOREIGN KEY (`lastModifiedBy`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
	)
	ENGINE = InnoDB;

	INSERT INTO modReleaseRetractions (releaseId, reason, created, lastModified, lastModifiedBy)
		SELECT r.releaseId, r.retractionReason, c.lastModified, c.lastModified, c.userId
		FROM modReleases r
		JOIN changelogs c ON c.assetId = r.assetId AND (c.text LIKE '%Retracted release.%' OR c.text LIKE '%Changed retraction reason.%')
		WHERE r.retractionReason IS NOT NULL;

	ALTER TABLE `modReleases` DROP COLUMN `retractionReason`;
END IF;


END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
