USE `moddb`;

CREATE TABLE IF NOT EXISTS `modRelations` (
  `relationId`       INT             NOT NULL AUTO_INCREMENT,
  `releaseId`        INT             NOT NULL COMMENT 'Relations are always pinned to a specific release. A mod that hosts multiple identifiers (e.g. linux variant) declares relations per release independently.',
  `targetIdentifier` VARCHAR(255)    NOT NULL COMMENT 'Always populated. Free-form modid string (e.g. "carrycapacity"). Resolved to targetModId when possible.',
  `targetModId`      INT                 NULL COMMENT 'FK to mods.modId when the target is a mod hosted in this moddb. Cache, kept in sync by the relation upsert function.',
  `relationType`     ENUM('required','optional','incompatible','tested_with') NOT NULL,
  `minVersion`       BIGINT UNSIGNED     NULL COMMENT 'Compiled semver, same encoding as modReleases.version. NULL = any version.',
  `maxVersion`       BIGINT UNSIGNED     NULL COMMENT 'Compiled semver, inclusive upper bound. NULL = no upper bound.',
  `origin`           ENUM('auto','manual') NOT NULL DEFAULT 'manual'
                     COMMENT 'auto = derived from modPeekResults.rawDependencies, manual = declared via UI. Sync only touches auto rows.',
  `createdByUserId`  INT             NOT NULL,
  `created`          DATETIME        NOT NULL DEFAULT NOW(),
  `lastModified`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`relationId`),
  UNIQUE INDEX `uq_relation` (`releaseId`, `targetIdentifier`, `relationType`),
  INDEX `targetIdentifier` (`targetIdentifier`),
  INDEX `targetModId` (`targetModId`),
  CONSTRAINT `FK_modRelations_targetModId` FOREIGN KEY (`targetModId`)     REFERENCES `mods`(`modId`)              ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `FK_modRelations_releaseId`   FOREIGN KEY (`releaseId`)       REFERENCES `modReleases`(`releaseId`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_modRelations_userId`      FOREIGN KEY (`createdByUserId`) REFERENCES `users`(`userId`)            ON UPDATE CASCADE ON DELETE RESTRICT
)
ENGINE = InnoDB;
